<?php

namespace App\Models\Telemetry;

use Illuminate\Support\Str;

class AlertChannel extends TelemetryRecord
{
    protected $table = 'nightowl_alert_channels';

    public $timestamps = true;

    protected $fillable = ['app_id', 'name', 'type', 'config', 'enabled'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        // uuid-public-ids: every new alert channel gets a public uuid
        // identifier going forward; existing rows are backfilled by the
        // cross-repo retrofit migration
        // (2026_07_06_130000_add_uuid_to_nightowl_alert_channels_table, run
        // against the `nightowl` connection since this table is owned by
        // nightowl/agent, not this repo).
        static::creating(function (AlertChannel $channel) {
            $channel->uuid ??= (string) Str::uuid();
        });
    }
}
