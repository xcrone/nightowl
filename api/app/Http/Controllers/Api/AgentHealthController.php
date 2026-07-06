<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Agent Health (docs/pages/agent-health.md) — monitors NightOwl's own ingest
 * pipeline, which has no telemetry table of its own. Per the build decision
 * ("real where a source exists; agent health simulated") this synthesizes a
 * stable, live-looking snapshot + history for the selected period, keyed off
 * the app id so each app reads consistently across requests.
 */
class AgentHealthController extends Controller
{
    public function show(App $app, Request $request)
    {
        [$from, $to, $period] = Period::resolve($request);
        $seed = crc32($app->app_id);

        $instances = [];
        $instanceCount = 2;
        for ($i = 1; $i <= $instanceCount; $i++) {
            $r = $this->rand($seed + $i, 0);
            $instances[] = [
                'name' => "nw_{$app->app_id}-web-{$i}:".(50 + $i),
                'health' => 'healthy',
                'ingest_per_s' => round(80 + $r * 40, 1),
                'drain_per_s' => round(78 + $r * 40, 1),
                'pg_latency_ms' => (int) (40 + $r * 60),
                'write_queue_pct' => round(10 + $r * 30, 1),
                'cpu_pct' => round(15 + $r * 40, 1),
                'memory_bytes' => (int) ((90 + $r * 40) * 1024 * 1024),
                'reject_pct' => round($r * 0.5, 2),
                'last_seen_at' => $to->toIso8601String(),
            ];
        }

        $bucket = Period::bucketSeconds($from, $to);
        $throughput = $buffer = $pgLatency = $score = [];
        for ($t = $from->getTimestamp(); $t <= $to->getTimestamp(); $t += $bucket) {
            $r = $this->rand($seed, $t);
            $iso = Carbon::createFromTimestamp($t)->toIso8601String();
            $throughput[] = ['t' => $iso, 'ingest' => round(80 + $r * 40, 1), 'drain' => round(78 + $r * 40, 1)];
            $buffer[] = ['t' => $iso, 'pending_rows' => (int) ($r * 5000)];
            $pgLatency[] = ['t' => $iso, 'ms' => (int) (40 + $r * 60)];
            $score[] = ['t' => $iso, 'score' => (int) (95 + $r * 5)];
        }

        return response()->json([
            'status' => 'healthy',
            'score' => 98,
            'last_report_at' => $to->toIso8601String(),
            'period' => $period,
            'instances' => $instances,
            'history' => [
                'throughput' => $throughput,
                'buffer' => $buffer,
                'pg_latency' => $pgLatency,
                'score' => $score,
            ],
        ]);
    }

    /** Deterministic 0..1 pseudo-random from a seed + time bucket. */
    private function rand(int $seed, int $t): float
    {
        $x = sin($seed * 12.9898 + $t * 0.0001) * 43758.5453;

        return $x - floor($x);
    }
}
