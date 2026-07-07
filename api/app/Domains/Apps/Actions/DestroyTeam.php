<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Support\RefusesCascadeDelete;
use App\Models\Org;
use App\Models\Team;
use App\Support\AuthorizesOrgMembership;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/orgs/{org}/teams/{team} — restricted to members of the
 * team's org. Refuses to delete a team that still has apps, so one call
 * can't silently cascade away multiple apps — delete the team's apps first.
 *
 * Same lockForUpdate()-inside-DB::transaction() shape as DestroyOrg, for
 * the same reason: without it, an app inserted between the exists() check
 * and delete() would be silently cascade-deleted (apps.team_id is
 * cascadeOnDelete()).
 */
class DestroyTeam
{
    use AsAction;
    use AuthorizesOrgMembership;
    use RefusesCascadeDelete;

    /** See UpdateOrg::authorize() docblock for why {team} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Team $team */
        $team = $request->route('team');

        return $this->authorizeOrgMember($team->org, $request->user());
    }

    /** See UpdateTeam::handle()'s docblock for why $org must stay type-hinted even though unused. */
    public function handle(Org $org, Team $team)
    {
        return DB::transaction(function () use ($team) {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            if ($refusal = $this->refuseIfHasChildren($team->apps(), "Delete this team's apps first.")) {
                return $refusal;
            }

            $team->delete();

            return response()->noContent();
        });
    }
}
