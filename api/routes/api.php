<?php

use App\Actions\Aggregates\IndexAggregate;
use App\Actions\Health\ShowAgentHealth;
use App\Actions\Rollups\IndexRollup;
use App\Actions\Telemetry\IndexTelemetryResource;
use App\Actions\Telemetry\RelatedTelemetryResource;
use App\Actions\Telemetry\ShowTelemetryResource;
use App\Actions\Timeseries\ShowTimeseries;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Domain modules (app/Domains/<Module>/Routes/api.php) contribute their
    // own routes here. See .claude/rules/routes.md — this glob loop is the
    // pure-aggregator target convention; composer routes:export doesn't
    // exist yet, so this stays the only wiring mechanism for now.
    //
    // /orgs, /apps, /apps/{app}, /apps/{app}/dashboard moved to
    // App\Domains\Apps\Routes\api.php; /apps/{app}/settings,
    // /environments/{name}, /token/regenerate, /templates*, and
    // /alert-channels* moved to App\Domains\Settings\Routes\api.php;
    // /apps/{app}/issues* moved to App\Domains\Issues\Routes\api.php
    // (registered by the aggregator loop below).
    foreach (glob(app_path('Domains/*/Routes/api.php')) as $routes) {
        require $routes;
    }

    // Group A cross-cutting Actions (app/Actions/, not a Domain — see the
    // api-domain-dev skill's app/Actions/ carve-out): generic, config-driven
    // engines across 23 telemetry/aggregate resource types. Registered after
    // the domain aggregator loop above so domain routes (e.g. issues/{issue})
    // keep taking precedence over Group A's generic {resource}/{id}
    // catch-all — exactly like today's ordering. Every row scoped by app_id.
    Route::prefix('apps/{app}')->group(function () {
        Route::get('/timeseries/{metric}', ShowTimeseries::class);
        Route::get('/health', ShowAgentHealth::class);

        // Aggregated list pages (per route/job/query/host/key/…). Registered
        // before the generic {resource} catch-all; 'aggregate' isn't a
        // telemetry resource key so there's no shadowing either way.
        Route::get('/aggregate/{resource}', IndexAggregate::class)
            ->whereIn('resource', array_keys(config('aggregates')));

        Route::get('/{resource}', IndexTelemetryResource::class)
            ->whereIn('resource', array_keys(config('telemetry.resources')));

        Route::get('/{resource}/{id}', ShowTelemetryResource::class)
            ->whereIn('resource', array_keys(config('telemetry.resources')))
            ->whereNumber('id');

        // Telescope-style "related entries" — see RelatedTelemetryResource.
        Route::get('/{resource}/{id}/related', RelatedTelemetryResource::class)
            ->whereIn('resource', array_keys(config('telemetry.resources')))
            ->whereNumber('id');
    });

    // --- Not-yet-app-scoped surface (legacy; superseded per-app equivalent
    // exists above — /rollups -> IndexAggregate — but this remains for any
    // lingering callers). /users, /users/{userId} moved to
    // App\Domains\Users\Routes\api.php (registered by the aggregator loop
    // above).
    Route::get('/rollups/{type}', IndexRollup::class);
});
