<?php

namespace Tests\Feature\Users;

use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App-scoped GET /api/apps/{app}/users/{userId} (App\Domains\Users\Actions\ShowUserDetail).
 * Relocated from tests/Feature/Apps/IssueUserDetailTest.php (Batch 3 of the
 * controllers -> Actions migration).
 */
class UserDetailApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_users'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('det_app');
    }

    public function test_user_detail_aggregates_requests_and_routes(): void
    {
        $user = User::factory()->create();
        NightowlUser::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'email' => 'nine@example.com']);
        RequestRecord::factory()->count(2)->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'route_path' => '/api/orders', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'route_path' => '/api/orders', 'status_code' => 500]);
        // another user's traffic must not leak in.
        RequestRecord::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_other', 'route_path' => '/x']);

        $response = $this->actingAs($user)->getJson('/api/apps/det_app/users/user_9');

        $response->assertOk()
            ->assertJsonPath('user.id', 'user_9')
            ->assertJsonPath('user.email', 'nine@example.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'last_seen']])
            ->assertJsonPath('requests.total', 3)
            ->assertJsonPath('requests.c5xx', 1)
            ->assertJsonPath('top_routes.0.route_path', '/api/orders')
            ->assertJsonPath('top_routes.0.count', 3);
    }
}
