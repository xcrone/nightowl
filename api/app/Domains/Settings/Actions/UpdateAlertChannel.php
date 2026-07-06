<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Actions\Concerns\ValidatesAlertChannelConfig;
use App\Domains\Settings\Resources\AlertChannelResource;
use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use App\Support\AuthorizesAppScope;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT/PATCH /api/apps/{app}/alert-channels/{alertChannel} — update an alert
 * channel scoped to this app (docs/pages/settings.md "Alerts" tab).
 */
class UpdateAlertChannel
{
    use AsAction;
    use AuthorizesAppScope;
    use ValidatesAlertChannelConfig;

    /**
     * See DestroyAlertChannel's authorize() docblock: reads the route-bound
     * models off `$request->route(...)` rather than type-hinting them
     * directly, since `authorize()`/`rules()` are resolved via plain
     * container `call()` (no access to the router's already-substituted
     * bindings) — a type-hinted `AlertChannel $alertChannel` parameter here
     * would silently receive a fresh, empty model instead of the real one.
     */
    public function authorize(ActionRequest $request): bool
    {
        $this->authorizeAppOwned($request->route('app'), $request->route('alertChannel'));

        return true;
    }

    public function rules(ActionRequest $request): array
    {
        /** @var AlertChannel $alertChannel */
        $alertChannel = $request->route('alertChannel');
        $type = $request->input('type', $alertChannel->type);

        return [
            ...$this->baseAlertChannelRules(),
            ...$this->configRules($type),
        ];
    }

    public function handle(App $app, AlertChannel $alertChannel, ActionRequest $request)
    {
        $data = $request->validated();

        $alertChannel->update($data);

        return response()->json((new AlertChannelResource($alertChannel))->resolve());
    }
}
