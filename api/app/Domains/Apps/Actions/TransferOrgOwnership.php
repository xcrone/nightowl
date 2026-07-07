<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Models\Org;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/orgs/{org}/owner — reassign an org's owner to another existing
 * member, by email. Deliberately does **not** reuse
 * `App\Support\AuthorizesOrgMembership` — any member may rename/delete the
 * org or manage its membership, but only the *current owner* may hand
 * ownership to someone else.
 */
class TransferOrgOwnership
{
    use AsAction;

    /** See UpdateOrg::authorize() docblock for why {org} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Org $org */
        $org = $request->route('org');

        return $org->owner_id !== null && $org->owner_id === $request->user()?->id;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    public function handle(Org $org, ActionRequest $request)
    {
        // A personal org's owner is fixed for life (it's the account that
        // founded it at registration) — never transferable, even by the
        // owner themselves.
        if ($org->is_personal) {
            throw ValidationException::withMessages([
                'email' => ["This organization's ownership can't be transferred."],
            ]);
        }

        $user = User::query()->where('email', $request->validated('email'))->firstOrFail();

        if (! $org->users()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Must be an existing member of this organization.'],
            ]);
        }

        $org->update(['owner_id' => $user->id]);

        return response()->json((new OrgResource($org->loadMissing('owner')))->resolve());
    }
}
