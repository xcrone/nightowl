<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Notifications\OrgInvitationReceived;
use App\Domains\Apps\Resources\OrgInvitationResource;
use App\Models\Org;
use App\Models\OrgInvitation;
use App\Support\AuthorizesOrgMembership;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/orgs/{org}/invitations — invite someone to join an org by
 * email, replacing the old instant-attach AddOrgMember. Unlike
 * AddOrgMember, the email need not belong to a registered account yet
 * (`rules()` deliberately has no `exists:users,email`) — the invitation is
 * matched to a real user later, at accept time, by comparing `email`
 * against the logged-in user's own email (AcceptOrgInvitation::authorize()).
 *
 * Returns the newly-created invitation
 * (`(new OrgInvitationResource($invitation))->resolve()`), HTTP 201.
 */
class InviteOrgMember
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

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function handle(Org $org, ActionRequest $request)
    {
        $email = $request->validated('email');

        if ($org->users()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of this org.',
            ]);
        }

        if ($org->invitations()->where('email', $email)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already has a pending invitation to this org.',
            ]);
        }

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => $email,
            'invited_by_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        Notification::route('mail', $email)->notify(new OrgInvitationReceived($invitation));

        return response()->json((new OrgInvitationResource($invitation))->resolve(), 201);
    }
}
