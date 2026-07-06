<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Support\Period;
use App\Support\SearchTerm;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function index(App $app, string $resource, Request $request)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query()->where('app_id', $app->app_id);

        // Optional period window (logs/issues lists carry the period selector).
        if ($request->filled('period') || $request->filled('from') || $request->filled('to')) {
            [$from, $to] = Period::resolve($request);
            $query->whereBetween('created_at', [$from, $to]);
        }

        foreach ($config['filters'] as $key => $filter) {
            $this->applyFilter($query, $filter, $key, $request);
        }

        // Structural correlation filter (not a per-resource "business"
        // filter, so it isn't declared in $config['filters']): links back to
        // ResourceDetail's "Related" panel — see related() below. Both
        // params are required together since execution_id alone is
        // ambiguous across execution sources.
        if (($config['traces_to_parent'] ?? false)
            && $request->filled('execution_source')
            && $request->filled('execution_id')) {
            $query->where('execution_source', $request->query('execution_source'))
                ->where('execution_id', $request->query('execution_id'));
        }

        $this->applySearch($query, $config, $request);
        $this->applySort($query, $config, $request);

        $perPage = min((int) $request->query('per_page', 25), 100);

        return response()->json($query->paginate($perPage)->withQueryString());
    }

    public function show(App $app, string $resource, int|string $id)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        return response()->json(
            $modelClass::query()->where('app_id', $app->app_id)->findOrFail($id)
        );
    }

    /**
     * Telescope-style correlation: what else happened in the same request/
     * job/command/scheduled-task as this record, and what originated it.
     *
     * See config/telemetry.php's "Trace correlation" block for how
     * `parent_key`/`parent_source`/`traces_to_parent` map the two sides of
     * the relationship (an origin's own identity vs. a child's
     * execution_source/execution_id pointer back to it).
     */
    public function related(App $app, string $resource, int|string $id)
    {
        $config = $this->resourceConfig($resource);
        $appId = $app->app_id;

        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = $config['model']::query()->where('app_id', $appId)->findOrFail($id);

        $originResources = array_filter(
            config('telemetry.resources'),
            fn (array $cfg) => isset($cfg['parent_key'])
        );

        $sourceToResource = [];
        foreach ($originResources as $key => $cfg) {
            $sourceToResource[$cfg['parent_source']] = $key;
        }

        $origin = null;

        if (($config['traces_to_parent'] ?? false) && $record->execution_source && $record->execution_id) {
            $originKey = $sourceToResource[$record->execution_source] ?? null;

            if ($originKey !== null) {
                $originConfig = $originResources[$originKey];
                $originRecord = $originConfig['model']::where('app_id', $appId)
                    ->where($originConfig['parent_key'], $record->execution_id)->first();

                if ($originRecord) {
                    $origin = ['resource' => $originKey, 'record' => $originRecord];
                }
            }
        }

        // Fallback: a processed job attempt has no execution_source/_id of
        // its own (it *is* an origin), but Nightwatch propagates the
        // dispatching request/command's trace_id through the queue, so it
        // can still be traced back to what originally queued it.
        if ($origin === null && $record->trace_id) {
            foreach (['requests', 'commands', 'scheduled-tasks'] as $originKey) {
                if ($originKey === $resource) {
                    continue;
                }

                $originConfig = $originResources[$originKey];
                $originRecord = $originConfig['model']::where('app_id', $appId)
                    ->where('trace_id', $record->trace_id)->first();

                if ($originRecord) {
                    $origin = ['resource' => $originKey, 'record' => $originRecord];
                    break;
                }
            }
        }

        $childrenFilter = null;
        $children = [];

        if (isset($config['parent_key']) && $record->{$config['parent_key']} !== null) {
            $childrenFilter = [
                'execution_source' => $config['parent_source'],
                'execution_id' => $record->{$config['parent_key']},
            ];

            foreach (config('telemetry.resources') as $key => $cfg) {
                if (! ($cfg['traces_to_parent'] ?? false) || $key === $resource) {
                    continue;
                }

                $count = $cfg['model']::where('app_id', $appId)
                    ->where('execution_source', $childrenFilter['execution_source'])
                    ->where('execution_id', $childrenFilter['execution_id'])
                    ->count();

                if ($count > 0) {
                    $children[$key] = $count;
                }
            }
        }

        return response()->json([
            'origin' => $origin ? ['resource' => $origin['resource'], 'record' => $origin['record']] : null,
            'children_filter' => $childrenFilter,
            'children' => $children,
        ]);
    }

    protected function applyFilter($query, array $filter, string $key, Request $request): void
    {
        if (array_key_exists('value', $filter)) {
            // Flag filter: presence of a truthy query param triggers a
            // config-fixed where() clause, e.g. ?failed=1 -> status_code >= 500.
            if ($request->has($key) && $request->boolean($key)) {
                $query->where($filter['column'], $filter['op'], $filter['value']);
            }

            return;
        }

        // Param-driven filter: the where() value comes from the query string.
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return;
        }

        $query->where($filter['column'], $filter['op'] ?? '=', $value);
    }

    /**
     * Applies ?q= against whichever search strategy config/telemetry.php's
     * 'search' key declares for this resource. Composes as an additional
     * AND-ed where() alongside the existing filters/traces_to_parent
     * scoping above — it narrows the same query further, it doesn't
     * replace those clauses.
     *
     * tsvector and trigram are OR'd together within the search clause
     * itself (a resource declaring both, e.g. exceptions, should match on
     * either) — but the whole thing is one where(fn (...) => ...) group so
     * it combines with the surrounding AND-ed filters correctly regardless
     * of operator precedence.
     */
    protected function applySearch($query, array $config, Request $request): void
    {
        $search = $config['search'] ?? null;

        if ($search === null) {
            return;
        }

        $q = SearchTerm::fromRequest($request);

        if ($q === null) {
            return;
        }

        $query->where(function ($outer) use ($search, $q) {
            if (isset($search['tsvector'])) {
                // websearch_to_tsquery tolerates arbitrary user input
                // (quotes, "-", "OR", trailing punctuation) without raising
                // a tsquery syntax error, unlike plainto_tsquery/
                // to_tsquery — the closest built-in parser to "how a
                // search box actually behaves".
                //
                // $search['tsvector'] is a fixed string from static PHP
                // config (never request input), so interpolating it into
                // the raw SQL as a column identifier is safe; $q is the
                // only user-controlled value here and it's always passed
                // as a bound parameter (?), never concatenated into the
                // SQL string.
                $outer->orWhereRaw(
                    "{$search['tsvector']} @@ websearch_to_tsquery('english', ?)",
                    [$q]
                );
            }

            if (isset($search['trigram'])) {
                $escaped = SearchTerm::escapeForLike($q);

                $outer->orWhere(function ($trigram) use ($search, $escaped) {
                    foreach ($search['trigram'] as $column) {
                        $trigram->orWhere($column, 'ILIKE', '%'.$escaped.'%');
                    }
                });
            }
        });
    }

    protected function applySort($query, array $config, Request $request): void
    {
        $sort = (string) $request->query('sort', $config['default_sort'] ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $config['sortable'] ?? [], true)) {
            $column = ltrim($config['default_sort'] ?? '-created_at', '-');
            $direction = 'desc';
        }

        $query->orderBy($column, $direction);
    }

    protected function resourceConfig(string $resource): array
    {
        $config = config("telemetry.resources.{$resource}");

        abort_if($config === null, 404);

        return $config;
    }
}
