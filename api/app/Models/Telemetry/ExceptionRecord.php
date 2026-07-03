<?php

namespace App\Models\Telemetry;

class ExceptionRecord extends TelemetryRecord
{
    protected $table = 'nightowl_exceptions';

    protected $casts = [
        'created_at' => 'datetime',
        'handled' => 'boolean',
    ];
}
