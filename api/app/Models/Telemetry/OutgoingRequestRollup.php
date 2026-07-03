<?php

namespace App\Models\Telemetry;

class OutgoingRequestRollup extends TelemetryRecord
{
    protected $table = 'nightowl_outgoing_request_rollups';

    // Composite PK (group_hash, bucket_start, environment), no 'id' column.
    public $incrementing = false;

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}
