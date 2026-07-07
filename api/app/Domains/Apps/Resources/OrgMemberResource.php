<?php

namespace App\Domains\Apps\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One org member, as returned by AddOrgMember's single-member response and
 * ListOrgMembers's collection. uuid-public-ids: the integer PK never
 * serializes here, only `uuid`.
 */
class OrgMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
