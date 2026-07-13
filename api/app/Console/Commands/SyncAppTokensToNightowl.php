<?php

namespace App\Console\Commands;

use App\Events\AppTokenIssued;
use App\Models\App;
use Illuminate\Console\Command;

/**
 * One-off backfill for Apps created before nightowl_apps existed (or any App
 * whose row is missing/stale in it) — reuses AppTokenIssued/SyncAppTokenToNightowl
 * so there's no separate upsert logic to keep in sync. Run once after
 * `nightowl:migrate` creates the table; safe to re-run (upsert-only).
 */
class SyncAppTokensToNightowl extends Command
{
    protected $signature = 'nightowl:sync-app-tokens';

    protected $description = 'Backfill nightowl_apps for every App with an existing agent_token';

    public function handle(): int
    {
        App::query()->whereNotNull('agent_token')->each(
            fn (App $app) => AppTokenIssued::dispatch($app->app_id, $app->agent_token)
        );

        return self::SUCCESS;
    }
}
