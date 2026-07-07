<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\AppResource;
use App\Models\App;
use App\Models\Team;
use App\Support\AuthorizesOrgMembership;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/teams/{team}/apps — create an app under a team, minting a
 * fresh opaque `app_id` (used in every /dashboard/<app-id>/… URL and
 * stamped on every telemetry row) and `agent_token` (App::generateAgentToken(),
 * the same 'nwt_'-prefixed format Settings\Actions\RegenerateAppToken issues).
 * Restricted to members of the team's org. Returns the same flat shape
 * ShowApp::handle() builds, minus the team/org keys — the caller already
 * has that context.
 */
class StoreApp
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
            'description' => ['nullable', 'string'],
            'db_connection' => ['nullable', 'string'],
            'environments' => ['nullable', 'array'],
        ];
    }

    public function handle(Team $team, ActionRequest $request)
    {
        $app = $team->apps()->create([
            ...$request->validated(),
            'app_id' => $this->newAppId(),
            'agent_token' => App::generateAgentToken(),
        ]);

        return response()->json((new AppResource($app))->resolve(), 201);
    }

    private function newAppId(): string
    {
        do {
            $appId = Str::random(27);
        } while (App::query()->where('app_id', $appId)->exists());

        return $appId;
    }
}
