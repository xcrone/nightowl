<?php

namespace App\Actions\Rollups;

use App\Models\Telemetry\CacheRollup;
use App\Models\Telemetry\JobRollup;
use App\Models\Telemetry\OutgoingRequestRollup;
use App\Models\Telemetry\QueryRollup;
use App\Models\Telemetry\RequestRollup;
use App\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use NightOwl\Support\QueryHistogram;

/**
 * GET /api/rollups/{type} — legacy, **not app-scoped** (this pre-dates
 * `app_id`; a per-app equivalent exists via
 * `App\Actions\Aggregates\IndexAggregate`, but this endpoint remains for any
 * lingering callers — see routes/api.php).
 *
 * Cross-cutting engine (Group A, app/Actions/, not a bounded context) — see
 * App\Actions\Telemetry\IndexTelemetryResource's docblock for the shared
 * rationale.
 */
class IndexRollup
{
    use AsAction;

    /**
     * group_by + representative label column per rollup type. Cache has no
     * histogram (grouped by key+store instead of group_hash) — see
     * agent/database/migrations/..._create_nightowl_cache_rollups_table.php.
     */
    protected const TYPES = [
        'queries' => ['model' => QueryRollup::class, 'group_by' => ['group_hash'], 'label' => 'sql_query', 'histogram' => true],
        'requests' => ['model' => RequestRollup::class, 'group_by' => ['group_hash'], 'label' => 'route_path', 'histogram' => true],
        'jobs' => ['model' => JobRollup::class, 'group_by' => ['group_hash'], 'label' => 'job_class', 'histogram' => true],
        'outgoing-requests' => ['model' => OutgoingRequestRollup::class, 'group_by' => ['group_hash'], 'label' => 'host', 'histogram' => true],
        'cache-events' => ['model' => CacheRollup::class, 'group_by' => ['key', 'store'], 'label' => null, 'histogram' => false],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function handle(string $type, ActionRequest $request)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $spec = self::TYPES[$type];
        /** @var class-string<Model> $modelClass */
        $modelClass = $spec['model'];

        // '24h' preserves this endpoint's pre-Actions default lookback
        // window exactly (Period::resolve()'s own default is '1h' for
        // everywhere else) — see Batch 7's plan note on this opportunistic
        // Period::resolve() adoption.
        [$from, $to] = Period::resolve($request, '24h');

        $query = $modelClass::query()->whereBetween('bucket_start', [$from, $to]);

        if ($environment = $request->query('environment')) {
            $query->where('environment', $environment);
        }

        $rows = $query->get();

        $groups = $rows->groupBy(fn ($row) => collect($spec['group_by'])->map(fn ($col) => $row->{$col})->implode('|'));

        $result = $groups->map(function ($rowsInGroup) use ($spec) {
            /** @var Collection $rowsInGroup */
            $first = $rowsInGroup->first();

            $summary = [
                'call_count' => (int) $rowsInGroup->sum('call_count'),
                'total_duration' => (int) $rowsInGroup->sum('total_duration'),
                'min_duration' => $rowsInGroup->min('min_duration'),
                'max_duration' => $rowsInGroup->max('max_duration'),
            ];

            foreach ($spec['group_by'] as $col) {
                $summary[$col] = $first->{$col};
            }

            if ($spec['label']) {
                $summary['label'] = $first->{$spec['label']};
            }

            $summary['avg_duration'] = $summary['call_count'] > 0
                ? (int) round($summary['total_duration'] / $summary['call_count'])
                : 0;

            if ($spec['histogram']) {
                $binCounts = [];
                for ($i = 0, $n = QueryHistogram::binCount(); $i < $n; $i++) {
                    $binCounts[$i] = (int) $rowsInGroup->sum(sprintf('hist_%02d', $i));
                }

                $summary['p50'] = (int) QueryHistogram::estimatePercentile($binCounts, 0.50, $summary['min_duration'], $summary['max_duration']);
                $summary['p95'] = (int) QueryHistogram::estimatePercentile($binCounts, 0.95, $summary['min_duration'], $summary['max_duration']);
                $summary['p99'] = (int) QueryHistogram::estimatePercentile($binCounts, 0.99, $summary['min_duration'], $summary['max_duration']);
            }

            return $summary;
        })->sortByDesc('call_count')->values();

        return response()->json([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'data' => $result,
        ]);
    }
}
