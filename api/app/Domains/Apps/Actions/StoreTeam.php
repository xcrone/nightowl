<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\TeamResource;
use App\Models\Org;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/orgs/{org}/teams — create a team under an org. Restricted to
 * existing org members.
 */
class StoreTeam
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
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function handle(Org $org, ActionRequest $request)
    {
        $team = $org->teams()->create($request->validated());

        return response()->json((new TeamResource($team))->resolve(), 201);
    }
}
