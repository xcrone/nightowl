<?php

use App\Http\Controllers\Api\AgentHealthController;
use App\Http\Controllers\Api\AggregateController;
use App\Http\Controllers\Api\AlertChannelController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DataManagementController;
use App\Http\Controllers\Api\IssueActionController;
use App\Http\Controllers\Api\TimeseriesController;
use App\Http\Controllers\Api\NightowlUserController;
use App\Http\Controllers\Api\OrgController;
use App\Http\Controllers\Api\RollupController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\UserDetailController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);

    // Org → Teams → Apps hierarchy (docs/pages/org-dashboard.md). The {app}
    // param binds App by its opaque app_id (App::getRouteKeyName).
    Route::get('/orgs', [OrgController::class, 'index']);
    Route::get('/apps', [AppController::class, 'index']);
    Route::get('/apps/{app}', [AppController::class, 'show']);

    // Per-app telemetry — every row scoped by app_id in TelemetryController.
    Route::prefix('apps/{app}')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::get('/timeseries/{metric}', [TimeseriesController::class, 'show']);
        Route::get('/health', [AgentHealthController::class, 'show']);
        Route::post('/data-management/preview', [DataManagementController::class, 'preview']);

        // Per-app settings + onboarding templates (docs/pages/settings.md).
        Route::get('/settings', [AppSettingController::class, 'index']);
        Route::put('/settings/{key}', [AppSettingController::class, 'updateSetting']);
        Route::put('/environments/{name}', [AppSettingController::class, 'updateEnvironment']);
        Route::post('/token/regenerate', [AppSettingController::class, 'regenerateToken']);
        Route::get('/templates', [AppSettingController::class, 'templates']);
        Route::post('/templates/sync', [AppSettingController::class, 'syncTemplate']);
        Route::post('/templates/apply', [AppSettingController::class, 'applyTemplate']);

        // Per-app alert channels (docs/pages/settings.md "Alerts" tab).
        Route::apiResource('alert-channels', AlertChannelController::class)
            ->except(['show'])
            ->parameters(['alert-channels' => 'alertChannel']);
        Route::post('/alert-channels/{alertChannel}/toggle', [AlertChannelController::class, 'toggle']);

        // Aggregated list pages (per route/job/query/host/key/…). Registered
        // before the generic {resource} catch-all; 'aggregate' isn't a
        // telemetry resource key so there's no shadowing either way.
        Route::get('/aggregate/{resource}', [AggregateController::class, 'index'])
            ->whereIn('resource', array_keys(config('aggregates')));

        // Rich issue detail + workflow — registered before the generic
        // /{resource}/{id} so /issues/{issue} returns the full drill-down
        // (occurrences/activity/env) rather than the raw Issue row.
        Route::get('/issues/{issue}', [IssueActionController::class, 'show']);
        Route::post('/issues/{issue}/assign', [IssueActionController::class, 'assign']);
        Route::post('/issues/{issue}/priority', [IssueActionController::class, 'priority']);
        Route::post('/issues/{issue}/resolve', [IssueActionController::class, 'resolve']);
        Route::post('/issues/{issue}/ignore', [IssueActionController::class, 'ignore']);
        Route::post('/issues/{issue}/reopen', [IssueActionController::class, 'reopen']);
        Route::get('/issues/{issue}/comments', [IssueActionController::class, 'comments']);
        Route::post('/issues/{issue}/comments', [IssueActionController::class, 'storeComment']);

        // Per-user drill-down ('users' isn't a telemetry resource key, so no
        // clash with the generic catch-all below).
        Route::get('/users/{userId}', [UserDetailController::class, 'show']);

        Route::get('/{resource}', [TelemetryController::class, 'index'])
            ->whereIn('resource', array_keys(config('telemetry.resources')));

        Route::get('/{resource}/{id}', [TelemetryController::class, 'show'])
            ->whereIn('resource', array_keys(config('telemetry.resources')))
            ->whereNumber('id');

        // Telescope-style "related entries" — see TelemetryController::related().
        Route::get('/{resource}/{id}/related', [TelemetryController::class, 'related'])
            ->whereIn('resource', array_keys(config('telemetry.resources')))
            ->whereNumber('id');
    });

    // --- Not-yet-app-scoped surfaces (legacy; superseded per-app equivalents
    // exist above — /users/{userId} -> UserDetailController, /rollups ->
    // AggregateController — but these remain for any lingering callers).
    Route::get('/users', [NightowlUserController::class, 'index']);
    Route::get('/users/{userId}', [NightowlUserController::class, 'show']);

    Route::get('/rollups/{type}', [RollupController::class, 'index']);
});
