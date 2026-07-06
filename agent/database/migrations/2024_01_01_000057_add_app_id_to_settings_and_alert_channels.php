<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `nightowl_settings` and `nightowl_alert_channels` were missed by
 * `..._000056_add_app_id_column` — the docs/api-contract.md nests both under
 * `/api/apps/{app}/…`, so they need the same per-app scoping as the other
 * telemetry tables.
 */
return new class extends Migration
{
    protected $connection = 'nightowl';

    /** Default app for backfill — the seeded "Northwind Web" app_id. */
    private const DEFAULT_APP_ID = '3FoNKDbo7D5S9MGhLx9qybejLCE';

    private array $tables = [
        'nightowl_settings',
        'nightowl_alert_channels',
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

        // `key` was globally unique — now that settings are per-app, the same
        // key must be reusable across apps, scoped by (app_id, key) instead.
        $schema->table('nightowl_settings', function (Blueprint $t) {
            $t->dropUnique(['key']);
            $t->unique(['app_id', 'key']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        $duplicates = DB::connection($this->connection)
            ->table('nightowl_settings')
            ->select('key')
            ->groupBy('key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('key');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot roll back: nightowl_settings has the same key used by multiple apps ('
                .$duplicates->implode(', ').'). Resolve the conflicts manually before rolling back.'
            );
        }

        $schema->table('nightowl_settings', function (Blueprint $t) {
            $t->dropUnique(['app_id', 'key']);
            $t->unique('key');
        });

        foreach ($this->tables as $table) {
            $schema->table($table, function (Blueprint $t) {
                $t->dropIndex(['app_id']);
                $t->dropColumn('app_id');
            });
        }
    }
};
