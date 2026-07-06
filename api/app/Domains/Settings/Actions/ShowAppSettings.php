<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use App\Models\Telemetry\Setting;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/settings — per-app settings hub
 * (docs/pages/settings.md): the free-form key/value settings map
 * (nightowl_settings, scoped by app_id), detected environments + colors,
 * the masked agent token, and this app's current onboarding template (if
 * any was ever synced).
 */
class ShowAppSettings
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $template = $app->templates()->latest('synced_at')->first();

        $settings = Setting::query()->forApp($app->app_id)->pluck('value', 'key')->all();

        return response()->json(array_merge($settings, [
            'app_id' => $app->app_id,
            'name' => $app->name,
            'description' => $app->description,
            'environments' => $app->environments ?? [],
            'agent_token_masked' => $this->mask($app->agent_token),
            'template' => $template ? [
                'name' => $template->name,
                'synced_at' => optional($template->synced_at)->toIso8601String(),
            ] : null,
        ]));
    }

    private function mask(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        return Str::substr($token, 0, 6).str_repeat('•', 8).Str::substr($token, -4);
    }
}
