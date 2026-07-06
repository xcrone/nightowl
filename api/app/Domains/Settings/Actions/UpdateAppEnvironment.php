<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/apps/{app}/environments/{name} — set (or add) one environment's
 * color chip on the app's settings/dashboard (docs/pages/settings.md).
 */
class UpdateAppEnvironment
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color' => ['required', 'string', 'max:9'],
        ];
    }

    public function handle(App $app, string $name, ActionRequest $request)
    {
        $data = $request->validated();

        $envs = $app->environments ?? [];
        $envs[$name] = $data['color'];
        $app->update(['environments' => $envs]);

        return response()->json(['environments' => $app->environments]);
    }
}
