<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Session-based Sanctum SPA login. Referenced directly from routes/web.php
 * (not routes/api.php) so it runs through the 'web' middleware group —
 * session + CSRF — per Sanctum's SPA auth pattern: the frontend hits
 * GET /sanctum/csrf-cookie first, then POSTs here with the X-XSRF-TOKEN
 * header.
 */
class Login
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function handle(ActionRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($request->user())]);
    }
}
