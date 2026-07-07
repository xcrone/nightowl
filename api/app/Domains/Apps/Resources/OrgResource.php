<?php

namespace App\Domains\Apps\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'account_email' => $this->account_email,
            'is_personal' => $this->is_personal,
            // owner_id (the integer FK) never leaves this resource — only
            // the owner's own uuid, per uuid-public-ids. Null for a
            // pre-existing org backfilled with no candidate owner.
            'owner_uuid' => $this->owner?->uuid,
        ];
    }
}
