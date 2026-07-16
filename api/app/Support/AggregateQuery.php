<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared query-building helpers for the generic aggregate engine
 * (`App\Actions\Aggregates\IndexAggregate`), driven by `config/aggregates.php`.
 * Ported verbatim from the pre-Actions
 * `App\Http\Controllers\Api\AggregateController` private methods so the
 * Action composes one implementation instead of duplicating it. Operates on
 * a plain `DB::table()` query builder, not Eloquent — these rows are raw
 * GROUP BY aggregates, not models.
 */
class AggregateQuery
{
    /** Build the SELECT list (grouped adds the group_by/label/extra columns). */
    public static function selectExpressions(array $config, bool $grouped): array
    {
        $expr = ['COUNT(*) as total'];

        if ($grouped) {
            foreach ($config['group_by'] as $col) {
                $expr[] = $col;
            }
            if (($label = $config['label'] ?? null) && ! in_array($label, $config['group_by'], true)) {
                $expr[] = "MAX({$label}) as {$label}";
            }
            // 'extra' may be a plain list (col) or a map (alias => col) so the
            // emitted row field can be renamed (e.g. execution_source -> source).
            // The *latest* occurrence in the group wins (ordered by created_at,
            // then id to break ties) — matching the representative-row pattern
            // ShowAggregateDetail::meta() and Exceptions\ShowExceptionGroup
            // already use for a group's carried-through fields. MAX() used to be
            // used here, but MAX() is an accident of SQL semantics, not a
            // meaningful pick: MAX(connection_type) silently reports "write" for
            // a group that's mostly reads (lexicographic 'write' > 'read'), and
            // the equivalent MAX() on a boolean reports "true" (handled) for a
            // group that's mostly unhandled (any-true wins) — see queries.rw and
            // exceptions.representative_bool.
            foreach ($config['extra'] ?? [] as $alias => $col) {
                $as = is_int($alias) ? $col : $alias;
                $expr[] = self::latestValueSql($col, $as);
            }
            // Boolean flag columns that also feed a same-named 'count_buckets'
            // SUM (e.g. exceptions' handled/unhandled) can't reuse that alias
            // for a representative value without colliding in the SELECT list,
            // so they're computed under an internal alias here and promoted
            // (overwriting the SUM's alias) by normalizeRow().
            foreach ($config['representative_bool'] ?? [] as $alias => $col) {
                $as = is_int($alias) ? $col : $alias;
                $expr[] = '(array_agg((CASE WHEN '.$col.' THEN 1 ELSE 0 END) order by created_at desc, id desc))[1]::int as '.self::representativeAlias($as);
            }
            foreach ($config['collect_distinct'] ?? [] as $alias => $col) {
                $expr[] = "string_agg(DISTINCT {$col}::text, ',') as {$alias}";
            }
            foreach ($config['distinct_count'] ?? [] as $alias => $col) {
                $expr[] = "COUNT(DISTINCT {$col}) as {$alias}";
            }
            if ($alias = self::lastAlias($config)) {
                $expr[] = "MAX({$config['last']}) as {$alias}";
            }
        }

        if ($config['duration'] ?? false) {
            $expr[] = 'ROUND(AVG(duration))::bigint as avg';
            $expr[] = 'percentile_cont(0.95) within group (order by duration)::bigint as p95';
            $expr[] = 'MIN(duration) as min';
            $expr[] = 'MAX(duration) as max';
        }

        foreach ($config['count_buckets'] ?? [] as $alias => $conditions) {
            $expr[] = 'SUM(CASE WHEN '.self::conditionsSql($conditions)." THEN 1 ELSE 0 END) as {$alias}";
        }

        return array_map(fn ($e) => DB::raw($e), $expr);
    }

    /**
     * SQL for "this column's value on the group's latest row" — an
     * array_agg(...ORDER BY...)[1], since Postgres has no first_value()
     * aggregate (only the windowed one, which needs a subquery to use
     * alongside a plain GROUP BY). Ties on created_at break on id, mirroring
     * ShowAggregateDetail/ShowExceptionGroup's `latest('created_at')->latest('id')`.
     */
    private static function latestValueSql(string $col, string $as): string
    {
        return "(array_agg({$col} order by created_at desc, id desc))[1] as {$as}";
    }

    /**
     * The row field a config's MAX(<last>) lands under: the default is
     * last_<col>, and 'last_alias' overrides it so the field matches the
     * contract (e.g. last_triggered / last_sent / last_seen). Shared by
     * selectExpressions() (which emits the alias) and normalizeRow() (which
     * normalizes its value) so the two can never drift apart.
     */
    private static function lastAlias(array $config): ?string
    {
        if (! ($lastCol = $config['last'] ?? null)) {
            return null;
        }

        return $config['last_alias'] ?? "last_{$lastCol}";
    }

    /**
     * Serialize an aggregate's MAX(<timestamp>) to ISO 8601 with an explicit zone.
     *
     * Aggregate rows come off the raw query builder, so PDO hands back the naive
     * `Y-m-d H:i:s` string of a `timestamp without time zone` column verbatim —
     * unlike every Eloquent path, where Carbon casting + serializeDate() already
     * emit ISO. The SPA does `new Date(value)` on these, and V8 parses a
     * zone-less, space-separated string as LOCAL time, so an unstamped value
     * silently skews by the viewer's UTC offset.
     *
     * Postgres runs UTC and the column carries no zone, so a naive value IS UTC:
     * it's parsed with UTC passed explicitly rather than letting Carbon fall back
     * to PHP's ambient timezone (or config('app.timezone')). A value that already
     * carries a zone — or is a DateTime — keeps its own instant, so this is safe
     * to apply twice. Null (MAX() over an empty group) stays null.
     */
    public static function toIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return Carbon::parse($value, 'UTC')->toIso8601String();
    }

    /** Internal SELECT alias for a 'representative_bool' column pre-promotion (see normalizeRow). */
    private static function representativeAlias(string $as): string
    {
        return "__repr_{$as}";
    }

    /**
     * P50/P95/P99 (+ AVG) duration breakdown for the aggregate-detail page's
     * percentile toggle and its "≥ AVG / ≥ P50 / ≥ P95 / ≥ P99" filter chips.
     * A superset of the list page's single-p95 duration panel.
     */
    public static function percentileExpressions(): array
    {
        return array_map(fn ($e) => DB::raw($e), [
            'ROUND(AVG(duration))::bigint as avg',
            'percentile_cont(0.5) within group (order by duration)::bigint as p50',
            'percentile_cont(0.95) within group (order by duration)::bigint as p95',
            'percentile_cont(0.99) within group (order by duration)::bigint as p99',
        ]);
    }

    /**
     * Constrain a query (Eloquent or DB::table) to one aggregate key. The
     * primary group column is matched against the decoded path key; any
     * further group columns (only scheduled-tasks' `expression`) are matched
     * against a same-named query param when present, so a composite key can be
     * disambiguated without a second path segment.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder|Builder  $query
     */
    public static function applyKeyScope($query, array $config, string $key, Request $request): void
    {
        $groupBy = $config['group_by'];
        $query->where($groupBy[0], $key);

        foreach (array_slice($groupBy, 1) as $col) {
            if ($request->filled($col)) {
                $query->where($col, $request->query($col));
            }
        }
    }

    /**
     * Apply the aggregate-detail table's resource-specific outcome chip
     * (?outcome=<count_bucket alias>) by replaying that bucket's config-defined
     * conditions as where() clauses — e.g. requests ?outcome=c5xx ->
     * status_code >= 500, jobs ?outcome=failed -> status = 'failed'.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder|Builder  $query
     */
    public static function applyOutcome($query, array $config, Request $request): void
    {
        $outcome = $request->query('outcome');
        $buckets = $config['count_buckets'] ?? [];

        if ($outcome === null || ! isset($buckets[$outcome])) {
            return;
        }

        foreach ($buckets[$outcome] as [$col, $op, $val]) {
            $query->where($col, $op, $val);
        }
    }

    /** Render config-derived (trusted) [col, op, val] conditions to SQL. */
    public static function conditionsSql(array $conditions): string
    {
        return collect($conditions)->map(function ($c) {
            [$col, $op, $val] = $c;
            $lit = is_bool($val) ? ($val ? 'true' : 'false')
                : (is_int($val) || is_float($val) ? $val : "'".addslashes((string) $val)."'");

            return "{$col} {$op} {$lit}";
        })->implode(' AND ');
    }

    public static function sort(array $config, Request $request): array
    {
        $sort = (string) $request->query('sort', $config['default_sort'] ?? '-total');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');

        if (! in_array($col, $config['sortable'] ?? [], true)) {
            $col = ltrim($config['default_sort'] ?? '-total', '-');
            $dir = 'desc';
        }

        // 'count'/'calls' are UI-friendly aliases for the same COUNT(*)/total.
        if (in_array($col, ['count', 'calls'], true)) {
            $col = 'total';
        }
        if ($col === 'hit_rate') {
            $col = 'total';
        }

        return [$col, $dir];
    }

    /** Post-process a DB row: cast, split channels, add count/calls aliases. */
    public static function normalizeRow(array $row, array $config): array
    {
        if (isset($row['total'])) {
            $row['total'] = (int) $row['total'];
            $row['count'] = $row['total'];
            $row['calls'] = $row['total'];
        }
        // 'last_duration' (jobs only, via 'extra') is a duration value like
        // avg/p95/min/max and gets the same int cast for API consistency.
        foreach (['avg', 'p95', 'min', 'max', 'last_duration'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        foreach (array_keys($config['count_buckets'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = (int) $row[$alias];
            }
        }
        // Promote each 'representative_bool' internal column over its
        // same-named 'count_buckets' SUM (e.g. exceptions' handled/unhandled
        // counts) — the group's badge/flag needs the latest occurrence's
        // actual value, not "was any occurrence in the group true".
        foreach ($config['representative_bool'] ?? [] as $alias => $col) {
            $as = is_int($alias) ? $col : $alias;
            $key = self::representativeAlias($as);
            if (array_key_exists($key, $row)) {
                $row[$as] = (bool) $row[$key];
                unset($row[$key]);
            }
        }
        foreach (array_keys($config['collect_distinct'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = $row[$alias] ? explode(',', $row[$alias]) : [];
            }
        }
        // The MAX(<last>) alias arrives as a naive `Y-m-d H:i:s` string (raw
        // query builder, zone-less column) — stamp it as the UTC instant it is.
        // Only touched when present: the ungrouped panels row never selects it.
        if (($lastAlias = self::lastAlias($config)) && array_key_exists($lastAlias, $row)) {
            $row[$lastAlias] = self::toIso8601($row[$lastAlias]);
        }
        // Cache hit rate convenience.
        if (isset($row['hits'], $row['misses'])) {
            $reads = $row['hits'] + $row['misses'];
            $row['hit_rate'] = $reads > 0 ? round($row['hits'] / $reads * 100, 2) : 0.0;
        }
        // Humanize a raw cron expression into a `schedule` cadence label.
        if (($cronCol = $config['cron'] ?? null) && array_key_exists($cronCol, $row)) {
            $row['schedule'] = self::humanizeCron((string) $row[$cronCol]);
            // Keep the raw cron when it's a group_by key: detail drill-down
            // (ShowAggregateDetail) + the SPA rowLink need it to disambiguate
            // rows that share a command but differ by cadence.
            if ($cronCol !== 'schedule' && ! in_array($cronCol, $config['group_by'] ?? [], true)) {
                unset($row[$cronCol]);
            }
        }

        return $row;
    }

    /**
     * Reshape a flat, normalized aggregate row into the nested per-stat-panel
     * sub-objects the frontend's panels(p) builders (web/src/aggregateConfig.js)
     * and docs/api-contract.md expect (e.g. requests -> { requests:{…}, duration:{…} }).
     * Driven by the config's declarative `panels` spec; each entry maps an output
     * key to a flat field name (int key => same name on both sides). Missing flat
     * fields default to 0. No `panels` spec falls back to the flat row.
     */
    public static function shapePanels(array $flatRow, array $config): array
    {
        $spec = $config['panels'] ?? null;
        if ($spec === null) {
            return $flatRow;
        }

        $out = [];
        foreach ($spec as $group => $fields) {
            $sub = [];
            foreach ($fields as $outKey => $flatKey) {
                $outKey = is_int($outKey) ? $flatKey : $outKey;
                $sub[$outKey] = $flatRow[$flatKey] ?? 0;
            }
            $out[$group] = $sub;
        }

        return $out;
    }

    /**
     * Best-effort human-readable cadence for the common cron expressions the
     * scheduler emits; unknown expressions fall back to the raw string.
     */
    public static function humanizeCron(string $expr): string
    {
        $expr = trim(preg_replace('/\s+/', ' ', $expr));

        $known = [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/10 * * * *' => 'Every 10 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Hourly',
            '0 0 * * *' => 'Daily',
            '0 0 * * 0' => 'Weekly',
            '0 0 1 * *' => 'Monthly',
            '0 0 1 1 *' => 'Yearly',
        ];
        if (isset($known[$expr])) {
            return $known[$expr];
        }

        // "*/N * * * *" for any N minutes.
        if (preg_match('#^\*/(\d+) \* \* \* \*$#', $expr, $m)) {
            return "Every {$m[1]} minutes";
        }
        // "M H * * *" -> "Daily at HH:MM".
        if (preg_match('#^(\d+) (\d+) \* \* \*$#', $expr, $m)) {
            return sprintf('Daily at %02d:%02d', (int) $m[2], (int) $m[1]);
        }

        return $expr;
    }
}
