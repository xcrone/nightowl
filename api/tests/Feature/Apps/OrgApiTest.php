<?php

namespace Tests\Feature\Apps;

use App\Models\Org;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/orgs (App\Domains\Apps\Actions\ListOrgs). Had zero test
 * coverage before this migration — covers both the normal case (user has
 * org membership) and the dev/demo fallback (falls back to every org when
 * membership wasn't seeded for the user).
 */
class OrgApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_orgs_the_user_belongs_to(): void
    {
        $user = User::factory()->create();

        $mine = Org::query()->create(['name' => 'Mine Org', 'account_email' => 'mine@example.com']);
        Org::query()->create(['name' => 'Someone Elses Org', 'account_email' => 'other@example.com']);

        $mine->users()->attach($user);

        $response = $this->actingAs($user)->getJson('/api/orgs');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($mine->uuid, $data[0]['uuid']);
        $this->assertSame('Mine Org', $data[0]['name']);
        $this->assertSame('mine@example.com', $data[0]['account_email']);
    }

    public function test_falls_back_to_every_org_when_the_user_has_no_membership(): void
    {
        $user = User::factory()->create();

        $orgA = Org::query()->create(['name' => 'Org A', 'account_email' => 'a@example.com']);
        $orgB = Org::query()->create(['name' => 'Org B', 'account_email' => 'b@example.com']);

        $response = $this->actingAs($user)->getJson('/api/orgs');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEqualsCanonicalizing(
            [$orgA->uuid, $orgB->uuid],
            array_column($data, 'uuid')
        );
    }
}
