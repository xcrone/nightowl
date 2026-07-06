<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function index(string $resource, Request $request)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();

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

        $this->applySort($query, $config, $request);

        $perPage = min((int) $request->query('per_page', 25), 100);

        return response()->json($query->paginate($perPage)->withQueryString());
    }

    public function show(string $resource, int|string $id)
    {
        $config = $this->resourceConfig($resource);

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $config['model'];

        return response()->json($modelClass::findOrFail($id));
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
    public function related(string $resource, int|string $id)
    {
        $config = $this->resourceConfig($resource);

        /** @var \Illuminate\Database\Eloquent\Model $record */
        $record = $config['model']::findOrFail($id);

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
                $originRecord = $originConfig['model']::where($originConfig['parent_key'], $record->execution_id)->first();

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
                $originRecord = $originConfig['model']::where('trace_id', $record->trace_id)->first();

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

                $count = $cfg['model']::where('execution_source', $childrenFilter['execution_source'])
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
