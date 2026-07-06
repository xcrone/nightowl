<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Resources\AlertChannelResource;
use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/alert-channels/{alertChannel}/toggle — flip an alert
 * channel's enabled flag (docs/pages/settings.md "Alerts" tab).
 */
class ToggleAlertChannel
{
    use AsAction;
    use AuthorizesAppScope;

    /**
     * See DestroyAlertChannel's authorize() docblock: reads the route-bound
     * models off `$request->route(...)` rather than type-hinting them
     * directly, since `authorize()` is resolved via plain container `call()`
     * (no access to the router's already-substituted bindings).
     */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('alertChannel'));

        return true;
    }

    public function handle(App $app, AlertChannel $alertChannel)
    {
        $alertChannel->update(['enabled' => ! $alertChannel->enabled]);

        return response()->json((new AlertChannelResource($alertChannel))->resolve());
    }
}
