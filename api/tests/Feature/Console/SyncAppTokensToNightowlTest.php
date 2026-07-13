<?php

namespace Tests\Feature\Console;

use App\Events\AppTokenIssued;
use App\Models\App;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * `nightowl:sync-app-tokens` — one-off backfill of nightowl_apps for Apps
 * created before that table existed. Reuses AppTokenIssued/SyncAppTokenToNightowl,
 * so this only needs to assert the command dispatches the right events.
 */
class SyncAppTokensToNightowlTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    public function test_dispatches_one_event_per_app_with_a_token(): void
    {
        Event::fake([AppTokenIssued::class]);

        $withToken = $this->seedApp('with_token');
        $skipped = $this->seedApp('without_token');
        $skipped->update(['agent_token' => null]);

        $this->artisan('nightowl:sync-app-tokens')->assertSuccessful();

        Event::assertDispatched(
            AppTokenIssued::class,
            fn (AppTokenIssued $event) => $event->appId === $withToken->app_id
                && $event->plaintextToken === $withToken->agent_token,
        );
        Event::assertNotDispatched(
            AppTokenIssued::class,
            fn (AppTokenIssued $event) => $event->appId === $skipped->app_id,
        );
    }

    public function test_backfill_actually_syncs_nightowl_apps_end_to_end(): void
    {
        $app = $this->seedApp('backfill_app');
        // Simulate a pre-existing App whose nightowl_apps row was never synced
        // (e.g. created before this feature shipped).

        $this->artisan('nightowl:sync-app-tokens')->assertSuccessful();

        $this->assertDatabaseHas('nightowl_apps', [
            'app_id' => 'backfill_app',
            'token_hash' => hash('sha256', $app->agent_token),
        ], 'nightowl');
    }
}
