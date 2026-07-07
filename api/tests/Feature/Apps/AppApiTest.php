<?php

namespace Tests\Feature\Apps;

use App\Models\Org;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps + GET /api/apps/{app} (App\Domains\Apps\Actions\ListApps,
 * ShowApp). Relocated from tests/Feature/Apps/AppScopingTest.php (Batch 4
 * of the controllers -> Actions migration) — that file's telemetry-scoping
 * test moved to tests/Feature/Telemetry/TelemetryApiTest.php in Batch 7
 * (TelemetryController -> App\Actions\Telemetry\*), which emptied and
 * removed the original file.
 */
class AppApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('nightowl')->table('nightowl_requests')->delete();
    }

    public function test_apps_endpoint_returns_teams_and_apps(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonStructure(['org' => ['id', 'uuid', 'name', 'account_email'], 'teams' => [['id', 'uuid', 'name', 'apps_count', 'apps']]]);
    }

    public function test_app_show_returns_the_app(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        $this->actingAs($user)
            ->getJson("/api/apps/{$app->app_id}")
            ->assertOk()
            ->assertJsonPath('app_id', $app->app_id)
            ->assertJsonPath('name', 'Test App')
            ->assertJsonStructure(['team' => ['id', 'uuid', 'name'], 'org' => ['id', 'uuid', 'name', 'account_email']]);
    }

    public function test_unknown_app_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/apps/does-not-exist/requests')
            ->assertNotFound();
    }

    public function test_apps_endpoint_returns_the_current_users_own_org_not_the_first_one_in_the_table(): void
    {
        // A pre-existing org (e.g. seeded demo data) that comes first in the
        // table, but which the acting user is NOT a member of.
        $otherOrg = Org::query()->create(['name' => 'Someone Else', 'account_email' => 'other@example.com']);
        Team::query()->create(['org_id' => $otherOrg->id, 'name' => 'Someone Else Team']);

        $user = User::factory()->create();
        $myOrg = Org::query()->create(['name' => 'My Org', 'account_email' => $user->email]);
        $myOrg->users()->attach($user->id);
        Team::query()->create(['org_id' => $myOrg->id, 'name' => 'My Team']);

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonPath('org.uuid', $myOrg->uuid)
            ->assertJsonPath('teams.0.name', 'My Team');
    }

    public function test_apps_endpoint_returns_the_org_selected_via_the_org_query_param(): void
    {
        $user = User::factory()->create();

        $orgA = Org::query()->create(['name' => 'Org A', 'account_email' => 'a@example.com']);
        $orgA->users()->attach($user->id);
        Team::query()->create(['org_id' => $orgA->id, 'name' => 'Team A']);

        $orgB = Org::query()->create(['name' => 'Org B', 'account_email' => 'b@example.com']);
        $orgB->users()->attach($user->id);
        Team::query()->create(['org_id' => $orgB->id, 'name' => 'Team B']);

        $this->actingAs($user)
            ->getJson("/api/apps?org={$orgB->uuid}")
            ->assertOk()
            ->assertJsonPath('org.uuid', $orgB->uuid)
            ->assertJsonPath('teams.0.name', 'Team B');
    }

    public function test_apps_endpoint_rejects_an_org_query_param_the_user_doesnt_belong_to(): void
    {
        $user = User::factory()->create();
        $myOrg = Org::query()->create(['name' => 'My Org', 'account_email' => 'me@example.com']);
        $myOrg->users()->attach($user->id);

        $otherOrg = Org::query()->create(['name' => 'Not Mine', 'account_email' => 'other@example.com']);

        $this->actingAs($user)
            ->getJson("/api/apps?org={$otherOrg->uuid}")
            ->assertNotFound();
    }
}
