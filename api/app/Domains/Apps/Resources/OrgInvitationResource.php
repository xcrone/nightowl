<?php

namespace App\Domains\Apps\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One org invitation, as returned by InviteOrgMember's single-invitation
 * response and ListOrgInvitations/ListReceivedInvitations's collections.
 * uuid-public-ids: the integer PK never serializes here, only `uuid`.
 */
class OrgInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'responded_at' => $this->responded_at,
            'org' => $this->whenLoaded('org', fn () => [
                'uuid' => $this->org->uuid,
                'name' => $this->org->name,
            ]),
        ];
    }
}
