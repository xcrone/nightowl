<?php

namespace Tests\Feature\Users;

use App\Models\Telemetry\NightowlUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Legacy, top-level (NOT app-scoped) /api/users + /api/users/{userId}
 * (App\Domains\Users\Actions\ListNightowlUsers, ShowNightowlUser).
 */
class NightowlUserApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('nightowl')->table('nightowl_users')->delete();
    }

    public function test_lists_nightowl_users(): void
    {
        $admin = User::factory()->create();
        NightowlUser::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_users_search_matches_name_or_email(): void
    {
        $admin = User::factory()->create();
        NightowlUser::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $match = NightowlUser::factory()->create(['name' => 'Bob', 'email' => 'bob@acme.test']);

        $response = $this->actingAs($admin)->getJson('/api/users?q=acme');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$match->user_id], $ids);
    }

    public function test_shows_a_single_nightowl_user(): void
    {
        $admin = User::factory()->create();
        $user = NightowlUser::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);

        $response = $this->actingAs($admin)->getJson("/api/users/{$user->user_id}");

        $response->assertOk()
            ->assertJsonPath('id', $user->user_id)
            ->assertJsonPath('name', 'Carol')
            ->assertJsonPath('email', 'carol@example.com')
            ->assertJsonStructure(['id', 'name', 'email', 'last_seen'])
            ->assertJsonMissingPath('user_id');
    }

    public function test_shows_404_for_an_unknown_user_id(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/users/does-not-exist')->assertNotFound();
    }
}
