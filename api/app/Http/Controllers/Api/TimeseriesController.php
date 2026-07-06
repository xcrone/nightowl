<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\RequestRecord;
use App\Support\Period;
use Illuminate\Http\Request;

/**
 * Bucketed time series for the dashboard charts (docs/pages/app-dashboard.md).
 * metric ∈ requests | duration | exceptions | jobs.
 */
class TimeseriesController extends Controller
{
    private const METRICS = [
        'requests' => [RequestRecord::class, [
            'c2xx' => 'SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END)',
            'c4xx' => 'SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END)',
            'c5xx' => 'SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END)',
        ]],
        'duration' => [RequestRecord::class, [
            'avg' => 'ROUND(AVG(duration))::bigint',
            'p95' => 'percentile_cont(0.95) within group (order by duration)::bigint',
        ]],
        'exceptions' => [ExceptionRecord::class, [
            'handled' => 'SUM(CASE WHEN handled THEN 1 ELSE 0 END)',
            'unhandled' => 'SUM(CASE WHEN NOT handled THEN 1 ELSE 0 END)',
        ]],
        'jobs' => [JobRecord::class, [
            'processed' => "SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END)",
            'released' => "SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END)",
            'failed' => "SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)",
        ]],
    ];

    public function show(App $app, string $metric, Request $request)
    {
        abort_unless(array_key_exists($metric, self::METRICS), 404);

        [$model, $values] = self::METRICS[$metric];
        [$from, $to, $period] = Period::resolve($request);
        $bucket = Period::bucketSeconds($from, $to);

        $bucketExpr = "to_timestamp(floor(extract(epoch from created_at) / {$bucket}) * {$bucket})";

        $select = ["{$bucketExpr} as bucket"];
        foreach ($values as $alias => $sql) {
            $select[] = "{$sql} as {$alias}";
        }

        $rows = (new $model)::query()->forApp($app->app_id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(implode(', ', $select))
            ->groupByRaw($bucketExpr)
            ->orderByRaw($bucketExpr)
            ->get();

        $series = $rows->map(function ($r) use ($values) {
            $out = ['t' => \Illuminate\Support\Carbon::parse($r->bucket)->toIso8601String(), 'values' => []];
            foreach (array_keys($values) as $alias) {
                $out['values'][$alias] = (int) $r->{$alias};
            }

            return $out;
        })->values();

        return response()->json([
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(),
            'period' => $period, 'bucket_seconds' => $bucket, 'series' => $series,
        ]);
    }
}
