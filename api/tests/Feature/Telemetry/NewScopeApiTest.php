<?php

namespace Tests\Feature\Telemetry;

use App\Models\Telemetry\AlertChannel;
use App\Models\Telemetry\Issue;
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

    public function test_creates_and_validates_slack_alert_channel(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/alert-channels', [
                'name' => 'Slack', 'type' => 'slack', 'config' => ['webhook_url' => 'not-a-url'],
            ])
            ->assertUnprocessable();

        $response = $this->actingAs($admin)
            ->postJson('/api/alert-channels', [
                'name' => 'Slack', 'type' => 'slack', 'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('nightowl_alert_channels', ['name' => 'Slack', 'type' => 'slack'], 'nightowl');
    }

    public function test_toggles_alert_channel_enabled(): void
    {
        $admin = User::factory()->create();
        $channel = AlertChannel::create([
            'name' => 'Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
        ]);

        $response = $this->actingAs($admin)->postJson("/api/alert-channels/{$channel->id}/toggle");

        $response->assertOk()->assertJsonPath('enabled', false);
    }

    public function test_updates_a_setting(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->putJson('/api/settings/slow_request_threshold_ms', [
            'value' => '1500',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('nightowl_settings', ['key' => 'slow_request_threshold_ms', 'value' => '1500'], 'nightowl');

        $this->actingAs($admin)
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('slow_request_threshold_ms', '1500');
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

    public function test_issue_lifecycle_transitions_and_records_activity(): void
    {
        $admin = User::factory()->create();
        $issue = Issue::factory()->create(['status' => 'open']);

        $this->actingAs($admin)
            ->postJson("/api/issues/{$issue->id}/resolve")
            ->assertOk()
            ->assertJsonPath('status', 'resolved');

        $this->assertDatabaseHas('nightowl_issue_activity', [
            'issue_id' => $issue->id,
            'action' => 'status_changed',
            'old_value' => 'open',
            'new_value' => 'resolved',
        ], 'nightowl');

        $this->actingAs($admin)
            ->postJson("/api/issues/{$issue->id}/reopen")
            ->assertOk()
            ->assertJsonPath('status', 'open');
    }

    public function test_can_comment_on_an_issue(): void
    {
        $admin = User::factory()->create();
        $issue = Issue::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/issues/{$issue->id}/comments", ['body' => 'Investigating.'])
            ->assertCreated()
            ->assertJsonPath('body', 'Investigating.');

        $this->actingAs($admin)
            ->getJson("/api/issues/{$issue->id}/comments")
            ->assertOk()
            ->assertJsonCount(1);
    }
}
