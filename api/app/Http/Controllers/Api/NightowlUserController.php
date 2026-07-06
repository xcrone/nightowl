<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Telemetry\NightowlUser;
use App\Support\SearchTerm;
use Illuminate\Http\Request;

class NightowlUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $query = NightowlUser::query();

        $q = SearchTerm::fromRequest($request);

        if ($q !== null) {
            // Small dimension table (no index needed): plain ILIKE over
            // name/email, same escaping as the trigram-backed telemetry
            // resources for consistency (a literal "%"/"_" in a name
            // shouldn't be treated as a SQL wildcard).
            $escaped = SearchTerm::escapeForLike($q);

            $query->where(function ($w) use ($escaped) {
                $w->where('name', 'ILIKE', '%'.$escaped.'%')
                    ->orWhere('email', 'ILIKE', '%'.$escaped.'%');
            });
        }

        return response()->json(
            $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString()
        );
    }

    public function show(string $userId)
    {
        return response()->json(NightowlUser::query()->findOrFail($userId));
    }
}
