<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/settings/storage — the Settings page "Storage" tab
 * (docs/pages/settings.md): "on-disk footprint of the app's NightOwl
 * telemetry tables" — i.e. scoped to {app}, not the whole shared Postgres.
 *
 * Physical table *sizes* (pg_total_relation_size, incl. indexes + TOAST) are
 * necessarily shared storage — Postgres doesn't segment a table's on-disk
 * pages per row value — but the *row counts*, and this app's share of each
 * table's bytes, are computed per app_id so a brand-new app with zero
 * telemetry reports zeros instead of the whole database's totals.
 *
 * - Tables that carry their own `app_id` column (every telemetry/issue/
 *   rollup/settings/alert-channel table — see
 *   agent/database/migrations/2024_01_01_000056_add_app_id_column.php and
 *   ..._000057_add_app_id_to_settings_and_alert_channels.php) are counted
 *   directly: `WHERE app_id = ?`.
 * - `nightowl_issue_activity`/`nightowl_issue_comments` have no `app_id` of
 *   their own (they only carry `issue_id`); they're counted via a join to
 *   `nightowl_issues.app_id`.
 * - `nightowl_reports` has no per-app relation at all (a whole-deployment
 *   snapshot, not owned by any single app) and is excluded from this report
 *   entirely rather than being misreported as either "0" or "everyone's".
 */
class ShowAppStorage
{
    use AsAction;

    /**
     * nightowl_* tables with no app_id column and no relation to one — a
     * whole-deployment resource, not meaningful in a per-app report.
     */
    private const GLOBAL_TABLES = ['nightowl_reports'];

    /**
     * nightowl_* tables with no app_id column of their own, scoped instead
     * via a join to their owning nightowl_issues row.
     *
     * @var array<string, string> table => its issue_id foreign key column
     */
    private const JOINED_VIA_ISSUE = [
        'nightowl_issue_activity' => 'issue_id',
        'nightowl_issue_comments' => 'issue_id',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $connection = DB::connection('nightowl');

        $rows = $connection->select(<<<'SQL'
            SELECT c.relname AS name,
                   pg_total_relation_size(c.oid) AS bytes
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relkind = 'r'
              AND n.nspname = 'public'
              AND c.relname LIKE 'nightowl\_%'
            ORDER BY bytes DESC, name ASC
        SQL);

        $appIdColumns = collect($connection->select(<<<'SQL'
            SELECT table_name FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name LIKE 'nightowl\_%'
              AND column_name = 'app_id'
        SQL))->pluck('table_name')->all();

        $tables = [];

        foreach ($rows as $r) {
            if (in_array($r->name, self::GLOBAL_TABLES, true)) {
                continue;
            }

            if (in_array($r->name, $appIdColumns, true)) {
                $appRows = (int) $connection->table($r->name)->where('app_id', $app->app_id)->count();
            } elseif (isset(self::JOINED_VIA_ISSUE[$r->name])) {
                $fk = self::JOINED_VIA_ISSUE[$r->name];
                $appRows = (int) $connection->table($r->name)
                    ->join('nightowl_issues', 'nightowl_issues.id', '=', "{$r->name}.{$fk}")
                    ->where('nightowl_issues.app_id', $app->app_id)
                    ->count();
            } else {
                // Unknown nightowl_* table not yet classified above — skip
                // rather than silently reporting whole-database totals.
                continue;
            }

            // pg_class.reltuples is only refreshed by ANALYZE/autovacuum and
            // is frequently stale or -1 (never analyzed) on these tables in
            // dev/test, so the proportional split below uses a real COUNT(*)
            // rather than that estimate.
            $totalRows = (int) $connection->table($r->name)->count();
            $totalBytes = (int) $r->bytes;
            $appBytes = $totalRows > 0
                ? (int) round($totalBytes * min($appRows / $totalRows, 1))
                : 0;

            $tables[] = [
                'name' => $r->name,
                'bytes' => $appBytes,
                'rows' => $appRows,
            ];
        }

        return response()->json([
            'tables' => $tables,
            'total_bytes' => array_sum(array_column($tables, 'bytes')),
        ]);
    }
}
