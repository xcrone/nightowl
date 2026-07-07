<?php

namespace App\Actions\Aggregates;

use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Support\AggregateQuery;
use App\Support\EnvironmentScope;
use App\Support\Period;
use App\Support\SearchTerm;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/aggregate/{resource} — aggregated list pages
 * (docs/pages/*-list.md). Rolls raw telemetry up per key
 * (route/job/command/query/host/cache-key/mailable/notification/user/
 * exception class) on the fly — GROUP BY + Postgres percentile_cont —
 * scoped by app_id + the period window. Config: config/aggregates.php.
 *
 * Cross-cutting engine (Group A, app/Actions/, not a bounded context) — see
 * IndexTelemetryResource's docblock for the shared rationale.
 */
class IndexAggregate
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $resource, ActionRequest $request)
    {
        $config = config("aggregates.{$resource}");
        abort_if($config === null, 404);

        [$from, $to, $period] = Period::resolve($request);

        if (($config['source'] ?? null) === 'bespoke' && $resource === 'users') {
            return response()->json([
                'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
                'panels' => $this->userPanels($app->app_id, $from, $to),
                'data' => $this->users($app->app_id, $from, $to, $request, $config),
            ]);
        }

        $model = new $config['model'];
        $base = fn () => DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->where('app_id', $app->app_id)
            ->whereBetween('created_at', [$from, $to]);

        // Page-scope filters (All Users / All Connections / All Levels +
        // the app switcher's All Environments). The bespoke users branch
        // returns above this — its table has no environment column.
        $applyScope = function (Builder $q) use ($config, $request) {
            foreach ($config['scope'] ?? [] as $key) {
                if ($request->filled($key)) {
                    $column = $key === 'connection' ? 'connection' : $key;
                    $q->where($column, $request->query($key));
                }
            }

            EnvironmentScope::apply($q, $request);

            return $q;
        };

        // ---- grouped rows ----
        $rows = $applyScope($base());
        foreach ($config['group_by'] as $col) {
            $rows->groupBy($col);
        }
        $rows->select(AggregateQuery::selectExpressions($config, true));

        if ($q = SearchTerm::fromRequest($request)) {
            $escaped = SearchTerm::escapeForLike($q);
            $rows->where(function ($w) use ($config, $escaped) {
                foreach ($config['search'] ?? [] as $col) {
                    $w->orWhere($col, 'ILIKE', '%'.$escaped.'%');
                }
            });
        }

        [$sortCol, $dir] = AggregateQuery::sort($config, $request);
        $rows->orderBy($sortCol, $dir);
        $rows->limit(200);

        $data = collect($rows->get())->map(fn ($r) => AggregateQuery::normalizeRow((array) $r, $config))->values();

        // ---- panel totals (ungrouped over the same window) ----
        $panels = (array) $applyScope($base())->select(AggregateQuery::selectExpressions($config, false))->first();

        return response()->json([
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
            'panels' => AggregateQuery::shapePanels(AggregateQuery::normalizeRow($panels, $config), $config),
            'data' => $data,
        ]);
    }

    // ---- bespoke: per-user rollup (requests + jobs + exceptions) ----

    private function users(string $appId, $from, $to, ActionRequest $request, array $config): array
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

        $q = SearchTerm::fromRequest($request);

        // Honor ?sort= against the users sortable whitelist (config/aggregates.php),
        // falling back to default_sort — matching the grouped aggregates' behaviour.
        [$sortCol, $dir] = AggregateQuery::sort($config, $request);

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
            ->when($q !== null, fn ($c) => $c->filter(fn ($u) => str_contains(strtolower($u['user_id'].' '.$u['email']), strtolower($q)))->values())
            ->sortBy(fn ($u) => $u[$sortCol] ?? null, SORT_REGULAR, $dir === 'desc')
            ->values()->all();
    }

    private function userPanels(string $appId, $from, $to): array
    {
        $r = RequestRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT user_id) as authenticated_total,
                SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest')->first();

        // Nested to match web/src/aggregateConfig.js users.panels(): reads
        // p.users.total and p.requests_split.{authenticated,guest}.
        return [
            'users' => ['total' => (int) ($r->authenticated_total ?? 0)],
            'requests_split' => ['authenticated' => (int) ($r->authenticated ?? 0), 'guest' => (int) ($r->guest ?? 0)],
        ];
    }
}
