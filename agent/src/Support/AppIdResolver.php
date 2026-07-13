<?php

namespace NightOwl\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves `nightowl.agent.app_id` from the agent's configured token, once
 * per process, by looking it up in the `nightowl_apps` table (synced from
 * api's control plane whenever a dashboard app's token is issued/regenerated
 * — see api's App\Listeners\SyncAppTokenToNightowl). An explicit
 * NIGHTOWL_APP_ID always wins and short-circuits the lookup entirely, for
 * self-hosted use with no api/dashboard relationship.
 *
 * The resolved value is written back into config(), which IS the cache — the
 * existing consumers (AlertNotifier, HealthAlertNotifier, RecordWriter) all
 * already read config('nightowl.agent.app_id') ?? env('NIGHTOWL_APP_ID'), an
 * in-process array read with no further I/O.
 *
 * Must be called explicitly from every daemon entrypoint (AgentCommand,
 * DrainWorkerCommand) — NOT from the service provider's boot(), which also
 * runs on every request of the host Laravel app this package is installed
 * into, not just the two daemon processes.
 */
final class AppIdResolver
{
    public static function resolve(): void
    {
        $existing = config('nightowl.agent.app_id') ?? env('NIGHTOWL_APP_ID');
        if (is_string($existing) && $existing !== '') {
            return;
        }

        $token = (string) config('nightowl.agent.token', '');
        if ($token === '') {
            return;
        }

        try {
            $appId = DB::connection('nightowl')
                ->table('nightowl_apps')
                ->where('token_hash', self::hashToken($token))
                ->value('app_id');

            if (is_string($appId) && $appId !== '') {
                config(['nightowl.agent.app_id' => $appId]);
            }
        } catch (\Throwable $e) {
            // Same swallow-and-log shape as AgentCommand::warnOnSchemaDrift()
            // — must never block boot (pre-migration nightowl database, no
            // matching row yet, transient connection failure).
            error_log('[NightOwl Agent] app_id resolution skipped: '.$e->getMessage());
        }
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
