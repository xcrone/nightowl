<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Support\Period;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated list pages (docs/pages/*-list.md). Rolls raw telemetry up per
 * key (route/job/command/query/host/cache-key/mailable/notification/user/
 * exception class) on the fly — GROUP BY + Postgres percentile_cont — scoped
 * by app_id + the period window. Config: config/aggregates.php.
 */
class AggregateController extends Controller
{
    public function index(App $app, string $resource, Request $request)
    {
        $config = config("aggregates.{$resource}");
        abort_if($config === null, 404);

        [$from, $to, $period] = Period::resolve($request);

        if (($config['source'] ?? null) === 'bespoke' && $resource === 'users') {
            return response()->json([
                'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
                'panels' => $this->userPanels($app->app_id, $from, $to),
                'data' => $this->users($app->app_id, $from, $to, $request),
            ]);
        }

        $model = new $config['model'];
        $base = fn () => DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->where('app_id', $app->app_id)
            ->whereBetween('created_at', [$from, $to]);

        // Page-scope filters (All Users / All Connections / All Levels).
        $applyScope = function (Builder $q) use ($config, $request) {
            foreach ($config['scope'] ?? [] as $key) {
                if ($request->filled($key)) {
                    $column = $key === 'connection' ? 'connection' : $key;
                    $q->where($column, $request->query($key));
                }
            }

            return $q;
        };

        // ---- grouped rows ----
        $rows = $applyScope($base());
        foreach ($config['group_by'] as $col) {
            $rows->groupBy($col);
        }
        $rows->select($this->selectExpressions($config, true));

        if ($q = trim((string) $request->query('q', ''))) {
            $rows->where(function ($w) use ($config, $q) {
                foreach ($config['search'] ?? [] as $col) {
                    $w->orWhere($col, 'ILIKE', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%');
                }
            });
        }

        [$sortCol, $dir] = $this->sort($config, $request);
        $rows->orderBy($sortCol, $dir);
        $rows->limit(200);

        $data = collect($rows->get())->map(fn ($r) => $this->normalizeRow((array) $r, $config))->values();

        // ---- panel totals (ungrouped over the same window) ----
        $panels = (array) $applyScope($base())->select($this->selectExpressions($config, false))->first();

        return response()->json([
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
            'panels' => $this->normalizeRow($panels, $config),
            'data' => $data,
        ]);
    }

    /** Build the SELECT list (grouped adds the group_by/label/extra columns). */
    private function selectExpressions(array $config, bool $grouped): array
    {
        $expr = ['COUNT(*) as total'];

        if ($grouped) {
            foreach ($config['group_by'] as $col) {
                $expr[] = $col;
            }
            if (($label = $config['label'] ?? null) && ! in_array($label, $config['group_by'], true)) {
                $expr[] = "MAX({$label}) as {$label}";
            }
            foreach ($config['extra'] ?? [] as $col) {
                $expr[] = "MAX({$col}) as {$col}";
            }
            foreach ($config['collect_distinct'] ?? [] as $alias => $col) {
                $expr[] = "string_agg(DISTINCT {$col}::text, ',') as {$alias}";
            }
            foreach ($config['distinct_count'] ?? [] as $alias => $col) {
                $expr[] = "COUNT(DISTINCT {$col}) as {$alias}";
            }
            if ($lastCol = $config['last'] ?? null) {
                $expr[] = "MAX({$lastCol}) as last_{$lastCol}";
            }
        }

        if ($config['duration'] ?? false) {
            $expr[] = 'ROUND(AVG(duration))::bigint as avg';
            $expr[] = 'percentile_cont(0.95) within group (order by duration)::bigint as p95';
            $expr[] = 'MIN(duration) as min';
            $expr[] = 'MAX(duration) as max';
        }

        foreach ($config['count_buckets'] ?? [] as $alias => $conditions) {
            $expr[] = 'SUM(CASE WHEN '.$this->conditionsSql($conditions)." THEN 1 ELSE 0 END) as {$alias}";
        }

        return array_map(fn ($e) => DB::raw($e), $expr);
    }

    /** Render config-derived (trusted) [col, op, val] conditions to SQL. */
    private function conditionsSql(array $conditions): string
    {
        return collect($conditions)->map(function ($c) {
            [$col, $op, $val] = $c;
            $lit = is_bool($val) ? ($val ? 'true' : 'false')
                : (is_int($val) || is_float($val) ? $val : "'".addslashes((string) $val)."'");

            return "{$col} {$op} {$lit}";
        })->implode(' AND ');
    }

    private function sort(array $config, Request $request): array
    {
        $sort = (string) $request->query('sort', $config['default_sort'] ?? '-total');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');

        if (! in_array($col, $config['sortable'] ?? [], true)) {
            $col = ltrim($config['default_sort'] ?? '-total', '-');
            $dir = 'desc';
        }

        // 'count'/'calls' are UI-friendly aliases for the same COUNT(*)/total.
        if (in_array($col, ['count', 'calls'], true)) {
            $col = 'total';
        }
        if ($col === 'hit_rate') {
            $col = 'total';
        }

        return [$col, $dir];
    }

    /** Post-process a DB row: cast, split channels, add count/calls aliases. */
    private function normalizeRow(array $row, array $config): array
    {
        if (isset($row['total'])) {
            $row['total'] = (int) $row['total'];
            $row['count'] = $row['total'];
            $row['calls'] = $row['total'];
        }
        foreach (['avg', 'p95', 'min', 'max'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        foreach (array_keys($config['count_buckets'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = (int) $row[$alias];
            }
        }
        foreach (array_keys($config['collect_distinct'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = $row[$alias] ? explode(',', $row[$alias]) : [];
            }
        }
        // Cache hit rate convenience.
        if (isset($row['hits'], $row['misses'])) {
            $reads = $row['hits'] + $row['misses'];
            $row['hit_rate'] = $reads > 0 ? round($row['hits'] / $reads * 100, 2) : 0.0;
        }

        return $row;
    }

    // ---- bespoke: per-user rollup (requests + jobs + exceptions) ----

    private function users(string $appId, $from, $to, Request $request): array
    {
        $window = fn ($m) => $m::query()->forApp($appId)->whereBetween('created_at', [$from, $to]);

        $requests = $window(RequestRecord::class)
            ->selectRaw('user_id,
                COUNT(*) as requests,
                SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) as c2xx,
                SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as c4xx,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as c5xx,
                MAX(created_at) as last_seen')
            ->whereNotNull('user_id')->groupBy('user_id')->get()->keyBy('user_id');

        $jobs = $window(JobRecord::class)->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as queued_jobs')->groupBy('user_id')->get()->keyBy('user_id');

        $exceptions = $window(ExceptionRecord::class)->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as exceptions')->groupBy('user_id')->get()->keyBy('user_id');

        $emails = NightowlUser::query()->forApp($appId)->pluck('email', 'user_id');

        $q = trim((string) $request->query('q', ''));

        return $requests->map(function ($r) use ($jobs, $exceptions, $emails) {
            return [
                'user_id' => $r->user_id,
                'email' => $emails[$r->user_id] ?? null,
                'c2xx' => (int) $r->c2xx, 'c4xx' => (int) $r->c4xx, 'c5xx' => (int) $r->c5xx,
                'requests' => (int) $r->requests,
                'queued_jobs' => (int) ($jobs[$r->user_id]->queued_jobs ?? 0),
                'exceptions' => (int) ($exceptions[$r->user_id]->exceptions ?? 0),
                'last_seen' => $r->last_seen,
            ];
        })->values()
            ->when($q !== '', fn ($c) => $c->filter(fn ($u) => str_contains(strtolower($u['user_id'].' '.$u['email']), strtolower($q)))->values())
            ->sortByDesc('requests')->values()->all();
    }

    private function userPanels(string $appId, $from, $to): array
    {
        $r = RequestRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT user_id) as authenticated_total,
                SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest')->first();

        return [
            'authenticated_total' => (int) ($r->authenticated_total ?? 0),
            'requests_split' => ['authenticated' => (int) ($r->authenticated ?? 0), 'guest' => (int) ($r->guest ?? 0)],
        ];
    }
}
