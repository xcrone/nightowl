<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Support\RefusesCascadeDelete;
use App\Models\Org;
use App\Support\AuthorizesOrgMembership;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/orgs/{org} — restricted to existing members. Refuses to
 * delete an org that still has teams, so one call can't silently cascade
 * away multiple teams/apps — delete the org's teams first.
 *
 * The children-exist check and the delete run inside one DB::transaction()
 * against a `lockForUpdate()`-locked re-fetch of the org row, so a
 * concurrent INSERT into teams (org_id references orgs.id) is blocked
 * until this transaction commits/rolls back — otherwise a team inserted
 * between the exists() check and delete() would be silently
 * cascade-deleted (teams.org_id is cascadeOnDelete()).
 */
class DestroyOrg
{
    use AsAction;
    use AuthorizesOrgMembership;
    use RefusesCascadeDelete;

    /** See UpdateOrg::authorize() docblock for why {org} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Org $org */
        $org = $request->route('org');

        return $this->authorizeOrgMember($org, $request->user());
    }

    public function handle(Org $org)
    {
        return DB::transaction(function () use ($org) {
            $org = Org::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();

            if ($refusal = $this->refuseIfHasChildren($org->teams(), "Delete this org's teams first.")) {
                return $refusal;
            }

            $org->delete();

            return response()->noContent();
        });
    }
}
