<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-app scoping.
 *
 * The dashboard's Org → Teams → Apps hierarchy needs every telemetry row to
 * carry which app it belongs to. Deployment-level tenancy (one agent + one
 * Postgres per app) remains the production model; this nullable `app_id`
 * lets a single shared database hold several simulated apps for the demo/dev
 * dashboard, scoped by `where app_id = ?` in api's controllers.
 *
 * Nullable + indexed so existing/live drain paths that don't stamp it keep
 * working; existing rows are backfilled to the default demo app so nothing
 * 404s in the dashboard.
 */
return new class extends Migration
{
    protected $connection = 'nightowl';

    /** Default app for backfill — the seeded "Northwind Web" app_id. */
    private const DEFAULT_APP_ID = '3FoNKDbo7D5S9MGhLx9qybejLCE';

    private array $tables = [
        // telemetry
        'nightowl_requests',
        'nightowl_queries',
        'nightowl_exceptions',
        'nightowl_commands',
        'nightowl_jobs',
        'nightowl_cache_events',
        'nightowl_mail',
        'nightowl_notifications',
        'nightowl_outgoing_requests',
        'nightowl_scheduled_tasks',
        'nightowl_logs',
        'nightowl_users',
        // issues
        'nightowl_issues',
        // rollups
        'nightowl_query_rollups',
        'nightowl_request_rollups',
        'nightowl_job_rollups',
        'nightowl_outgoing_request_rollups',
        'nightowl_cache_rollups',
    ];

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $connection = DB::connection($this->connection);

        foreach ($this->tables as $table) {
            if (! $schema->hasColumn($table, 'app_id')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->string('app_id')->nullable()->index();
                });
            }

            $connection->table($table)
                ->whereNull('app_id')
                ->update(['app_id' => self::DEFAULT_APP_ID]);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        foreach ($this->tables as $table) {
            $schema->table($table, function (Blueprint $t) {
                $t->dropIndex(['app_id']);
                $t->dropColumn('app_id');
            });
        }
    }
};
