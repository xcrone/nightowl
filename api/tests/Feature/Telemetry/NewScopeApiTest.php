<?php

namespace Tests\Feature\Telemetry;

use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\QueryRollup;
use App\Models\Telemetry\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NewScopeApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'nightowl_users', 'nightowl_alert_channels', 'nightowl_settings',
            'nightowl_query_rollups', 'nightowl_issues', 'nightowl_issue_activity',
            'nightowl_issue_comments',
        ] as $table) {
            DB::connection('nightowl')->table($table)->delete();
        }
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
        $ids = array_column($response->json('data'), 'user_id');
        $this->assertSame([$match->user_id], $ids);
    }

    public function test_query_rollups_aggregate_and_estimate_percentiles(): void
    {
        $admin = User::factory()->create();
        Setting::query()->delete();
        // Three separate per-minute buckets for the same query, matching how
        // real rollups accumulate — bucket_start must differ or they'd
        // collide on the composite primary key.
        QueryRollup::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['bucket_start' => now()->startOfMinute()->subMinutes($sequence->index)])
            ->create(['group_hash' => 'abc']);

        $response = $this->actingAs($admin)->getJson('/api/rollups/queries');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(3, $data[0]['call_count']);
        $this->assertArrayHasKey('p95', $data[0]);
    }
}
