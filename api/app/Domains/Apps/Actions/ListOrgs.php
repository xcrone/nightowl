<?php

namespace App\Domains\Apps\Actions;

use App\Domains\Apps\Resources\OrgResource;
use App\Models\Org;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/orgs — Orgs the authenticated dashboard user belongs to.
 */
class ListOrgs
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(ActionRequest $request)
    {
        $orgs = $request->user()->orgs()->get(['orgs.id', 'orgs.uuid', 'name', 'account_email']);

        // Demo/dev convenience: if membership wasn't seeded for this user,
        // fall back to every org so the dashboard is never empty.
        if ($orgs->isEmpty()) {
            $orgs = Org::query()->get(['id', 'uuid', 'name', 'account_email']);
        }

        return OrgResource::collection($orgs);
    }
}
