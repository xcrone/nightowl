<?php

namespace App\Models\Telemetry;

class RequestRecord extends TelemetryRecord
{
    protected $table = 'nightowl_requests';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
