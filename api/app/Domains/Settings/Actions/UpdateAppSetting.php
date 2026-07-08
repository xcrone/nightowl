<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Actions\Concerns\GuardsReservedSettingKeys;
use App\Models\App;
use App\Models\Telemetry\Setting;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/apps/{app}/settings/{key} — upsert one free-form setting key/value
 * pair (docs/pages/settings.md). A handful of keys are reserved because
 * they're computed/derived fields on the settings payload itself, not
 * genuine settings — writing to them here would silently do nothing (or
 * worse, get shadowed) on the next GET /settings.
 */
class UpdateAppSetting
{
    use AsAction;
    use GuardsReservedSettingKeys;

    public function authorize(ActionRequest $request): bool
    {
        $key = $request->route('key');

        $this->abortIfReservedSettingKey($key);

        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
        ];
    }

    public function handle(App $app, string $key, ActionRequest $request)
    {
        $data = $request->validated();

        Setting::query()->updateOrCreate(
            ['app_id' => $app->app_id, 'key' => $key],
            ['value' => $data['value']],
        );

        return response()->json(['key' => $key, 'value' => $data['value']]);
    }
}
