<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgInvitationResource;
use App\Models\OrgInvitation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/invitations — the pending invitations addressed to the
 * authenticated user's own email, across every org (no {org} route param
 * at all — this is a cross-org "my invitations" inbox).
 */
class ListReceivedInvitations
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(ActionRequest $request)
    {
        return OrgInvitationResource::collection(
            OrgInvitation::query()
                ->where('email', $request->user()->email)
                ->where('status', 'pending')
                ->with('org')
                ->get()
        );
    }
}
