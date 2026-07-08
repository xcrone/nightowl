<?php

namespace App\Domains\Apps\Actions;

use App\Models\Org;
use App\Models\OrgInvitation;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/orgs/{org}/invitations/{invitation} — cancel a pending
 * invitation. {invitation} binds by uuid (OrgInvitation::getRouteKeyName).
 */
class CancelOrgInvitation
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

    public function handle(Org $org, OrgInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation has already been responded to.'], 422);
        }

        $invitation->delete();

        return response()->noContent();
    }
}
