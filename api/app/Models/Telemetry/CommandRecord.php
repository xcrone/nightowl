<?php

namespace App\Models\Telemetry;

class CommandRecord extends TelemetryRecord
{
    protected $table = 'nightowl_commands';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
