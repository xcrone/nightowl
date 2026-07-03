<?php

namespace App\Models\Telemetry;

class JobRecord extends TelemetryRecord
{
    protected $table = 'nightowl_jobs';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
