<?php

namespace Tests\Feature\Apps;

use App\Models\App;
use App\Models\Org;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/teams/{team}/apps, PUT /api/apps/{app}, DELETE /api/apps/{app}
 * (App\Domains\Apps\Actions\StoreApp, UpdateApp, DestroyApp).
 */
class AppManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    private function teamFor(User $user): Team
    {
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        return Team::query()->create(['org_id' => $org->id, 'name' => 'Team']);
    }

    public function test_creates_an_app_under_a_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamFor($user);

        $response = $this->actingAs($user)->postJson("/api/teams/{$team->uuid}/apps", [
            'name' => 'New App',
            'description' => 'A new app',
            'environments' => ['production' => '#22c55e'],
        ]);

        $response->assertCreated();
        $this->assertSame('New App', $response->json('name'));
        $this->assertNotEmpty($response->json('app_id'));
        $this->assertArrayNotHasKey('agent_token', $response->json());

        $app = App::query()->where('name', 'New App')->firstOrFail();
        $this->assertSame($team->id, $app->team_id);
        $this->assertNotEmpty($app->agent_token);
        $this->assertStringStartsWith('nwt_', $app->agent_token);

        // StoreApp dispatches AppTokenIssued -> SyncAppTokenToNightowl, which
        // upserts a lookup row so the agent daemon can resolve app_id from
        // its configured token at boot (see agent's Support\AppIdResolver).
        $this->assertDatabaseHas('nightowl_apps', [
            'app_id' => $app->app_id,
            'token_hash' => hash('sha256', $app->agent_token),
        ], 'nightowl');
    }

    public function test_store_app_requires_a_name(): void
    {
        $user = User::factory()->create();
        $team = $this->teamFor($user);

        $this->actingAs($user)->postJson("/api/teams/{$team->uuid}/apps", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_app_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Team']);

        $this->actingAs($user)->postJson("/api/teams/{$team->uuid}/apps", ['name' => 'App'])
            ->assertForbidden();
    }

    public function test_updates_an_app(): void
    {
        $user = User::factory()->create();
        $team = $this->teamFor($user);
        $app = App::query()->create([
            'app_id' => 'app_'.Str::random(10),
            'team_id' => $team->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->putJson("/api/apps/{$app->app_id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('name'));
        $this->assertSame($app->app_id, $response->json('app_id'));
    }

    public function test_update_app_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Team']);
        $app = App::query()->create([
            'app_id' => 'app_'.Str::random(10),
            'team_id' => $team->id,
            'name' => 'Old Name',
        ]);

        $this->actingAs($user)->putJson("/api/apps/{$app->app_id}", ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_deletes_an_app(): void
    {
        $user = User::factory()->create();
        $team = $this->teamFor($user);
        $app = App::query()->create([
            'app_id' => 'app_'.Str::random(10),
            'team_id' => $team->id,
            'name' => 'Deletable',
        ]);

        $this->actingAs($user)->deleteJson("/api/apps/{$app->app_id}")
            ->assertNoContent();

        $this->assertModelMissing($app);
    }

    public function test_destroy_app_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Team']);
        $app = App::query()->create([
            'app_id' => 'app_'.Str::random(10),
            'team_id' => $team->id,
            'name' => 'App',
        ]);

        $this->actingAs($user)->deleteJson("/api/apps/{$app->app_id}")
            ->assertForbidden();
    }
}
