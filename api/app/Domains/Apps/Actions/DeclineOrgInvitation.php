<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgInvitationResource;
use App\Models\OrgInvitation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/invitations/{invitation}/decline — the invitee turns down the
 * invitation. Same email-match authorization as AcceptOrgInvitation, but
 * never touches the org's membership pivot.
 */
class DeclineOrgInvitation
{
    use AsAction;

    /** See UpdateOrg::authorize() docblock for why {invitation} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var OrgInvitation|null $invitation */
        $invitation = $request->route('invitation');

        return $invitation !== null
            && $request->user() !== null
            && $invitation->email === $request->user()->email;
    }

    public function handle(OrgInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation is no longer pending.'], 422);
        }

        $invitation->update(['status' => 'declined', 'responded_at' => now()]);

        return response()->json((new OrgInvitationResource($invitation))->resolve(), 200);
    }
}
