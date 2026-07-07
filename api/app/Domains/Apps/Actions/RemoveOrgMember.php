<?php

namespace App\Domains\Apps\Actions;

use App\Models\Org;
use App\Models\User;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/orgs/{org}/members/{user} — detach a member from an org.
 * {user} binds by uuid (User::getRouteKeyName) so the integer id never
 * reaches the SPA.
 */
class RemoveOrgMember
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

    public function handle(Org $org, User $user)
    {
        $org->users()->detach($user->id);

        return response()->noContent();
    }
}
