<?php

namespace App\Domains\Apps\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base Team fields (id/uuid/name) only — the Org Dashboard tree's
 * `apps_count`/`apps` (a computed health() summary per app, not a raw
 * relation dump) are merged in by ListApps at the call site rather than
 * living on this Resource, since they aren't a 1:1 model field.
 */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
        ];
    }
}
