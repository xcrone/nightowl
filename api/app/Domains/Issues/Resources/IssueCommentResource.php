<?php

namespace App\Domains\Issues\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the full IssueComment model — `id` stays for now (additive
 * `uuid` alongside it this pass, per the migration plan), matching the same
 * full-attribute shape the pre-migration `IssueActionController::comments/
 * storeComment` returned via a raw `response()->json(...)` model dump.
 */
class IssueCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'issue_id' => $this->issue_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'user_email' => $this->user_email,
            'actor_type' => $this->actor_type,
            'actor_meta' => $this->actor_meta,
            'body' => $this->body,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
