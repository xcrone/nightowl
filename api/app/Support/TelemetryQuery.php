<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared query-building helpers for the generic telemetry engine
 * (`App\Actions\Telemetry\{IndexTelemetryResource,ShowTelemetryResource,RelatedTelemetryResource}`),
 * driven by `config/telemetry.php`. Ported verbatim from the pre-Actions
 * `App\Http\Controllers\Api\TelemetryController` protected methods so the 3
 * Actions compose one implementation instead of duplicating it.
 */
class TelemetryQuery
{
    public static function resourceConfig(string $resource): array
    {
        $config = config("telemetry.resources.{$resource}");

        abort_if($config === null, 404);

        return $config;
    }

    public static function applyFilter(Builder $query, array $filter, string $key, Request $request): void
    {
        if (($filter['kind'] ?? null) === 'assignment') {
            self::applyAssignmentFilter($query, $filter, $key, $request);

            return;
        }

        if (array_key_exists('value', $filter)) {
            // Flag filter: presence of a truthy query param triggers a
            // config-fixed where() clause, e.g. ?failed=1 -> status_code >= 500.
            if ($request->has($key) && $request->boolean($key)) {
                $query->where($filter['column'], $filter['op'], $filter['value']);
            }

            return;
        }

        // Param-driven filter: the where() value comes from the query string.
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return;
        }

        $query->where($filter['column'], $filter['op'] ?? '=', $value);
    }

    /**
     * Tri-state ?assignment=all|unassigned|mine (issues list). Absent or
     * 'all' -> no constraint; 'unassigned' -> the assignee column IS NULL;
     * 'mine' -> the authenticated Sanctum user's email. Any other value is
     * ignored (treated like 'all') so a stray query string never 500s.
     */
    private static function applyAssignmentFilter(Builder $query, array $filter, string $key, Request $request): void
    {
        $value = $request->query($key);
        $column = $filter['column'];

        if ($value === 'unassigned') {
            $query->whereNull($column);

            return;
        }

        if ($value === 'mine') {
            // Match on the current user's email; if the request somehow has no
            // authenticated user, match nothing rather than leaking every row.
            $email = $request->user()?->email;

            $email === null
                ? $query->whereRaw('1 = 0')
                : $query->where($column, $email);
        }
    }

    /**
     * Applies ?q= against whichever search strategy config/telemetry.php's
     * 'search' key declares for this resource. Composes as an additional
     * AND-ed where() alongside the existing filters/traces_to_parent
     * scoping above — it narrows the same query further, it doesn't
     * replace those clauses.
     *
     * tsvector and trigram are OR'd together within the search clause
     * itself (a resource declaring both, e.g. exceptions, should match on
     * either) — but the whole thing is one where(fn (...) => ...) group so
     * it combines with the surrounding AND-ed filters correctly regardless
     * of operator precedence.
     */
    public static function applySearch(Builder $query, array $config, Request $request): void
    {
        $search = $config['search'] ?? null;

        if ($search === null) {
            return;
        }

        $q = SearchTerm::fromRequest($request);

        if ($q === null) {
            return;
        }

        $query->where(function ($outer) use ($search, $q) {
            if (isset($search['tsvector'])) {
                // websearch_to_tsquery tolerates arbitrary user input
                // (quotes, "-", "OR", trailing punctuation) without raising
                // a tsquery syntax error, unlike plainto_tsquery/
                // to_tsquery — the closest built-in parser to "how a
                // search box actually behaves".
                //
                // $search['tsvector'] is a fixed string from static PHP
                // config (never request input), so interpolating it into
                // the raw SQL as a column identifier is safe; $q is the
                // only user-controlled value here and it's always passed
                // as a bound parameter (?), never concatenated into the
                // SQL string.
                $outer->orWhereRaw(
                    "{$search['tsvector']} @@ websearch_to_tsquery('english', ?)",
                    [$q]
                );
            }

            if (isset($search['trigram'])) {
                $escaped = SearchTerm::escapeForLike($q);

                $outer->orWhere(function ($trigram) use ($search, $escaped) {
                    foreach ($search['trigram'] as $column) {
                        $trigram->orWhere($column, 'ILIKE', '%'.$escaped.'%');
                    }
                });
            }
        });
    }

    public static function applySort(Builder $query, array $config, Request $request): void
    {
        $sort = (string) $request->query('sort', $config['default_sort'] ?? '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $config['sortable'] ?? [], true)) {
            $column = ltrim($config['default_sort'] ?? '-created_at', '-');
            $direction = 'desc';
        }

        $query->orderBy($column, $direction);
    }
}
