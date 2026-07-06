<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cross-repo ALTER (uuid-public-ids retrofit, Settings batch): unlike every
 * other retrofit migration in this set, `nightowl_alert_channels` is NOT
 * owned by this repo — it's created by nightowl/agent's own
 * `agent/database/migrations/2024_01_01_000018_create_nightowl_alert_channels_table.php`,
 * consumed here via composer.json's local path repository and the
 * `nightowl` Postgres connection (registered at runtime by
 * NightOwlAgentServiceProvider from config/nightowl.php's `database` block).
 *
 * This migration deliberately lives on the api side (not agent/) — the user
 * signed off on this exact cross-repo schema touch when approving the
 * controllers -> Actions migration plan. `Schema::table` only, never
 * `Schema::create`, so the table's origin/ownership and existing row data
 * stay entirely with agent/; this is purely additive (nullable column +
 * backfill), matching the same pattern as the other three uuid retrofit
 * migrations in this batch set (orgs/teams/templates), just pointed at the
 * `nightowl` connection via the migration's own $connection property
 * instead of the app's default connection.
 *
 * Guarded with hasColumn() checks (unlike the sibling orgs/teams/templates
 * retrofit migrations): those three target `sqlite`, the SAME connection
 * Laravel's migrator tracks "already ran" history against, so a fresh test
 * database never sees them as pending twice. `nightowl` is a real, shared,
 * persistent Postgres database (local dev and CI both point at one that
 * outlives any single test run/`:memory:` sqlite reset), while migration
 * *history* is still tracked in the app's default connection — so a fresh
 * testing sqlite (empty `migrations` table) sees this migration as
 * "pending" again on every run even once it has already been applied to the
 * real `nightowl` database. Idempotent guards keep repeated
 * `php artisan migrate` runs (dev or test) safe either way.
 */
return new class extends Migration
{
    protected $connection = 'nightowl';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('nightowl_alert_channels', 'uuid')) {
            Schema::connection($this->connection)->table('nightowl_alert_channels', function (Blueprint $table) {
                // Nullable: no backfill guarantee at the DB level yet, so this
                // can't be NOT NULL in the same migration (see api/CLAUDE.md's
                // uuid-public-ids retrofit notes). Backfilled below for any
                // existing rows so nothing ships with a null uuid in practice.
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
        }

        DB::connection($this->connection)->table('nightowl_alert_channels')
            ->whereNull('uuid')->get(['id'])->each(function ($channel) {
                DB::connection($this->connection)->table('nightowl_alert_channels')
                    ->where('id', $channel->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('nightowl_alert_channels', 'uuid')) {
            Schema::connection($this->connection)->table('nightowl_alert_channels', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
