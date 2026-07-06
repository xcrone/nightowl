<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Telemetry\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Per-app settings hub (docs/pages/settings.md): the free-form key/value
 * settings map (nightowl_settings, scoped by app_id), detected environments +
 * colors, the agent token, and the onboarding-template system (sync / apply).
 */
class AppSettingController extends Controller
{
    private const RESERVED_KEYS = ['app_id', 'name', 'description', 'environments', 'agent_token_masked', 'template'];

    public function index(App $app)
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

    public function updateSetting(Request $request, App $app, string $key)
    {
        abort_if(in_array($key, self::RESERVED_KEYS, true), 422, "'{$key}' is a reserved setting key.");

        $data = $request->validate(['value' => ['required', 'string']]);

        Setting::query()->updateOrCreate(
            ['app_id' => $app->app_id, 'key' => $key],
            ['value' => $data['value']],
        );

        return response()->json(['key' => $key, 'value' => $data['value']]);
    }

    public function updateEnvironment(Request $request, App $app, string $name)
    {
        $data = $request->validate(['color' => ['required', 'string', 'max:9']]);

        $envs = $app->environments ?? [];
        $envs[$name] = $data['color'];
        $app->update(['environments' => $envs]);

        return response()->json(['environments' => $app->environments]);
    }

    public function regenerateToken(App $app)
    {
        $token = 'nwt_'.Str::random(40);
        $app->update(['agent_token' => $token]);

        // Shown once on generation (docs: "shown once on generation").
        return response()->json(['agent_token' => $token]);
    }

    public function templates(App $app)
    {
        return response()->json(['data' => $app->templates()->latest('synced_at')->get()]);
    }

    /** Snapshot this app's current config into its onboarding template. */
    public function syncTemplate(Request $request, App $app)
    {
        $data = $request->validate(['name' => ['nullable', 'string']]);

        $template = $app->templates()->updateOrCreate(
            ['name' => $data['name'] ?? 'Default Setup'],
            ['payload' => ['environments' => $app->environments ?? []], 'synced_at' => now()],
        );

        return response()->json($template);
    }

    /** Clone another app's template onto this one (secrets excluded). */
    public function applyTemplate(Request $request, App $app)
    {
        $data = $request->validate(['from_app_id' => ['required', 'string']]);

        $source = App::query()->where('app_id', $data['from_app_id'])->firstOrFail();

        // Only non-secret config travels (colors) — never the agent token.
        $app->update(['environments' => $source->environments ?? []]);
        $app->update(['template_synced_at' => now()]);

        return response()->json([
            'app_id' => $app->app_id,
            'environments' => $app->environments,
        ]);
    }

    private function mask(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        return Str::substr($token, 0, 6).str_repeat('•', 8).Str::substr($token, -4);
    }
}
