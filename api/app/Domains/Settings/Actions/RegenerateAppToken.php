<?php

namespace App\Domains\Settings\Actions;

use App\Models\App;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * POST /api/apps/{app}/token/regenerate — issue a fresh agent token
 * (docs/pages/settings.md: "shown once on generation"). The plaintext token
 * is only ever returned from this one response; every other read of it
 * (`ShowAppSettings`) sees the masked form.
 */
class RegenerateAppToken
{
    use AsAction;

    public function authorize(): bool
    {
        return true;
    }

    public function handle(App $app)
    {
        $token = 'nwt_'.Str::random(40);
        $app->update(['agent_token' => $token]);

        return response()->json(['agent_token' => $token]);
    }
}
