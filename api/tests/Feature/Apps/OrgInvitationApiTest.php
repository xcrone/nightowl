<?php

namespace Tests\Feature\Apps;

use App\Domains\Apps\Notifications\OrgInvitationReceived;
use App\Models\Org;
use App\Models\OrgInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * GET/POST /api/orgs/{org}/invitations, DELETE /api/orgs/{org}/invitations/{invitation},
 * GET /api/invitations, POST /api/invitations/{invitation}/accept,
 * POST /api/invitations/{invitation}/decline (App\Domains\Apps\Actions\ListOrgInvitations,
 * InviteOrgMember, CancelOrgInvitation, ListReceivedInvitations, AcceptOrgInvitation,
 * DeclineOrgInvitation).
 */
class OrgInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invites_an_existing_user_to_an_org(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'invitee@example.com',
        ]);

        $response->assertCreated();

        $invitation = OrgInvitation::query()->where('org_id', $org->id)->where('email', 'invitee@example.com')->firstOrFail();
        $this->assertSame('pending', $invitation->status);
        $this->assertSame($user->id, $invitation->invited_by_user_id);
        $this->assertFalse($org->users()->where('users.id', $invitee->id)->exists());

        Notification::assertSentOnDemand(
            OrgInvitationReceived::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee@example.com'
        );
    }

    public function test_invites_a_not_yet_registered_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'nobody-yet@example.com',
        ]);

        $response->assertCreated();

        $invitation = OrgInvitation::query()->where('org_id', $org->id)->where('email', 'nobody-yet@example.com')->firstOrFail();
        $this->assertSame('pending', $invitation->status);

        Notification::assertSentOnDemand(
            OrgInvitationReceived::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'nobody-yet@example.com'
        );
    }

    public function test_invite_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'someone@example.com',
        ])->assertForbidden();
    }

    public function test_invite_rejects_a_user_whos_already_a_member(): void
    {
        $user = User::factory()->create();
        $existingMember = User::factory()->create(['email' => 'member@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach([$user->id, $existingMember->id]);

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'member@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('org_invitations', [
            'org_id' => $org->id,
            'email' => 'member@example.com',
        ]);
    }

    public function test_invite_rejects_a_duplicate_pending_invitation(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'dupe@example.com',
        ])->assertCreated();

        $response = $this->actingAs($user)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'dupe@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            1,
            OrgInvitation::query()->where('org_id', $org->id)->where('email', 'dupe@example.com')->count()
        );
    }

    public function test_lists_an_orgs_pending_invitations(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $pending = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'pending@example.com',
            'invited_by_user_id' => $user->id,
            'status' => 'pending',
        ]);
        OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'accepted@example.com',
            'invited_by_user_id' => $user->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/orgs/{$org->uuid}/invitations");

        $response->assertOk();
        $emails = array_column($response->json('data') ?? $response->json(), 'email');
        $this->assertSame(['pending@example.com'], $emails);
    }

    public function test_list_invitations_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $this->actingAs($user)->getJson("/api/orgs/{$org->uuid}/invitations")
            ->assertForbidden();
    }

    public function test_cancels_a_pending_invitation(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'cancel-me@example.com',
            'invited_by_user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/invitations/{$invitation->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($invitation);
    }

    public function test_cancel_invitation_is_forbidden_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'cancel-me@example.com',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/invitations/{$invitation->uuid}")
            ->assertForbidden();
    }

    public function test_cancel_fails_for_an_invitation_thats_not_pending(): void
    {
        $user = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($user);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'already-accepted@example.com',
            'invited_by_user_id' => $user->id,
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/orgs/{$org->uuid}/invitations/{$invitation->uuid}");

        $response->assertStatus(422);
        $this->assertModelExists($invitation);
    }

    public function test_lists_invitations_received_by_the_current_user(): void
    {
        $user = User::factory()->create(['email' => 'receiver@example.com']);
        $orgA = Org::query()->create(['name' => 'Org A', 'account_email' => 'a@example.com']);
        $orgB = Org::query()->create(['name' => 'Org B', 'account_email' => 'b@example.com']);

        $mine = OrgInvitation::query()->create([
            'org_id' => $orgA->id,
            'email' => 'receiver@example.com',
            'status' => 'pending',
        ]);
        OrgInvitation::query()->create([
            'org_id' => $orgB->id,
            'email' => 'someone-else@example.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/invitations');

        $response->assertOk();
        $uuids = array_column($response->json('data') ?? $response->json(), 'uuid');
        $this->assertSame([$mine->uuid], $uuids);
    }

    public function test_accepts_an_invitation_and_joins_the_org(): void
    {
        $user = User::factory()->create(['email' => 'accepter@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'accepter@example.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson("/api/invitations/{$invitation->uuid}/accept");

        $response->assertOk();

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
        $this->assertNotNull($invitation->responded_at);
        $this->assertTrue($org->users()->where('users.id', $user->id)->exists());
    }

    public function test_accept_is_forbidden_for_a_user_whose_email_doesnt_match(): void
    {
        $user = User::factory()->create(['email' => 'not-invited@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'invited@example.com',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson("/api/invitations/{$invitation->uuid}/accept")
            ->assertForbidden();

        $invitation->refresh();
        $this->assertSame('pending', $invitation->status);
    }

    public function test_accept_fails_for_an_invitation_thats_not_pending(): void
    {
        $user = User::factory()->create(['email' => 'accepter@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'accepter@example.com',
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        $this->actingAs($user)->postJson("/api/invitations/{$invitation->uuid}/accept")
            ->assertStatus(422);
    }

    public function test_declines_an_invitation(): void
    {
        $user = User::factory()->create(['email' => 'decliner@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'decliner@example.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson("/api/invitations/{$invitation->uuid}/decline");

        $response->assertOk();

        $invitation->refresh();
        $this->assertSame('declined', $invitation->status);
        $this->assertNotNull($invitation->responded_at);
        $this->assertFalse($org->users()->where('users.id', $user->id)->exists());
    }

    public function test_decline_is_forbidden_for_a_user_whose_email_doesnt_match(): void
    {
        $user = User::factory()->create(['email' => 'not-invited@example.com']);
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);

        $invitation = OrgInvitation::query()->create([
            'org_id' => $org->id,
            'email' => 'invited@example.com',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->postJson("/api/invitations/{$invitation->uuid}/decline")
            ->assertForbidden();

        $invitation->refresh();
        $this->assertSame('pending', $invitation->status);
    }

    public function test_accepts_an_invitation_after_registering_after_the_invite_was_sent(): void
    {
        Notification::fake();

        $inviter = User::factory()->create();
        $org = Org::query()->create(['name' => 'Org', 'account_email' => 'org@example.com']);
        $org->users()->attach($inviter);

        $this->actingAs($inviter)->postJson("/api/orgs/{$org->uuid}/invitations", [
            'email' => 'late-signup@example.com',
        ])->assertCreated();

        $invitation = OrgInvitation::query()->where('org_id', $org->id)->where('email', 'late-signup@example.com')->firstOrFail();

        // The invitee registers *after* the invite was sent.
        $newUser = User::factory()->create(['email' => 'late-signup@example.com']);

        $response = $this->actingAs($newUser)->postJson("/api/invitations/{$invitation->uuid}/accept");

        $response->assertOk();
        $this->assertTrue($org->users()->where('users.id', $newUser->id)->exists());
    }
}
