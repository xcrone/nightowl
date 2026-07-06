<?php

namespace App\Domains\Auth\Actions;

use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Session-based Sanctum SPA logout. Referenced directly from routes/web.php
 * (not routes/api.php) so it runs through the 'web' middleware group —
 * session + CSRF — same as Login.
 */
class Logout
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(ActionRequest $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
