<?php

namespace App\Models\Telemetry;

class ScheduledTask extends TelemetryRecord
{
    protected $table = 'nightowl_scheduled_tasks';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
