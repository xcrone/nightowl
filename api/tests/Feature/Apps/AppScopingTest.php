<?php

namespace Tests\Feature\Apps;

use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppScopingTest extends TestCase
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
            ->assertJsonStructure(['org' => ['id', 'name', 'account_email'], 'teams' => [['id', 'name', 'apps_count', 'apps']]]);
    }

    public function test_app_show_returns_the_app(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        $this->actingAs($user)
            ->getJson("/api/apps/{$app->app_id}")
            ->assertOk()
            ->assertJsonPath('app_id', $app->app_id)
            ->assertJsonPath('name', 'Test App');
    }

    public function test_unknown_app_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/apps/does-not-exist/requests')
            ->assertNotFound();
    }

    public function test_telemetry_is_scoped_to_its_app(): void
    {
        $user = User::factory()->create();
        $appA = $this->seedApp('app_a');
        $appB = $this->seedApp('app_b');

        $mine = RequestRecord::factory()->create(['app_id' => 'app_a']);
        RequestRecord::factory()->create(['app_id' => 'app_b']);

        $response = $this->actingAs($user)->getJson('/api/apps/app_a/requests');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$mine->id], $ids);
    }
}
