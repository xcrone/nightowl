<?php

namespace App\Models\Telemetry;

class OutgoingRequest extends TelemetryRecord
{
    protected $table = 'nightowl_outgoing_requests';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
