<?php

namespace Tests\Feature\Apps;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    public function test_settings_returns_environments_and_masked_token(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp('set_app');

        $this->actingAs($user)->getJson('/api/apps/set_app/settings')
            ->assertOk()
            ->assertJsonPath('app_id', 'set_app')
            ->assertJsonPath('environments.production', '#22c55e')
            ->assertJsonStructure(['agent_token_masked', 'template']);
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
}
