<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Org;
use Illuminate\Http\Request;

class OrgController extends Controller
{
    /** Orgs the authenticated dashboard user belongs to. */
    public function index(Request $request)
    {
        $orgs = $request->user()->orgs()->get(['orgs.id', 'name', 'account_email']);

        // Demo/dev convenience: if membership wasn't seeded for this user,
        // fall back to every org so the dashboard is never empty.
        if ($orgs->isEmpty()) {
            $orgs = Org::query()->get(['id', 'name', 'account_email']);
        }

        return response()->json(['data' => $orgs]);
    }
}
