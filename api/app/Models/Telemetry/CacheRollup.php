<?php

namespace App\Models\Telemetry;

class CacheRollup extends TelemetryRecord
{
    protected $table = 'nightowl_cache_rollups';

    // Composite PK (key, store, bucket_start, environment), no 'id' column.
    public $incrementing = false;

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}
