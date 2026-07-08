<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgInvitationResource;
use App\Models\Org;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/orgs/{org}/invitations — the org's currently-pending invitations
 * (`OrgInvitationResource::collection`). Accepted/declined invitations are
 * historical, not actionable, so they're excluded here.
 */
class ListOrgInvitations
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
        return OrgInvitationResource::collection(
            $org->invitations()->where('status', 'pending')->get()
        );
    }
}
