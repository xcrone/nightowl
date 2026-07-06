<?php

namespace App\Domains\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the authenticated User. `id` is kept this pass alongside the
 * new `uuid` — per the uuid retrofit plan, the integer PK is additive-only
 * removed in a later follow-up once the SPA is confirmed to key off `uuid`
 * instead. Mirrors exactly what App\Models\User::toArray() exposed today
 * (password/remember_token stay hidden via the model's #[Hidden] attribute).
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
