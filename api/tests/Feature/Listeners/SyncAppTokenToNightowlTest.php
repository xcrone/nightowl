<?php

namespace Tests\Feature\Listeners;

use App\Events\AppTokenIssued;
use App\Listeners\SyncAppTokenToNightowl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Listeners\SyncAppTokenToNightowl — upserts nightowl_apps (on the
 * 'nightowl' connection) whenever AppTokenIssued fires from StoreApp or
 * Settings\Actions\RegenerateAppToken. See agent's Support\AppIdResolver for
 * the reader side.
 */
class SyncAppTokenToNightowlTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    public function test_handle_upserts_the_exact_row_shape(): void
    {
        (new SyncAppTokenToNightowl)->handle(new AppTokenIssued('app_under_test', 'nwt_plaintext'));

        $this->assertDatabaseHas('nightowl_apps', [
            'app_id' => 'app_under_test',
            'token_hash' => hash('sha256', 'nwt_plaintext'),
        ], 'nightowl');
    }

    public function test_hash_matches_the_agent_daemons_algorithm(): void
    {
        // agent's AppIdResolver::hashToken() must produce this exact digest
        // for the same plaintext — both sides are PHP, hash('sha256', ...)
        // is the shared primitive (see AppIdResolver's docblock).
        $known = 'nwt_'.str_repeat('a', 40);

        (new SyncAppTokenToNightowl)->handle(new AppTokenIssued('hash_check_app', $known));

        $row = DB::connection('nightowl')->table('nightowl_apps')->where('app_id', 'hash_check_app')->first();

        $this->assertSame(hash('sha256', $known), $row->token_hash);
        $this->assertSame(64, strlen($row->token_hash));
    }

    public function test_handle_replaces_not_duplicates_on_repeated_dispatch_for_same_app_id(): void
    {
        (new SyncAppTokenToNightowl)->handle(new AppTokenIssued('app_repeat', 'nwt_first'));
        (new SyncAppTokenToNightowl)->handle(new AppTokenIssued('app_repeat', 'nwt_second'));

        $this->assertSame(
            1,
            DB::connection('nightowl')->table('nightowl_apps')->where('app_id', 'app_repeat')->count(),
        );
        $this->assertDatabaseHas('nightowl_apps', [
            'app_id' => 'app_repeat',
            'token_hash' => hash('sha256', 'nwt_second'),
        ], 'nightowl');
    }
}
