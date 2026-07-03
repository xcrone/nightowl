<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Telemetry\NightowlUser;
use Illuminate\Http\Request;

class NightowlUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        return response()->json(
            NightowlUser::query()->orderByDesc('updated_at')->paginate($perPage)->withQueryString()
        );
    }

    public function show(string $userId)
    {
        return response()->json(NightowlUser::query()->findOrFail($userId));
    }
}
