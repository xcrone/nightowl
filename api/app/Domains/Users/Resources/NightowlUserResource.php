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
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
