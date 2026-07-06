<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Org;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\RequestRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppController extends Controller
{
    /**
     * Apps grouped by team, each with a live 1h health summary — drives the
     * Org Dashboard cards (docs/pages/org-dashboard.md).
     */
    public function index(Request $request)
    {
        $org = Org::query()->firstOrFail();

        $teams = $org->teams()->with('apps')->get()->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'apps_count' => $team->apps->count(),
                'apps' => $team->apps->map(fn (App $app) => $this->health($app))->values(),
            ];
        });

        return response()->json([
            'org' => ['id' => $org->id, 'name' => $org->name, 'account_email' => $org->account_email],
            'teams' => $teams,
        ]);
    }

    public function show(App $app)
    {
        $app->loadMissing('team.org');

        return response()->json([
            'app_id' => $app->app_id,
            'name' => $app->name,
            'description' => $app->description,
            'db_connection' => $app->db_connection,
            'environments' => $app->environments ?? [],
            'team' => $app->team ? ['id' => $app->team->id, 'name' => $app->team->name] : null,
            'org' => $app->team?->org
                ? ['id' => $app->team->org->id, 'name' => $app->team->org->name, 'account_email' => $app->team->org->account_email]
                : null,
        ]);
    }

    /** One app's health card payload over a recent window. */
    private function health(App $app): array
    {
        $since = Carbon::now()->subDay();
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
