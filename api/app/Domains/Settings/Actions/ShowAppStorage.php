<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/settings/storage — the Settings page "Storage" tab
 * (docs/pages/settings.md): the live on-disk footprint of the NightOwl
 * telemetry tables, read straight from Postgres system catalogs
 * (pg_total_relation_size includes indexes + TOAST — matching the docs'
 * "Total telemetry footprint: … including indexes" headline).
 *
 * Physical table sizes are shared across every app in this Postgres, so this
 * reports the whole-database telemetry footprint (`nightowl_*` tables); the
 * {app} binding just scopes the page/auth, exactly as the docs describe.
 */
class ShowAppStorage
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $rows = DB::connection('nightowl')->select(<<<'SQL'
            SELECT c.relname AS name,
                   pg_total_relation_size(c.oid) AS bytes,
                   GREATEST(c.reltuples, 0)::bigint AS rows
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relkind = 'r'
              AND n.nspname = 'public'
              AND c.relname LIKE 'nightowl\_%'
            ORDER BY bytes DESC, name ASC
        SQL);

        $tables = array_map(fn ($r) => [
            'name' => $r->name,
            'bytes' => (int) $r->bytes,
            'rows' => (int) $r->rows,
        ], $rows);

        return response()->json([
            'tables' => $tables,
            'total_bytes' => array_sum(array_column($tables, 'bytes')),
        ]);
    }
}
