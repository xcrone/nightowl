<?php

namespace Tests\Feature\Settings;

use App\Models\Telemetry\AlertChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alert-channel endpoints (App\Domains\Settings\Actions\{ListAlertChannels,
 * StoreAlertChannel,UpdateAlertChannel,DestroyAlertChannel,
 * ToggleAlertChannel}). Relocated from tests/Feature/Apps/AppSettingsTest.php
 * (Batch 5 of the controllers -> Actions migration), which only covered
 * store/index/toggle — this file adds the update/destroy coverage the
 * migration plan explicitly calls out as missing.
 */
class AlertChannelApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    public function test_lists_and_creates_alert_channels_scoped_to_its_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');
        $this->seedApp('other_app');

        AlertChannel::create([
            'app_id' => 'other_app', 'name' => 'Other Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/other'],
        ]);

        $this->actingAs($user)
            ->postJson('/api/apps/set_app/alert-channels', [
                'name' => 'Slack', 'type' => 'slack', 'config' => ['webhook_url' => 'not-a-url'],
            ])
            ->assertUnprocessable();

        $created = $this->actingAs($user)
            ->postJson('/api/apps/set_app/alert-channels', [
                'name' => 'Slack', 'type' => 'slack', 'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'uuid', 'app_id', 'name', 'type', 'config', 'enabled'])
            ->json();

        $this->assertDatabaseHas('nightowl_alert_channels', [
            'app_id' => 'set_app', 'name' => 'Slack', 'type' => 'slack',
        ], 'nightowl');

        $response = $this->actingAs($user)->getJson('/api/apps/set_app/alert-channels');
        $response->assertOk()->assertJsonCount(1);
        $this->assertArrayHasKey('uuid', $response->json()[0]);

        $this->actingAs($user)->postJson("/api/apps/set_app/alert-channels/{$created['id']}/toggle")
            ->assertOk()->assertJsonPath('enabled', false);

        // an alert channel belonging to a different app is not reachable here.
        $other = AlertChannel::query()->where('app_id', 'other_app')->first();
        $this->actingAs($user)->postJson("/api/apps/set_app/alert-channels/{$other->id}/toggle")
            ->assertNotFound();
    }

    public function test_updates_an_alert_channel(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $channel = AlertChannel::create([
            'app_id' => 'set_app', 'name' => 'Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
        ]);

        $this->actingAs($user)
            ->putJson("/api/apps/set_app/alert-channels/{$channel->id}", [
                'name' => 'Renamed Slack', 'type' => 'slack',
                'config' => ['webhook_url' => 'https://hooks.slack.com/renamed'],
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed Slack')
            ->assertJsonPath('config.webhook_url', 'https://hooks.slack.com/renamed')
            ->assertJsonStructure(['uuid']);

        $this->assertDatabaseHas('nightowl_alert_channels', [
            'id' => $channel->id, 'name' => 'Renamed Slack',
        ], 'nightowl');
    }

    public function test_updating_an_alert_channel_validates_input(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $channel = AlertChannel::create([
            'app_id' => 'set_app', 'name' => 'Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
        ]);

        $this->actingAs($user)
            ->putJson("/api/apps/set_app/alert-channels/{$channel->id}", [
                'name' => 'Renamed Slack', 'type' => 'slack',
                'config' => ['webhook_url' => 'not-a-url'],
            ])
            ->assertUnprocessable();
    }

    public function test_destroys_an_alert_channel(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $channel = AlertChannel::create([
            'app_id' => 'set_app', 'name' => 'Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/x'],
        ]);

        $this->actingAs($user)->deleteJson("/api/apps/set_app/alert-channels/{$channel->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('nightowl_alert_channels', ['id' => $channel->id], 'nightowl');
    }

    public function test_cannot_update_or_destroy_an_alert_channel_belonging_to_another_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');
        $this->seedApp('other_app');

        $other = AlertChannel::create([
            'app_id' => 'other_app', 'name' => 'Other Slack', 'type' => 'slack', 'enabled' => true,
            'config' => ['webhook_url' => 'https://hooks.slack.com/other'],
        ]);

        $this->actingAs($user)
            ->putJson("/api/apps/set_app/alert-channels/{$other->id}", [
                'name' => 'Hijacked', 'type' => 'slack',
                'config' => ['webhook_url' => 'https://hooks.slack.com/hijacked'],
            ])
            ->assertNotFound();

        $this->actingAs($user)->deleteJson("/api/apps/set_app/alert-channels/{$other->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('nightowl_alert_channels', ['id' => $other->id, 'name' => 'Other Slack'], 'nightowl');
    }
}
