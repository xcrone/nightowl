<?php

namespace App\Domains\Issues\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes IssueActivity for the issue detail page's activity feed
 * (`ShowIssue`'s `activity` key). Same curated field set as the
 * pre-migration `IssueActionController::show()` mapping (`id`, `actor_type`,
 * `actor_name` aliasing the model's `user_name`, `action`, `old_value`,
 * `new_value`, `created_at` formatted `->toIso8601String()` exactly as the
 * old controller did) — `uuid` is additive alongside `id` this pass, per the
 * migration plan.
 */
class IssueActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'actor_type' => $this->actor_type,
            'actor_name' => $this->user_name,
            'action' => $this->action,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
