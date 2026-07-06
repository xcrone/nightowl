<?php

namespace App\Support;

use Illuminate\Http\Request;
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
            foreach ($config['extra'] ?? [] as $col) {
                $expr[] = "MAX({$col}) as {$col}";
            }
            foreach ($config['collect_distinct'] ?? [] as $alias => $col) {
                $expr[] = "string_agg(DISTINCT {$col}::text, ',') as {$alias}";
            }
            foreach ($config['distinct_count'] ?? [] as $alias => $col) {
                $expr[] = "COUNT(DISTINCT {$col}) as {$alias}";
            }
            if ($lastCol = $config['last'] ?? null) {
                $expr[] = "MAX({$lastCol}) as last_{$lastCol}";
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
        foreach (['avg', 'p95', 'min', 'max'] as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null) {
                $row[$k] = (int) $row[$k];
            }
        }
        foreach (array_keys($config['count_buckets'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = (int) $row[$alias];
            }
        }
        foreach (array_keys($config['collect_distinct'] ?? []) as $alias) {
            if (array_key_exists($alias, $row)) {
                $row[$alias] = $row[$alias] ? explode(',', $row[$alias]) : [];
            }
        }
        // Cache hit rate convenience.
        if (isset($row['hits'], $row['misses'])) {
            $reads = $row['hits'] + $row['misses'];
            $row['hit_rate'] = $reads > 0 ? round($row['hits'] / $reads * 100, 2) : 0.0;
        }

        return $row;
    }
}
