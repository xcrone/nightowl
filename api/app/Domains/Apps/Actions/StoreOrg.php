<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Models\Org;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/orgs — found a new Org. Any authenticated dashboard user may
 * create one; the creator is attached as its first member so ListOrgs's
 * "no membership -> show every org" dev fallback never triggers for them.
 */
class StoreOrg
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'account_email' => ['required', 'email'],
        ];
    }

    public function handle(ActionRequest $request)
    {
        $org = Org::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        $org->users()->attach($request->user()->id);

        return response()->json((new OrgResource($org->loadMissing('owner')))->resolve(), 201);
    }
}
