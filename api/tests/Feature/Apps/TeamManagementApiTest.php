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
 * POST /api/orgs/{org}/teams, PUT /api/orgs/{org}/teams/{team},
 * DELETE /api/orgs/{org}/teams/{team} (App\Domains\Apps\Actions\StoreTeam,
 * UpdateTeam, DestroyTeam).
 */
class TeamManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function orgFor(User $user): Org
    {
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        return $org;
    }

    public function test_creates_a_team_under_an_org(): void
    {
        $user = User::factory()->create();
        $org = $this->orgFor($user);

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/teams", [
            'name' => 'New Team',
        ]);

        $response->assertCreated();
        $this->assertSame('New Team', $response->json('name'));
        $this->assertNotEmpty($response->json('uuid'));
        $this->assertDatabaseHas('teams', ['org_id' => $org->id, 'name' => 'New Team']);
    }

    public function test_store_team_requires_a_name(): void
    {
        $user = User::factory()->create();
        $org = $this->orgFor($user);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/teams", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_team_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/teams", ['name' => 'Team'])
            ->assertForbidden();
    }

    public function test_updates_a_team(): void
    {
        $user = User::factory()->create();
        $org = $this->orgFor($user);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/orgs/{$org->uuid}/teams/{$team->uuid}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('name'));
    }

    public function test_update_team_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Old Name']);

        $this->actingAs($user)->putJson("/api/orgs/{$org->uuid}/teams/{$team->uuid}", ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_deletes_a_team_with_no_apps(): void
    {
        $user = User::factory()->create();
        $org = $this->orgFor($user);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Deletable']);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/teams/{$team->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($team);
    }

    public function test_destroy_team_is_blocked_when_it_still_has_apps(): void
    {
        $user = User::factory()->create();
        $org = $this->orgFor($user);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Has Apps']);
        App::query()->create([
            'app_id' => 'app_'.Str::random(10),
            'team_id' => $team->id,
            'name' => 'An App',
        ]);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/teams/{$team->uuid}")
            ->assertStatus(422)
            ->assertJson(['message' => "Delete this team's apps first."]);

        $this->assertModelExists($team);
    }

    public function test_destroy_team_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $team = Team::query()->create(['org_id' => $org->id, 'name' => 'Team']);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/teams/{$team->uuid}")
            ->assertForbidden();
    }
}
