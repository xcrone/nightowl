<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings/environments/token/template endpoints
 * (App\Domains\Settings\Actions\{ShowAppSettings,UpdateAppSetting,
 * DestroyAppSetting,UpdateAppEnvironment,RegenerateAppToken,ListAppTemplates,
 * SyncAppTemplate,ApplyAppTemplate}). Relocated from
 * tests/Feature/Apps/AppSettingsTest.php (Batch 5 of the controllers -> Actions
 * migration) — the AlertChannel half of that file now lives in
 * AlertChannelApiTest.php.
 */
class AppSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    public function test_settings_returns_environments_and_masked_token(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp('set_app');

        $this->actingAs($user)->getJson('/api/apps/set_app/settings')
            ->assertOk()
            ->assertJsonPath('app_id', 'set_app')
            ->assertJsonPath('environments.production', '#22c55e')
            ->assertJsonStructure(['agent_token', 'template']);
    }

    public function test_environment_color_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $this->actingAs($user)->putJson('/api/apps/set_app/environments/production', ['color' => '#ff0000'])
            ->assertOk()
            ->assertJsonPath('environments.production', '#ff0000');
    }

    public function test_token_regenerates_to_a_new_value(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp('set_app');
        $old = $app->agent_token;

        $new = $this->actingAs($user)->postJson('/api/apps/set_app/token/regenerate')
            ->assertOk()->json('agent_token');

        $this->assertNotSame($old, $new);
        $this->assertStringStartsWith('nwt_', $new);
    }

    public function test_template_sync_then_apply_copies_environment_colors(): void
    {
        $user = User::factory()->create();
        $source = $this->seedApp('src_app');
        $target = $this->seedApp('dst_app');

        // give the source a distinctive color, sync it, then apply to target.
        $this->actingAs($user)->putJson('/api/apps/src_app/environments/production', ['color' => '#123456']);
        $this->actingAs($user)->postJson('/api/apps/src_app/templates/sync', ['name' => 'E-commerce Setup'])->assertOk();

        $this->actingAs($user)->postJson('/api/apps/dst_app/templates/apply', ['from_app_id' => 'src_app'])
            ->assertOk()
            ->assertJsonPath('environments.production', '#123456');
    }

    public function test_templates_lists_the_apps_synced_templates(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $this->actingAs($user)->postJson('/api/apps/set_app/templates/sync', ['name' => 'My Template'])
            ->assertOk()
            ->assertJsonStructure(['uuid', 'name', 'payload', 'synced_at']);

        $response = $this->actingAs($user)->getJson('/api/apps/set_app/templates')->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('My Template', $data[0]['name']);
        $this->assertArrayHasKey('uuid', $data[0]);
    }

    public function test_updates_a_setting_scoped_to_its_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');
        $this->seedApp('other_app');

        $this->actingAs($user)->putJson('/api/apps/set_app/settings/slow_request_threshold_ms', [
            'value' => '1500',
        ])->assertOk();

        $this->assertDatabaseHas('nightowl_settings', [
            'app_id' => 'set_app', 'key' => 'slow_request_threshold_ms', 'value' => '1500',
        ], 'nightowl');

        $this->actingAs($user)->getJson('/api/apps/set_app/settings')
            ->assertOk()
            ->assertJsonPath('slow_request_threshold_ms', '1500');

        // a same-named key on another app must not leak in or collide.
        $this->actingAs($user)->putJson('/api/apps/other_app/settings/slow_request_threshold_ms', [
            'value' => '9000',
        ])->assertOk();

        $this->actingAs($user)->getJson('/api/apps/set_app/settings')
            ->assertOk()
            ->assertJsonPath('slow_request_threshold_ms', '1500');
    }

    public function test_updating_a_reserved_setting_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $this->actingAs($user)->putJson('/api/apps/set_app/settings/name', ['value' => 'Hijacked'])
            ->assertUnprocessable();

        $this->actingAs($user)->putJson('/api/apps/set_app/settings/app_id', ['value' => 'evil'])
            ->assertUnprocessable();
    }

    public function test_deletes_a_setting(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');
        $this->seedApp('other_app');

        $this->actingAs($user)->putJson('/api/apps/set_app/settings/slow_request_threshold_ms', [
            'value' => '1500',
        ])->assertOk();

        $this->actingAs($user)->putJson('/api/apps/other_app/settings/slow_request_threshold_ms', [
            'value' => '9000',
        ])->assertOk();

        $this->assertDatabaseHas('nightowl_settings', [
            'app_id' => 'set_app', 'key' => 'slow_request_threshold_ms', 'value' => '1500',
        ], 'nightowl');

        $this->actingAs($user)->deleteJson('/api/apps/set_app/settings/slow_request_threshold_ms')
            ->assertNoContent();

        $this->assertDatabaseMissing('nightowl_settings', [
            'app_id' => 'set_app', 'key' => 'slow_request_threshold_ms',
        ], 'nightowl');

        // a same-named key on another app must not be affected by the delete.
        $this->assertDatabaseHas('nightowl_settings', [
            'app_id' => 'other_app', 'key' => 'slow_request_threshold_ms', 'value' => '9000',
        ], 'nightowl');
    }

    public function test_deleting_a_reserved_setting_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $this->actingAs($user)->deleteJson('/api/apps/set_app/settings/name')
            ->assertUnprocessable();

        $this->actingAs($user)->deleteJson('/api/apps/set_app/settings/app_id')
            ->assertUnprocessable();
    }

    public function test_delete_is_idempotent_for_a_key_that_was_never_set(): void
    {
        $user = User::factory()->create();
        $this->seedApp('set_app');

        $this->actingAs($user)->deleteJson('/api/apps/set_app/settings/a_key_that_was_never_set')
            ->assertNoContent();
    }
}
