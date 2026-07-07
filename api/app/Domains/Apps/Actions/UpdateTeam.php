<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\TeamResource;
use App\Models\Org;
use App\Models\Team;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/orgs/{org}/teams/{team} — rename a team. Restricted to members
 * of the team's org.
 */
class UpdateTeam
{
    use AsAction;
    use AuthorizesOrgMembership;

    /** See UpdateOrg::authorize() docblock for why {team} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Team $team */
        $team = $request->route('team');

        return $this->authorizeOrgMember($team->org, $request->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * $org is unused in the body but must stay declared, type-hinted, and in
     * the same order the route resolves it ({org} before {team}): Laravel's
     * implicit route-model-binding is resolved by reflecting on handle()'s
     * own parameter types (not authorize()'s) — drop a type hint here and
     * that route segment is never substituted for a model at all, breaking
     * $request->route('team') in authorize() too, not just position order.
     */
    public function handle(Org $org, Team $team, ActionRequest $request)
    {
        $team->update($request->validated());

        return response()->json((new TeamResource($team))->resolve());
    }
}
