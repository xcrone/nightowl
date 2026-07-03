<?php

namespace App\Models\Telemetry;

class NightowlUser extends TelemetryRecord
{
    protected $table = 'nightowl_users';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
