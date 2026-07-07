<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Models\Org;
use App\Support\AuthorizesOrgMembership;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUT /api/orgs/{org} — rename an org / change its billing contact email.
 * Restricted to existing members.
 */
class UpdateOrg
{
    use AsAction;
    use AuthorizesOrgMembership;

    /**
     * `authorize()`/`rules()` are resolved via plain container `call()`
     * (lorisleiva/laravel-actions) — no access to the router's
     * already-substituted bindings, so the route-bound {org} is read off
     * `$request->route('org')` rather than a type-hinted parameter (see
     * DestroyAlertChannel's authorize() docblock for the full rationale).
     */
    public function authorize(ActionRequest $request): bool
    {
        /** @var Org $org */
        $org = $request->route('org');

        return $this->authorizeOrgMember($org, $request->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_email' => ['sometimes', 'required', 'email'],
        ];
    }

    public function handle(Org $org, ActionRequest $request)
    {
        $org->update($request->validated());

        return response()->json((new OrgResource($org->loadMissing('owner')))->resolve());
    }
}
