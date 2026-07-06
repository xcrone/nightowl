<?php

use App\Domains\Users\Actions\ListNightowlUsers;
use App\Domains\Users\Actions\ShowNightowlUser;
use App\Domains\Users\Actions\ShowUserDetail;
use Illuminate\Support\Facades\Route;

// Legacy, top-level (NOT app-scoped) — see this domain's README for why
// these predate the {app} scoping the rest of the API uses.
Route::get('/users', ListNightowlUsers::class);
Route::get('/users/{userId}', ShowNightowlUser::class);

// Per-app drill-down. 'users' isn't a telemetry resource key, so no clash
// with the generic /{resource} catch-all registered by app/Actions/ later.
Route::prefix('apps/{app}')->group(function () {
    Route::get('/users/{userId}', ShowUserDetail::class);
});
