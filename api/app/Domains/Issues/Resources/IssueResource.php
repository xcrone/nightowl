<?php

namespace App\Domains\Issues\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the full Issue model — `id` stays for now (additive `uuid`
 * alongside it this pass, per the migration plan — no existing consumer
 * breaks; a follow-up coordinated with the web side will drop `id` once the
 * SPA keys off `uuid` instead), matching the same full-attribute shape the
 * pre-migration `IssueActionController::assign/priority/resolve/ignore/
 * reopen` returned via a raw `response()->json($issue)` model dump. The one
 * intentional omission is `search_vector` (Postgres tsvector generated
 * column) — the raw-dump endpoints leaked it as a byte blob before this
 * migration, but that was never an intentional part of the API surface (the
 * curated `ShowIssue` payload never included it); dropping it here is a
 * minor, behavior-safe cleanup, not a functional regression for any known
 * consumer.
 */
class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'app_id' => $this->app_id,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'deploy' => $this->deploy,
            'environment' => $this->environment,
            'status' => $this->status,
            'priority' => $this->priority,
            'exception_class' => $this->exception_class,
            'exception_message' => $this->exception_message,
            'group_hash' => $this->group_hash,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
            'occurrences_count' => (int) $this->occurrences_count,
            'users_count' => (int) $this->users_count,
            'threshold_ms' => $this->threshold_ms,
            'triggered_duration_ms' => $this->triggered_duration_ms,
            'assigned_to' => $this->assigned_to,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
