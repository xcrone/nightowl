<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Actions\Concerns\GuardsReservedSettingKeys;
use App\Models\App;
use App\Models\Telemetry\Setting;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * DELETE /api/apps/{app}/settings/{key} — remove one free-form setting
 * key/value pair (docs/pages/settings.md). Idempotent: deleting a key that
 * was never set (or already deleted) is a no-op, not an error. Reserved
 * keys (see UpdateAppSetting) can't be deleted either, for the same reason
 * they can't be written to.
 */
class DestroyAppSetting
{
    use AsAction;
    use GuardsReservedSettingKeys;

    public function authorize(ActionRequest $request): bool
    {
        $key = $request->route('key');

        $this->abortIfReservedSettingKey($key);

        return true;
    }

    public function handle(App $app, string $key)
    {
        Setting::query()->forApp($app->app_id)->where('key', $key)->delete();

        return response()->noContent();
    }
}
