<?php

namespace Tests\Feature\Apps;

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

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

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
}
