<?php

namespace App\Models\Telemetry;

class QueryRecord extends TelemetryRecord
{
    protected $table = 'nightowl_queries';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
