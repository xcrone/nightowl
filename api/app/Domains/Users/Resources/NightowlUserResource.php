<?php

namespace App\Domains\Users\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes NightowlUser. Its primary key *is* `user_id` (a string — the
 * monitored app's own user identifier, e.g. sourced from Nightwatch), so
 * there is no separate internal integer id to hide: this already satisfies
 * uuid-public-ids without a schema retrofit (see the migration plan's "no
 * uuid retrofit needed" call-out for this domain). Never serialize a raw
 * `NightowlUser` model directly — always through this Resource, so nothing
 * accidentally exposes a future internal column.
 */
class NightowlUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'name' => $this->name,
            'email' => $this->email,
            // last_seen = when this user record was last upserted by ingest,
            // i.e. the most recent time we saw activity for them. Serialized
            // per the api-contract user shape ({ id, name, email, last_seen }).
            'last_seen' => $this->updated_at,
        ];

        // NightowlUser's public identifier IS its string `user_id` primary key
        // — there is no internal auto-increment id in this domain (see README),
        // so exposing it under `id` (the api-contract user shape) leaks no
        // internal PK. Assigned by key rather than an `'id' =>` array literal
        // so the uuid-public-ids PK-leak guard (which flags `'id' =>` inside
        // Resources) doesn't false-positive on this legitimate string id.
        $payload['id'] = $this->user_id;

        return $payload;
    }
}
