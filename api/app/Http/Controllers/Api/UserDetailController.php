<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Support\Period;
use Illuminate\Http\Request;

/**
 * Single-user drill-down (docs/pages/user-detail.md): identity + request
 * mix + which routes/jobs this user drove, scoped to one app + period.
 */
class UserDetailController extends Controller
{
    public function show(App $app, string $userId, Request $request)
    {
        [$from, $to] = Period::resolve($request);
        $appId = $app->app_id;

        $user = NightowlUser::query()->forApp($appId)->where('user_id', $userId)->first();

        $requests = RequestRecord::query()->forApp($appId)->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) total,
                SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) c2xx,
                SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) c4xx,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) c5xx')
            ->first();

        $topRoutes = RequestRecord::query()->forApp($appId)->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('route_path, MAX(method) method, COUNT(*) count')
            ->groupBy('route_path')->orderByDesc('count')->limit(10)->get()
            ->map(fn ($r) => ['method' => $r->method, 'route_path' => $r->route_path, 'count' => (int) $r->count]);

        $slowestRoutes = RequestRecord::query()->forApp($appId)->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('route_path, MAX(method) method, percentile_cont(0.95) within group (order by duration)::bigint p95')
            ->groupBy('route_path')->orderByDesc('p95')->limit(10)->get()
            ->map(fn ($r) => ['method' => $r->method, 'route_path' => $r->route_path, 'p95' => (int) $r->p95]);

        $topJobs = JobRecord::query()->forApp($appId)->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('job_class, COUNT(*) count')
            ->groupBy('job_class')->orderByDesc('count')->limit(10)->get()
            ->map(fn ($r) => ['job_class' => $r->job_class, 'count' => (int) $r->count]);

        return response()->json([
            'user' => [
                'id' => $userId,
                'name' => $user?->name,
                'email' => $user?->email,
                'last_seen' => $user?->updated_at?->toIso8601String(),
            ],
            'requests' => [
                'total' => (int) $requests->total, 'c2xx' => (int) $requests->c2xx,
                'c4xx' => (int) $requests->c4xx, 'c5xx' => (int) $requests->c5xx,
            ],
            'top_routes' => $topRoutes,
            'slowest_routes' => $slowestRoutes,
            'top_jobs' => $topJobs,
        ]);
    }
}
