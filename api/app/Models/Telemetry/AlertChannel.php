<?php

namespace App\Models\Telemetry;

class AlertChannel extends TelemetryRecord
{
    protected $table = 'nightowl_alert_channels';

    public $timestamps = true;

    protected $fillable = ['name', 'type', 'config', 'enabled'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];
}
