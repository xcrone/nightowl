<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Telemetry\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(
            Setting::query()->orderBy('key')->get()->pluck('value', 'key')
        );
    }

    public function update(Request $request, string $key)
    {
        $data = $request->validate([
            'value' => ['required', 'string'],
        ]);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $data['value']]);

        return response()->json(['key' => $key, 'value' => $data['value']]);
    }
}
