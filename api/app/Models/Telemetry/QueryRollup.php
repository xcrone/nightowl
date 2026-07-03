<?php

namespace App\Models\Telemetry;

class QueryRollup extends TelemetryRecord
{
    protected $table = 'nightowl_query_rollups';

    // Composite PK (group_hash, bucket_start, environment, connection), no
    // 'id' column — disable Eloquent's auto-increment assumption so inserts
    // don't try `RETURNING id`.
    public $incrementing = false;

    protected $casts = [
        'bucket_start' => 'datetime',
    ];
}
