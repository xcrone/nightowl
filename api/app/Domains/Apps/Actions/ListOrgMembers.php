<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgMemberResource;
use App\Models\Org;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/orgs/{org}/members — the org's existing members. Without this,
 * the only way the SPA could learn who belongs to an org was by building
 * up a list from AddOrgMember/RemoveOrgMember responses within one
 * session, which loses the list on reload.
 */
class ListOrgMembers
{
    use AsAction;
    use AuthorizesOrgMembership;

    /** See UpdateOrg::authorize() docblock for why {org} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Org $org */
        $org = $request->route('org');

        return $this->authorizeOrgMember($org, $request->user());
    }

    public function handle(Org $org)
    {
        return OrgMemberResource::collection(
            $org->users()->get(['users.id', 'users.uuid', 'users.name', 'users.email'])
        );
    }
}
