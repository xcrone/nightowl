<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The `?environment=` page-scope filter — the app switcher's "All
 * environments" control (docs/api-contract.md). A single shared mechanism used
 * by both the raw telemetry engine (App\Support\TelemetryQuery via
 * App\Actions\Telemetry\*) and the aggregate/exception engines
 * (App\Support\AggregateQuery via App\Actions\Aggregates\* /
 * App\Actions\Exceptions\*), since every telemetry + issues table carries the
 * same indexed `environment` column (agent migration
 * 2024_01_01_000026_add_environment_column) — only the nightowl_users
 * dimension table / bespoke users aggregate lacks it, and those callers simply
 * don't invoke this.
 *
 * Absent or empty ?environment= -> no constraint (all environments), matching
 * the "All environments" default. Operates on both Eloquent and DB::table
 * query builders since it only adds a plain `where('environment', ?)` clause.
 *
 * @param  \Illuminate\Contracts\Database\Query\Builder|Builder  $query
 */
class EnvironmentScope
{
    public static function apply($query, Request $request): void
    {
        $environment = $request->query('environment');

        if ($environment === null || $environment === '') {
            return;
        }

        $query->where('environment', $environment);
    }
}
