<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cross-repo ALTER (uuid-public-ids retrofit, Issues batch): same pattern as
 * 2026_07_06_130000_add_uuid_to_nightowl_alert_channels_table (Settings
 * batch) — `nightowl_issues`/`nightowl_issue_activity`/`nightowl_issue_comments`
 * are NOT owned by this repo, they're created by nightowl/agent's own
 * migrations (`agent/database/migrations/2024_01_01_000014_create_nightowl_issues_table.php`
 * and `..._000017_add_issues_comments_and_activity.php`), consumed here via
 * composer.json's local path repository and the `nightowl` Postgres
 * connection.
 *
 * Combined into one migration file (rather than 3) since all three tables
 * get identical treatment (nullable+unique uuid column, backfill loop) and
 * live in the same agent-owned schema — the migration plan describes this as
 * "same cross-repo ALTER" for all three, and Batch 5's precedent already
 * established the per-table pattern this just repeats 3x.
 *
 * `Schema::table` only, never `Schema::create` — purely additive, existing
 * row data/ownership stays entirely with agent/. The user signed off on this
 * exact cross-repo schema touch when approving the controllers -> Actions
 * migration plan.
 *
 * Guarded with hasColumn() checks (same reasoning as the alert_channels
 * migration): `nightowl` is a real, shared, persistent Postgres database
 * (local dev + CI both point at one that outlives any single test run),
 * while migration *history* is tracked in the app's default connection — so
 * a fresh testing sqlite sees this migration as "pending" again on every
 * run even once it's already been applied to the real `nightowl` database.
 * Idempotent guards keep repeated `php artisan migrate` runs safe either way.
 */
return new class extends Migration
{
    protected $connection = 'nightowl';

    /** All three tables get an identical nullable+unique uuid column + backfill. */
    private array $tables = [
        'nightowl_issues',
        'nightowl_issue_activity',
        'nightowl_issue_comments',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::connection($this->connection)->hasColumn($table, 'uuid')) {
                Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) {
                    // Nullable: no backfill guarantee at the DB level yet, so this
                    // can't be NOT NULL in the same migration (see api/CLAUDE.md's
                    // uuid-public-ids retrofit notes). Backfilled below for any
                    // existing rows so nothing ships with a null uuid in practice.
                    $blueprint->uuid('uuid')->nullable()->unique()->after('id');
                });
            }

            DB::connection($this->connection)->table($table)
                ->whereNull('uuid')->get(['id'])->each(function ($row) use ($table) {
                    DB::connection($this->connection)->table($table)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::connection($this->connection)->hasColumn($table, 'uuid')) {
                Schema::connection($this->connection)->table($table, function (Blueprint $blueprint) {
                    $blueprint->dropUnique(['uuid']);
                    $blueprint->dropColumn('uuid');
                });
            }
        }
    }
};
