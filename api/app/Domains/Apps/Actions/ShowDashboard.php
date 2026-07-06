<?php

namespace App\Domains\Apps\Actions;

use App\Models\App;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Support\Period;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /apps/{app}/dashboard — App Dashboard summary
 * (docs/pages/app-dashboard.md): a single-window health rollup — request
 * volume/latency, exceptions, job throughput, users.
 */
class ShowDashboard
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app, ActionRequest $request)
    {
        [$from, $to, $period] = Period::resolve($request);
        $appId = $app->app_id;

        $req = RequestRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) total,
                SUM(CASE WHEN status_code < 400 THEN 1 ELSE 0 END) c2xx,
                SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) c4xx,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) c5xx,
                MIN(duration) min, MAX(duration) max, ROUND(AVG(duration))::bigint avg,
                percentile_cont(0.95) within group (order by duration)::bigint p95,
                COUNT(DISTINCT user_id) authenticated_total,
                SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) req_auth,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) req_guest')
            ->first();

        $exc = ExceptionRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) count, COUNT(DISTINCT user_id) users,
                SUM(CASE WHEN handled THEN 1 ELSE 0 END) handled,
                SUM(CASE WHEN NOT handled THEN 1 ELSE 0 END) unhandled')
            ->first();

        $jobs = JobRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->selectRaw("COUNT(*) total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) processed,
                SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) released,
                MIN(duration) min, MAX(duration) max, ROUND(AVG(duration))::bigint avg,
                percentile_cont(0.95) within group (order by duration)::bigint p95")
            ->first();

        return response()->json([
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'period' => $period,
            'requests' => [
                'total' => (int) $req->total, 'c2xx' => (int) $req->c2xx,
                'c4xx' => (int) $req->c4xx, 'c5xx' => (int) $req->c5xx,
            ],
            'duration' => [
                'min' => (int) $req->min, 'max' => (int) $req->max,
                'avg' => (int) $req->avg, 'p95' => (int) $req->p95,
            ],
            'exceptions' => [
                'count' => (int) $exc->count, 'users_impacted' => (int) $exc->users,
                'handled' => (int) $exc->handled, 'unhandled' => (int) $exc->unhandled,
            ],
            'jobs' => [
                'total' => (int) $jobs->total, 'failed' => (int) $jobs->failed,
                'processed' => (int) $jobs->processed, 'released' => (int) $jobs->released,
            ],
            'job_duration' => [
                'min' => (int) $jobs->min, 'max' => (int) $jobs->max,
                'avg' => (int) $jobs->avg, 'p95' => (int) $jobs->p95,
            ],
            'users' => [
                'authenticated_total' => (int) $req->authenticated_total,
                'requests_split' => ['authenticated' => (int) $req->req_auth, 'guest' => (int) $req->req_guest],
                'impacted_by_exceptions' => $this->impactedUsers($appId, $from, $to),
                'most_active' => $this->mostActiveUsers($appId, $from, $to),
            ],
        ]);
    }

    private function impactedUsers(string $appId, $from, $to): array
    {
        $rows = ExceptionRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) count')->groupBy('user_id')
            ->orderByDesc('count')->limit(5)->get();

        return $this->withEmails($appId, $rows);
    }

    private function mostActiveUsers(string $appId, $from, $to): array
    {
        $rows = RequestRecord::query()->forApp($appId)->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) count')->groupBy('user_id')
            ->orderByDesc('count')->limit(5)->get();

        return $this->withEmails($appId, $rows);
    }

    private function withEmails(string $appId, $rows): array
    {
        $emails = NightowlUser::query()->forApp($appId)->pluck('email', 'user_id');

        return $rows->map(fn ($r) => [
            'user_id' => $r->user_id,
            'email' => $emails[$r->user_id] ?? null,
            'count' => (int) $r->count,
        ])->all();
    }
}
