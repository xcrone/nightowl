<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shared `?q=` handling for the entrypoints that support free-text search
 * (App\Support\TelemetryQuery, App\Actions\Aggregates\IndexAggregate,
 * App\Domains\Users\Actions\ListNightowlUsers). No FormRequest layer exists
 * anywhere in this app (see config/telemetry.php's filter design) — this
 * stays a plain static helper rather than introducing one just for a single
 * optional string param.
 */
class SearchTerm
{
    private const MAX_LENGTH = 200;

    /**
     * Trimmed, length-capped search term from the request, or null if
     * absent/blank. The length cap is defensive: a pathological multi-KB
     * query string is never a legitimate search term, and both
     * websearch_to_tsquery parsing and a wall of ILIKE OR-branches are
     * cheap only because real input is short.
     */
    public static function fromRequest(Request $request): ?string
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return null;
        }

        return mb_substr($q, 0, self::MAX_LENGTH);
    }

    /**
     * Escapes ILIKE's special characters (%, _, and the escape character
     * itself) so a literal search for e.g. "100%" or a job name containing
     * an underscore matches literally instead of being treated as a
     * wildcard.
     */
    public static function escapeForLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
