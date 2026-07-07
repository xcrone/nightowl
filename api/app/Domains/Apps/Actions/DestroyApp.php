<?php

namespace App\Domains\Apps\Actions;

use App\Models\App;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/apps/{app} — restricted to members of the app's team's org.
 * Telemetry rows in the separate `nightowl_*` tables are NOT cascade-deleted
 * here (out of scope — a different database, owned by `nightowl/agent`, not
 * this domain's schema).
 */
class DestroyApp
{
    use AsAction;
    use AuthorizesOrgMembership;

    /** See UpdateOrg::authorize() docblock for why {app} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var App $app */
        $app = $request->route('app');

        return $this->authorizeOrgMember($app->team->org, $request->user());
    }

    public function handle(App $app)
    {
        $app->delete();

        return response()->noContent();
    }
}
