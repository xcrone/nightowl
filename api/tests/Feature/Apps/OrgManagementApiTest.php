<?php

namespace Tests\Feature\Apps;

use App\Models\Org;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/orgs, PUT /api/orgs/{org}, DELETE /api/orgs/{org},
 * GET /api/orgs/{org}/members, POST /api/orgs/{org}/members,
 * DELETE /api/orgs/{org}/members/{user} (App\Domains\Apps\Actions\StoreOrg,
 * UpdateOrg, DestroyOrg, ListOrgMembers, AddOrgMember, RemoveOrgMember).
 */
class OrgManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_org_and_attaches_the_creator(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orgs', [
            'name' => 'New Org',
            'account_email' => 'new-org@example.com',
        ]);

        $response->assertCreated();
        $this->assertSame('New Org', $response->json('name'));
        $this->assertNotEmpty($response->json('uuid'));

        $org = Org::query()->where('name', 'New Org')->firstOrFail();
        $this->assertTrue($org->users->contains($user));
        $this->assertSame($user->id, $org->owner_id);
        $this->assertFalse($org->is_personal);
    }

    public function test_store_org_requires_name_and_account_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/orgs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'account_email']);
    }

    public function test_updates_an_org_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Old Name', 'account_email' => 'old@example.com']);
        $org->users()->attach($user);

        $response = $this->actingAs($user)->putJson("/api/orgs/{$org->uuid}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertSame('New Name', $response->json('name'));
        $this->assertSame('old@example.com', $response->json('account_email'));
    }

    public function test_update_org_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Old Name', 'account_email' => 'old@example.com']);

        $this->actingAs($user)->putJson("/api/orgs/{$org->uuid}", ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_deletes_an_org_with_no_teams(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Deletable', 'account_email' => 'del@example.com']);
        $org->users()->attach($user);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($org);
    }

    public function test_destroy_org_is_blocked_when_it_still_has_teams(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Has Teams', 'account_email' => 'teams@example.com']);
        $org->users()->attach($user);
        Team::query()->create(['org_id' => $org->id, 'name' => 'A Team']);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}")
            ->assertStatus(422)
            ->assertJson(['message' => "Delete this org's teams first."]);

        $this->assertModelExists($org);
    }

    public function test_destroy_org_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Not Mine', 'account_email' => 'notmine@example.com']);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}")
            ->assertForbidden();
    }

    public function test_adds_an_existing_user_as_an_org_member(): void
    {
        $user = User::factory()->create();
        $newMember = User::factory()->create(['email' => 'joiner@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/members", [
            'email' => 'joiner@example.com',
        ]);

        // AddOrgMember returns the single newly-added member, not the org's
        // whole member list.
        $response->assertCreated();
        $this->assertSame('joiner@example.com', $response->json('email'));
        $this->assertSame($newMember->uuid, $response->json('uuid'));
        $this->assertArrayNotHasKey('id', $response->json());
        $this->assertTrue($org->users()->where('users.id', $newMember->id)->exists());
    }

    public function test_add_member_rejects_an_email_with_no_account(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/members", [
            'email' => 'nobody@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_add_member_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(['email' => 'joiner@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/members", [
            'email' => 'joiner@example.com',
        ])->assertForbidden();
    }

    public function test_lists_an_orgs_members_for_an_authorized_member(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach([$user->id, $member->id]);

        $response = $this->actingAs($user)->getJson("/api/orgs/{$org->uuid}/members");

        $response->assertOk();
        $emails = array_column($response->json('data'), 'email');
        $this->assertEqualsCanonicalizing(
            [$user->email, 'member@example.com'],
            $emails
        );
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
    }

    public function test_list_members_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $this->actingAs($user)->getJson("/api/orgs/{$org->uuid}/members")
            ->assertForbidden();
    }

    public function test_removes_an_org_member(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach([$user->id, $member->id]);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/members/{$member->uuid}")
            ->assertNoContent();

        $this->assertFalse($org->users()->where('users.id', $member->id)->exists());
    }

    public function test_remove_member_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($member->id);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/members/{$member->uuid}")
            ->assertForbidden();
    }
}
