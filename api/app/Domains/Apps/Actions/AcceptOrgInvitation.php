<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgInvitationResource;
use App\Models\OrgInvitation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/invitations/{invitation}/accept — the invitee joins the org.
 * Matched by email, not by any pre-existing relation: `authorize()`
 * restricts this to the currently-authenticated user whose own email
 * matches the invitation's `email`, whether they registered before or
 * after the invite was sent.
 */
class AcceptOrgInvitation
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

    public function handle(OrgInvitation $invitation, ActionRequest $request)
    {
        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation is no longer pending.'], 422);
        }

        $invitation->org->users()->syncWithoutDetaching([$request->user()->id]);

        $invitation->update(['status' => 'accepted', 'responded_at' => now()]);

        return response()->json((new OrgInvitationResource($invitation))->resolve(), 200);
    }
}
