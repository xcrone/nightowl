<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever an App's agent_token is minted (StoreApp) or regenerated
 * (Settings\Actions\RegenerateAppToken). Carries the plaintext token — it's
 * only readable at the moment it's created/updated, and App::$agent_token is
 * stored plaintext (no cast) so there's nothing to re-derive it from later.
 *
 * Lives outside app/Domains/ because App is consumed by 2+ domains (Apps +
 * Settings, same reasoning documented in both domains' README.md for the
 * App model itself) — neither domain should own an event the other has to
 * dispatch across a folder boundary.
 */
class AppTokenIssued
{
    use Dispatchable;

    public function __construct(
        public readonly string $appId,
        public readonly string $plaintextToken,
    ) {}
}
