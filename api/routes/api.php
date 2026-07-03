<?php

use App\Http\Controllers\Api\AlertChannelController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IssueActionController;
use App\Http\Controllers\Api\NightowlUserController;
use App\Http\Controllers\Api\RollupController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/users', [NightowlUserController::class, 'index']);
    Route::get('/users/{userId}', [NightowlUserController::class, 'show']);

    Route::apiResource('alert-channels', AlertChannelController::class)
        ->except(['show'])
        ->parameters(['alert-channels' => 'alertChannel']);
    Route::post('/alert-channels/{alertChannel}/toggle', [AlertChannelController::class, 'toggle']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings/{key}', [SettingController::class, 'update']);

    Route::get('/rollups/{type}', [RollupController::class, 'index']);

    Route::post('/issues/{issue}/resolve', [IssueActionController::class, 'resolve']);
    Route::post('/issues/{issue}/ignore', [IssueActionController::class, 'ignore']);
    Route::post('/issues/{issue}/reopen', [IssueActionController::class, 'reopen']);
    Route::get('/issues/{issue}/comments', [IssueActionController::class, 'comments']);
    Route::post('/issues/{issue}/comments', [IssueActionController::class, 'storeComment']);

    // Generic read-only telemetry resources (12 parity resources — see
    // config/telemetry.php). Registered last: their {resource} wildcard is
    // constrained to the known resource keys, so it can't shadow the
    // explicit routes above even if it were registered first, but keeping
    // it last keeps the file readable as "specific routes, then the
    // catch-all".
    Route::get('/{resource}', [TelemetryController::class, 'index'])
        ->whereIn('resource', array_keys(config('telemetry.resources')));

    Route::get('/{resource}/{id}', [TelemetryController::class, 'show'])
        ->whereIn('resource', array_keys(config('telemetry.resources')))
        ->whereNumber('id');
});
