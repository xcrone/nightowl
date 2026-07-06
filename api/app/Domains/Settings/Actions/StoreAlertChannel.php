<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Actions\Concerns\ValidatesAlertChannelConfig;
use App\Domains\Settings\Resources\AlertChannelResource;
use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/alert-channels — create an alert channel scoped to
 * this app (docs/pages/settings.md "Alerts" tab).
 */
class StoreAlertChannel
{
    use AsAction;
    use ValidatesAlertChannelConfig;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ActionRequest $request): array
    {
        return [
            ...$this->baseAlertChannelRules(),
            ...$this->configRules($request->input('type')),
        ];
    }

    public function handle(App $app, ActionRequest $request)
    {
        $data = $request->validated();
        $data['app_id'] = $app->app_id;

        $channel = AlertChannel::create($data);

        return response()->json((new AlertChannelResource($channel))->resolve(), 201);
    }
}
