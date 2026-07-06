<?php

use App\Domains\Apps\Actions\ListApps;
use App\Domains\Apps\Actions\ListOrgs;
use App\Domains\Apps\Actions\ShowApp;
use App\Domains\Apps\Actions\ShowDashboard;
use Illuminate\Support\Facades\Route;

// Org -> Teams -> Apps hierarchy (docs/pages/org-dashboard.md). The {app}
// param binds App by its opaque app_id (App::getRouteKeyName).
Route::get('/orgs', ListOrgs::class);
Route::get('/apps', ListApps::class);
Route::get('/apps/{app}', ShowApp::class);

// Per-app dashboard summary — registered ahead of the generic
// /{resource} catch-all (app/Actions/ later): 'dashboard' isn't a
// telemetry resource key, so there's no shadowing either way.
Route::prefix('apps/{app}')->group(function () {
    Route::get('/dashboard', ShowDashboard::class);
});
