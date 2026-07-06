<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Resources\TemplateResource;
use App\Models\App;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/templates/sync — snapshot this app's current config
 * (currently just its environment colors) into its onboarding template,
 * upserted by name (default "Default Setup").
 */
class SyncAppTemplate
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
        ];
    }

    public function handle(App $app, ActionRequest $request)
    {
        $data = $request->validated();

        $template = $app->templates()->updateOrCreate(
            ['name' => $data['name'] ?? 'Default Setup'],
            ['payload' => ['environments' => $app->environments ?? []], 'synced_at' => now()],
        );

        return response()->json((new TemplateResource($template))->resolve());
    }
}
