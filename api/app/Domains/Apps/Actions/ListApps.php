<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Domains\Apps\Resources\TeamResource;
use App\Models\App;
use App\Models\Org;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\RequestRecord;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps — apps grouped by team, each with a live 1h health summary
 * — drives the Org Dashboard cards (docs/pages/org-dashboard.md).
 */
class ListApps
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle()
    {
        $org = Org::query()->firstOrFail();

        $teams = $org->teams()->with('apps')->get()->map(function ($team) {
            // apps_count/apps are computed extras, not raw Team fields, so
            // they're merged onto the base TeamResource shape here rather
            // than living on the Resource itself.
            return array_merge((new TeamResource($team))->resolve(), [
                'apps_count' => $team->apps->count(),
                'apps' => $team->apps->map(fn (App $app) => $this->health($app))->values(),
            ]);
        });

        return response()->json([
            'org' => (new OrgResource($org))->resolve(),
            'teams' => $teams,
        ]);
    }

    /** One app's health card payload over the last 1h window (docs/api-contract.md). */
    private function health(App $app): array
    {
        $since = Carbon::now()->subHour();
        $appId = $app->app_id;

        $reqTotal = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->count();
        $req4xx5xx = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->where('status_code', '>=', 400)->count();
        $count5xx = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->where('status_code', '>=', 500)->count();
        $exceptions = ExceptionRecord::query()->forApp($appId)->where('created_at', '>=', $since)->count();
        $openIssues = Issue::query()->forApp($appId)->where('status', 'open')->count();

        $lastReport = RequestRecord::query()->forApp($appId)->max('created_at');
        $connected = $lastReport && Carbon::parse($lastReport)->gt(Carbon::now()->subHour());

        return [
            'app_id' => $appId,
            'name' => $app->name,
            'db_connection' => $app->db_connection,
            'error_rate' => $reqTotal > 0 ? round($req4xx5xx / $reqTotal * 100, 1) : 0.0,
            'count_5xx' => $count5xx,
            'exceptions' => $exceptions,
            'open_issues' => $openIssues,
            'monitoring' => $connected ? 'connected' : 'disconnected',
            'last_report_at' => $lastReport ? Carbon::parse($lastReport)->toIso8601String() : null,
            'alerts' => $openIssues,
        ];
    }
}
