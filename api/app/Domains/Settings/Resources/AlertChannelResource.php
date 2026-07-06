<?php

namespace App\Domains\Settings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes AlertChannel. `id` stays for now (additive `uuid` alongside it
 * this pass, per the migration plan — no existing consumer breaks; a
 * follow-up coordinated with the web side will drop `id` once the SPA keys
 * off `uuid` instead). `app_id` here is the compliant opaque app identifier
 * (matches `App::app_id`/`TelemetryRecord::scopeForApp`), not an internal
 * PK — safe to serialize as-is, same as every other per-app telemetry
 * payload. `config` is never masked — the controller this was ported from
 * never masked `secret`/`webhook_url` either, so this preserves existing
 * behavior exactly (not a regression introduced by this migration).
 */
class AlertChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'app_id' => $this->app_id,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->config,
            'enabled' => $this->enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
