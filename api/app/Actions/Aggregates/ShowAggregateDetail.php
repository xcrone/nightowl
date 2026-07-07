<?php

namespace App\Actions\Aggregates;

use App\Models\App;
use App\Support\AggregateKey;
use App\Support\AggregateQuery;
use App\Support\EnvironmentScope;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/aggregate/{resource}/{key} — per-key drill-down detail
 * (docs/pages/aggregate-detail.md) for the 8 clickable Activity aggregates
 * (requests/outgoing-requests/jobs/commands/scheduled-tasks/queries/
 * notifications/mail — those flagged `detail => true` in config/aggregates.php).
 *
 * `{key}` is the base64url-encoded aggregate key (see App\Support\AggregateKey)
 * — the route_path / host / job_class / command / query group_hash / mailable /
 * notification class the parent list row rolls up. Scoped by app_id + the
 * period window + that key. Returns the same nested stat `panels` as the list
 * row, a P50/P95/P99 duration breakdown, and a paginated list of the individual
 * underlying occurrences (with the doc's duration/outcome filter chips).
 *
 * Cross-cutting engine (app/Actions/, not a bounded context) — see
 * IndexAggregate for the shared rationale.
 */
class ShowAggregateDetail
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, string $resource, string $key, ActionRequest $request)
    {
        $config = config("aggregates.{$resource}");
        abort_if($config === null || ! ($config['detail'] ?? false), 404);

        $rawKey = AggregateKey::decode($key);
        [$from, $to, $period] = Period::resolve($request);

        /** @var Model $model */
        $model = new $config['model'];

        // ---- one merged aggregate pass: panels + the P50/P99 that widen the
        // list page's single-p95 duration panel into the detail breakdown
        // (avg + p95 already come from selectExpressions), plus queries'
        // SUM(duration). Previously panels and percentiles ran as two
        // overlapping ungrouped scans that both recomputed avg + p95. ----
        $select = AggregateQuery::selectExpressions($config, false);
        if ($config['duration'] ?? false) {
            $select[] = DB::raw('percentile_cont(0.5) within group (order by duration)::bigint as p50');
            $select[] = DB::raw('percentile_cont(0.99) within group (order by duration)::bigint as p99');
        }
        if ($resource === 'queries') {
            $select[] = DB::raw('SUM(duration) as total_time');
        }
        $row = (array) $this->scoped($model, $config, $rawKey, $from, $to, $request)
            ->select($select)
            ->first();

        $panels = AggregateQuery::shapePanels(
            AggregateQuery::normalizeRow($row, $config),
            $config
        );

        // ---- P50/P95/P99 (+avg) duration breakdown, read off the merged row ----
        $percentiles = null;
        if ($config['duration'] ?? false) {
            $percentiles = [
                'avg' => $row['avg'] !== null ? (int) $row['avg'] : null,
                'p50' => $row['p50'] !== null ? (int) $row['p50'] : null,
                'p95' => $row['p95'] !== null ? (int) $row['p95'] : null,
                'p99' => $row['p99'] !== null ? (int) $row['p99'] : null,
            ];
        }

        // ---- representative label + meta (method/connection/rw/schedule) ----
        $representative = $this->occurrences($model, $config, $rawKey, $from, $to, $request)
            ->reorder()->latest('created_at')->first();
        $meta = $this->meta($representative, $config);

        // ---- paginated individual occurrences (+ filter chips) ----
        $occ = $this->occurrences($model, $config, $rawKey, $from, $to, $request);
        $this->applyChips($occ, $config, $percentiles, $request);
        $occ->latest('created_at');
        // Floor at 1 so a ?per_page=0 (or negative) can't reach paginate(0)
        // -> DivisionByZero.
        $perPage = max(min((int) $request->query('per_page', 25), 100), 1);

        $response = [
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
            'resource' => $resource,
            'key' => $key,
            'label' => $meta['label'],
            'meta' => $meta['meta'],
            'panels' => $panels,
            'percentiles' => $percentiles,
            'occurrences' => $occ->paginate($perPage)->withQueryString(),
        ];

        // Queries get the extra Info/SQL detail panels the doc calls for.
        if ($resource === 'queries') {
            $response['info'] = $this->queryInfo($model, $config, $rawKey, $from, $to, $request, $row, $percentiles);
            $response['sql'] = $meta['label'];
        }

        return response()->json($response);
    }

    /** A raw DB::table builder for the panels/percentile aggregates. */
    private function scoped(Model $model, array $config, string $key, $from, $to, ActionRequest $request): QueryBuilder
    {
        $q = DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->where('app_id', $request->route('app')->app_id)
            ->whereBetween('created_at', [$from, $to]);

        AggregateQuery::applyKeyScope($q, $config, $key, $request);
        $this->applyScope($q, $config, $request);

        return $q;
    }

    /** An Eloquent builder for the paginated occurrence rows. */
    private function occurrences(Model $model, array $config, string $key, $from, $to, ActionRequest $request): Builder
    {
        $q = $model->newQuery()
            ->where('app_id', $request->route('app')->app_id)
            ->whereBetween('created_at', [$from, $to]);

        AggregateQuery::applyKeyScope($q, $config, $key, $request);
        $this->applyScope($q, $config, $request);

        return $q;
    }

    /** Page-scope filters (All Users / All Connections / All Environments), same as the list page. */
    private function applyScope($query, array $config, ActionRequest $request): void
    {
        foreach ($config['scope'] ?? [] as $scopeKey) {
            if ($request->filled($scopeKey)) {
                $query->where($scopeKey, $request->query($scopeKey));
            }
        }

        EnvironmentScope::apply($query, $request);
    }

    /** The two independent filter-chip rows above the occurrence table. */
    private function applyChips(Builder $query, array $config, ?array $percentiles, ActionRequest $request): void
    {
        // Duration bucket: ?bucket=avg|p50|p95|p99 -> duration >= that threshold.
        $bucket = $request->query('bucket');
        if ($percentiles !== null && $bucket !== null && ($percentiles[$bucket] ?? null) !== null) {
            $query->where('duration', '>=', $percentiles[$bucket]);
        }

        // Resource-specific outcome breakdown: ?outcome=<count_bucket alias>.
        AggregateQuery::applyOutcome($query, $config, $request);
    }

    /** Representative label + carried-through meta fields for the title. */
    private function meta(?Model $row, array $config): array
    {
        if ($row === null) {
            return ['label' => null, 'meta' => []];
        }

        $meta = [];
        foreach ($config['extra'] ?? [] as $alias => $col) {
            $alias = is_int($alias) ? $col : $alias;
            $meta[$alias] = $row->{$col};
        }
        if ($cronCol = $config['cron'] ?? null) {
            $meta['expression'] = $row->{$cronCol};
            $meta['schedule'] = AggregateQuery::humanizeCron((string) $row->{$cronCol});
        }

        return ['label' => $row->{$config['label']}, 'meta' => $meta];
    }

    /**
     * Queries Info JSON block (docs/pages/aggregate-detail.md). Reuses the
     * merged aggregate row (calls = total, avg_time = avg, total_time =
     * SUM(duration) folded into that same pass) so the only extra query here
     * is the genuinely distinct environments pluck.
     */
    private function queryInfo(Model $model, array $config, string $key, $from, $to, ActionRequest $request, array $row, ?array $percentiles): array
    {
        $environments = $this->occurrences($model, $config, $key, $from, $to, $request)
            ->whereNotNull('environment')->distinct()->pluck('environment')->all();

        return [
            'calls' => (int) ($row['total'] ?? 0),
            'total_time' => (int) ($row['total_time'] ?? 0),
            'avg_time' => ($row['avg'] ?? null) !== null ? (int) $row['avg'] : null,
            'p50' => $percentiles['p50'] ?? null,
            'p95' => $percentiles['p95'] ?? null,
            'p99' => $percentiles['p99'] ?? null,
            'environments' => $environments,
        ];
    }
}
