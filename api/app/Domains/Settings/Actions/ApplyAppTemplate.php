<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/templates/apply — clone another app's synced
 * template onto this one. Only non-secret config travels (environment
 * colors) — never the source app's agent token.
 */
class ApplyAppTemplate
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_app_id' => ['required', 'string'],
        ];
    }

    public function handle(App $app, ActionRequest $request)
    {
        $data = $request->validated();

        $source = App::query()->where('app_id', $data['from_app_id'])->firstOrFail();

        $app->update(['environments' => $source->environments ?? []]);
        $app->update(['template_synced_at' => now()]);

        return response()->json([
            'app_id' => $app->app_id,
            'environments' => $app->environments,
        ]);
    }
}
