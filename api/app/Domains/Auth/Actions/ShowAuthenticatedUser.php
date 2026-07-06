<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Resources\UserResource;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/user — returns the currently authenticated user, gated by the
 * root aggregator's `auth:sanctum` middleware group (see
 * App\Domains\Auth\Routes\api.php).
 */
class ShowAuthenticatedUser
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(ActionRequest $request)
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }
}
