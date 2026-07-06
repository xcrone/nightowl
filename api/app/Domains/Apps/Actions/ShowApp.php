<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Domains\Apps\Resources\TeamResource;
use App\Models\App;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app} — single app + its team/org, for the App Dashboard
 * header (docs/pages/app-dashboard.md). {app} binds by the opaque app_id
 * (App::getRouteKeyName) — already uuid-public-ids compliant, no retrofit
 * needed for App itself.
 */
class ShowApp
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $app->loadMissing('team.org');

        return response()->json([
            'app_id' => $app->app_id,
            'name' => $app->name,
            'description' => $app->description,
            'db_connection' => $app->db_connection,
            'environments' => $app->environments ?? [],
            'team' => $app->team ? (new TeamResource($app->team))->resolve() : null,
            'org' => $app->team?->org ? (new OrgResource($app->team->org))->resolve() : null,
        ]);
    }
}
