<?php

namespace App\Models\Telemetry;

class CacheEvent extends TelemetryRecord
{
    protected $table = 'nightowl_cache_events';

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
