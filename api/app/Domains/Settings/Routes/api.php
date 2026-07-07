<?php

use App\Domains\Settings\Actions\ApplyAppTemplate;
use App\Domains\Settings\Actions\DestroyAlertChannel;
use App\Domains\Settings\Actions\ListAlertChannels;
use App\Domains\Settings\Actions\ListAppTemplates;
use App\Domains\Settings\Actions\RegenerateAppToken;
use App\Domains\Settings\Actions\ShowAppSettings;
use App\Domains\Settings\Actions\ShowAppStorage;
use App\Domains\Settings\Actions\StoreAlertChannel;
use App\Domains\Settings\Actions\SyncAppTemplate;
use App\Domains\Settings\Actions\ToggleAlertChannel;
use App\Domains\Settings\Actions\UpdateAlertChannel;
use App\Domains\Settings\Actions\UpdateAppEnvironment;
use App\Domains\Settings\Actions\UpdateAppSetting;
use Illuminate\Support\Facades\Route;

// Per-app settings + onboarding templates + alert channels
// (docs/pages/settings.md).
Route::prefix('apps/{app}')->group(function () {
    Route::get('/settings', ShowAppSettings::class);
    Route::get('/settings/storage', ShowAppStorage::class);
    Route::put('/settings/{key}', UpdateAppSetting::class);
    Route::put('/environments/{name}', UpdateAppEnvironment::class);
    Route::post('/token/regenerate', RegenerateAppToken::class);
    Route::get('/templates', ListAppTemplates::class);
    Route::post('/templates/sync', SyncAppTemplate::class);
    Route::post('/templates/apply', ApplyAppTemplate::class);

    // Alert channels (docs/pages/settings.md "Alerts" tab). Same URL shapes
    // as the old apiResource(...)->except(['show'])->parameters([...
    // 'alertChannel']) registration — both PUT and PATCH accepted for
    // update, matching what Route::apiResource used to register.
    Route::get('/alert-channels', ListAlertChannels::class);
    Route::post('/alert-channels', StoreAlertChannel::class);
    Route::match(['put', 'patch'], '/alert-channels/{alertChannel}', UpdateAlertChannel::class);
    Route::delete('/alert-channels/{alertChannel}', DestroyAlertChannel::class);
    Route::post('/alert-channels/{alertChannel}/toggle', ToggleAlertChannel::class);
});
