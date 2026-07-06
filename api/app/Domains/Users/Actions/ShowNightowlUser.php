<?php

namespace App\Domains\Users\Actions;

use App\Domains\Users\Resources\NightowlUserResource;
use App\Models\Telemetry\NightowlUser;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * GET /api/users/{userId} — legacy, top-level (NOT app-scoped; no
 * forApp() call today — preserved as-is, see this domain's README). 404s
 * via findOrFail when userId doesn't exist.
 */
class ShowNightowlUser
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(string $userId)
    {
        // response()->json() (rather than returning the Resource directly)
        // keeps the response flat, matching the pre-migration controller's
        // shape (findOrFail(...) serialized with no top-level "data" wrap) —
        // returning a JsonResource directly triggers Laravel's default
        // Resource-response wrapping instead.
        return response()->json(new NightowlUserResource(NightowlUser::query()->findOrFail($userId)));
    }
}
