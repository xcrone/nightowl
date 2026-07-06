<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Resources\TemplateResource;
use App\Models\App;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/apps/{app}/templates — this app's onboarding templates, most
 * recently synced first (docs/pages/settings.md).
 */
class ListAppTemplates
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        return response()->json([
            'data' => TemplateResource::collection($app->templates()->latest('synced_at')->get())->resolve(),
        ]);
    }
}
