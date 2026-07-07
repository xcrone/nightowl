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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps — apps grouped by team, each with a live 1h health summary
 * — drives the Org Dashboard cards (docs/pages/org-dashboard.md). A user
 * with no org membership at all gets their own true empty state
 * (`{org: null, teams: []}`) — never another user's org data.
 */
class ListApps
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(ActionRequest $request)
    {
        // ?org=<uuid> lets a user who belongs to more than one Org pick
        // which one to view (the org switcher on the dashboard). Two
        // distinct miss cases, handled differently:
        //  - the org exists but the user isn't a member: a deliberate
        //    request for someone else's org, so it's rejected (404) rather
        //    than silently substituting a different org and leaking that a
        //    valid-but-inaccessible org exists.
        //  - the org doesn't exist at all: the id is stale client-side
        //    state, not a bad-faith request — web/src/store/org.js's
        //    currentOrgUuid survives logins/logouts/DB resets in
        //    localStorage, so it can easily point at an org that's been
        //    deleted out from under it. Fall back to the user's own org
        //    exactly like the no-param branch below instead of 404ing: the
        //    store already re-syncs currentOrgUuid to whatever org actually
        //    comes back, so this self-heals on the next request.
        // If the user has no org membership at all, there is no org to fall
        // back to: return a clean empty state (`org: null, teams: []`)
        // rather than substituting a random/first org from the table, which
        // would leak another user's private org data.
        // Previously this always returned Org::query()->firstOrFail()
        // regardless of who was asking, which meant a newly registered user
        // (attached to their own, separate Org) was shown/scoped to
        // whatever Org happened to be first in the table instead of their
        // own — invisible teams/apps, and every management mutation 403ing
        // since they weren't a member of that other Org.
        $requestedOrg = null;
        if ($request->filled('org')) {
            $maybeOrg = Org::query()->where('uuid', $request->query('org'))->first();
            if ($maybeOrg) {
                abort_unless($request->user()->orgs()->whereKey($maybeOrg->id)->exists(), 404);
                $requestedOrg = $maybeOrg;
            }
        }

        $org = $requestedOrg ?? $request->user()->orgs()->first();

        if (! $org) {
            return response()->json(['org' => null, 'teams' => []]);
        }

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
            'org' => (new OrgResource($org->loadMissing('owner')))->resolve(),
            'teams' => $teams,
        ]);
    }

    /**
     * Minimum request volume in the health window before `error_rate` is
     * trustworthy enough to drive an alarming badge — below this, a single
     * stray request can swing the percentage arbitrarily (e.g. 1 request
     * that happens to 404 would otherwise read as "100% err"). The frontend
     * uses `request_count` to fall back to a neutral badge when this isn't
     * met (web/src/pages/OrgDashboard.vue).
     */
    private const MIN_SAMPLE_FOR_ERROR_BADGE = 20;

    /** One app's health card payload over the last 1h window (docs/api-contract.md). */
    private function health(App $app): array
    {
        $since = Carbon::now()->subHour();
        $appId = $app->app_id;

        $reqTotal = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->count();
        $count4xx = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->whereBetween('status_code', [400, 499])->count();
        $count5xx = RequestRecord::query()->forApp($appId)->where('created_at', '>=', $since)->where('status_code', '>=', 500)->count();
        $exceptions = ExceptionRecord::query()->forApp($appId)->where('created_at', '>=', $since)->count();
        $openIssues = Issue::query()->forApp($appId)->where('status', 'open')->count();

        $lastReport = RequestRecord::query()->forApp($appId)->max('created_at');
        $connected = $lastReport && Carbon::parse($lastReport)->gt(Carbon::now()->subHour());

        return [
            'app_id' => $appId,
            'name' => $app->name,
            'description' => $app->description,
            'db_connection' => $app->db_connection,
            // Server-error rate only (status >= 500) — ordinary client
            // errors (404/401/429/...) are NOT counted here, they're
            // reported separately via count_4xx. Previously this lumped
            // every status >= 400 together, so a single stray 404 in the
            // 1h window could read as "100% err" for an otherwise healthy
            // app (see the org-dashboard "% err" badge).
            'error_rate' => $reqTotal > 0 ? round($count5xx / $reqTotal * 100, 1) : 0.0,
            'count_4xx' => $count4xx,
            'count_5xx' => $count5xx,
            'request_count' => $reqTotal,
            'exceptions' => $exceptions,
            'open_issues' => $openIssues,
            'monitoring' => $connected ? 'connected' : 'disconnected',
            'last_report_at' => $lastReport ? Carbon::parse($lastReport)->toIso8601String() : null,
            'alerts' => $openIssues,
        ];
    }
}
