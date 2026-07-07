<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\AppResource;
use App\Models\App;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/apps/{app} — update an app's display fields. Restricted to
 * members of the app's team's org. Returns the same flat shape
 * ShowApp::handle() builds.
 */
class UpdateApp
{
    use AsAction;
    use AuthorizesOrgMembership;

    /** See UpdateOrg::authorize() docblock for why {app} is read off the route. */
    public function authorize(ActionRequest $request): bool
    {
        /** @var App $app */
        $app = $request->route('app');

        return $this->authorizeOrgMember($app->team->org, $request->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'db_connection' => ['nullable', 'string'],
            'environments' => ['nullable', 'array'],
        ];
    }

    public function handle(App $app, ActionRequest $request)
    {
        $app->update($request->validated());

        return response()->json((new AppResource($app))->resolve());
    }
}
