<?php

namespace App\Domains\Settings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes Template (new this batch — the raw model used to only ever be
 * dumped directly by `AppSettingController::templates()`/`syncTemplate()`).
 * `id`/`uuid` follow the same additive rule as every other retrofitted
 * table this migration pass. Deliberately does NOT serialize the model's
 * `app_id` column: unlike every other `app_id` in this codebase, this one
 * is an internal integer FK to `apps.id` (`$table->foreignId('app_id')
 * ->constrained('apps')` in `2026_07_06_000001_create_app_management_tables`),
 * not the opaque public `App::app_id` string — exposing it under the same
 * key name other payloads use for the compliant identifier would be
 * actively misleading, and the SPA never needed it (a template is always
 * fetched already scoped to `{app}` in the URL). No existing consumer
 * depended on it either, since this is the first Resource ever written for
 * Template — this domain doesn't import `Domains/Apps/Resources` (no
 * cross-domain imports), so this is its own copy rather than a shared one.
 */
class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'payload' => $this->payload,
            'synced_at' => $this->synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
