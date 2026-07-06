<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/apps/{app}/alert-channels/{alertChannel} — remove an alert
 * channel scoped to this app (docs/pages/settings.md "Alerts" tab).
 */
class DestroyAlertChannel
{
    use AsAction;
    use AuthorizesAppScope;

    /**
     * `authorize()`/`rules()` are resolved via plain container `call()`
     * (lorisleiva/laravel-actions), which does NOT have access to the
     * router's already-substituted route-model bindings — a type-hinted
     * `App $app`/`AlertChannel $alertChannel` parameter here would silently
     * receive a fresh, empty model instead of the real one. Reading them
     * off `$request->route(...)` instead gets the actual bound models
     * (resolved once by `SubstituteBindings` for the whole request), same
     * ones `handle()` receives via its own (route-aware) resolution path.
     */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('alertChannel'));

        return true;
    }

    public function handle(App $app, AlertChannel $alertChannel)
    {
        $alertChannel->delete();

        return response()->noContent();
    }
}
