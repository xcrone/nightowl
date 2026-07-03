<?php

namespace App\Models\Telemetry;

class RequestRollup extends TelemetryRecord
{
    protected $table = 'nightowl_request_rollups';

    // Composite PK (group_hash, bucket_start, environment), no 'id' column.
    public $incrementing = false;

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}
