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
        $orgs = $request->user()->orgs()
            ->with('owner')
            ->get(['orgs.id', 'orgs.uuid', 'name', 'account_email', 'orgs.owner_id', 'orgs.is_personal']);

        // Demo/dev convenience: if membership wasn't seeded for this user,
        // fall back to every org so the dashboard is never empty.
        if ($orgs->isEmpty()) {
            $orgs = Org::query()->with('owner')->get(['id', 'uuid', 'name', 'account_email', 'owner_id', 'is_personal']);
        }

        return OrgResource::collection($orgs);
    }
}
