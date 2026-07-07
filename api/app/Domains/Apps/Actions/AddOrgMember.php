<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgMemberResource;
use App\Models\Org;
use App\Models\User;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/orgs/{org}/members — attach an *existing* user account to an
 * org by email. There is no email-invite infrastructure yet: this cannot
 * create an account for someone who hasn't registered, it can only grant
 * org access to one that already exists.
 *
 * Returns the single newly-added member (`(new OrgMemberResource($user))
 * ->resolve()`), not the org's whole member list — previously this
 * re-queried and re-serialized every member just to reflect one added row.
 */
class AddOrgMember
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
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }

    public function handle(Org $org, ActionRequest $request)
    {
        $user = User::query()->where('email', $request->validated('email'))->firstOrFail();

        $org->users()->syncWithoutDetaching([$user->id]);

        return response()->json((new OrgMemberResource($user))->resolve(), 201);
    }
}
