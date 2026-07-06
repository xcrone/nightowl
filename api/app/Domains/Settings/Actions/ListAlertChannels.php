<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Resources\AlertChannelResource;
use App\Models\App;
use App\Models\Telemetry\AlertChannel;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/alert-channels — this app's alert channels
 * (docs/pages/settings.md "Alerts" tab), alphabetical by name.
 */
class ListAlertChannels
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $channels = AlertChannel::query()->forApp($app->app_id)->orderBy('name')->get();

        return response()->json(AlertChannelResource::collection($channels)->resolve());
    }
}
