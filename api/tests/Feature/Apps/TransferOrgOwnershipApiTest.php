<?php

namespace Tests\Feature\Apps;

use App\Models\Org;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PUT /api/orgs/{org}/owner (App\Domains\Apps\Actions\TransferOrgOwnership). */
class TransferOrgOwnershipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_transfers_ownership_to_an_existing_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'newowner@example.com']);
        $org = Org::query()->create([
            'name' => 'Org',
            'account_email' => 'org@example.com',
            'owner_id' => $owner->id,
        ]);
        $org->users()->attach([$owner->id, $member->id]);

        $response = $this->actingAs($owner)->putJson("/api/orgs/{$org->uuid}/owner", [
            'email' => 'newowner@example.com',
        ]);

        $response->assertOk();
        $this->assertSame($member->uuid, $response->json('owner_uuid'));
        $this->assertSame($member->id, $org->refresh()->owner_id);
    }

    public function test_transfer_is_forbidden_for_a_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $org = Org::query()->create([
            'name' => 'Org',
            'account_email' => 'org@example.com',
            'owner_id' => $owner->id,
        ]);
        $org->users()->attach([$owner->id, $member->id]);

        // $member passes the plain membership check but is not the owner.
        $this->actingAs($member)->putJson("/api/orgs/{$org->uuid}/owner", [
            'email' => 'member@example.com',
        ])->assertForbidden();

        $this->assertSame($owner->id, $org->refresh()->owner_id);
    }

    public function test_transfer_to_a_non_members_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create(['email' => 'outsider@example.com']);
        $org = Org::query()->create([
            'name' => 'Org',
            'account_email' => 'org@example.com',
            'owner_id' => $owner->id,
        ]);
        $org->users()->attach($owner->id);

        $this->actingAs($owner)->putJson("/api/orgs/{$org->uuid}/owner", [
            'email' => 'outsider@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertSame($owner->id, $org->refresh()->owner_id);
    }

    public function test_transferring_a_personal_org_is_refused_even_by_its_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $org = Org::query()->create([
            'name' => 'Personal Org',
            'account_email' => 'org@example.com',
            'owner_id' => $owner->id,
            'is_personal' => true,
        ]);
        $org->users()->attach([$owner->id, $member->id]);

        $this->actingAs($owner)->putJson("/api/orgs/{$org->uuid}/owner", [
            'email' => 'member@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertSame($owner->id, $org->refresh()->owner_id);
    }

    public function test_transfer_to_a_nonexistent_email_is_a_validation_error(): void
    {
        $owner = User::factory()->create();
        $org = Org::query()->create([
            'name' => 'Org',
            'account_email' => 'org@example.com',
            'owner_id' => $owner->id,
        ]);
        $org->users()->attach($owner->id);

        $this->actingAs($owner)->putJson("/api/orgs/{$org->uuid}/owner", [
            'email' => 'nobody@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
