<?php

namespace App\Listeners;

use App\Events\AppTokenIssued;
use Illuminate\Support\Facades\DB;

/**
 * Upserts into `nightowl_apps` (owned by nightowl/agent, on the 'nightowl'
 * connection) so the agent daemon can resolve its own app_id from its
 * configured token at boot — see agent's Support\AppIdResolver.
 *
 * Deliberately synchronous, not ShouldQueue: this api has no queue worker
 * process anywhere (see Domains\Apps\Notifications\OrgInvitationReceived's
 * identical note), so a queued listener would silently never run in local
 * dev or a bare self-hosted deploy. Wrapped in try/catch so a transient
 * `nightowl` DB issue never fails app creation/token regeneration —
 * `nightowl:sync-app-tokens` is the recovery path for anything missed.
 */
class SyncAppTokenToNightowl
{
    public function handle(AppTokenIssued $event): void
    {
        try {
            DB::connection('nightowl')->table('nightowl_apps')->updateOrInsert(
                ['app_id' => $event->appId],
                ['token_hash' => hash('sha256', $event->plaintextToken), 'updated_at' => now()],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
