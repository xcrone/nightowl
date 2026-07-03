<?php

namespace App\Models\Telemetry;

class NotificationRecord extends TelemetryRecord
{
    protected $table = 'nightowl_notifications';

    protected $casts = [
        'created_at' => 'datetime',
        'failed' => 'boolean',
        'queued' => 'boolean',
    ];
}
