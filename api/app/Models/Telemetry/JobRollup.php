<?php

namespace App\Models\Telemetry;

class JobRollup extends TelemetryRecord
{
    protected $table = 'nightowl_job_rollups';

    // Composite PK (group_hash, bucket_start, environment), no 'id' column.
    public $incrementing = false;

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}
