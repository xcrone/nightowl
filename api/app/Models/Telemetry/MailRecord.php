<?php

namespace App\Models\Telemetry;

class MailRecord extends TelemetryRecord
{
    protected $table = 'nightowl_mail';

    protected $casts = [
        'created_at' => 'datetime',
        'failed' => 'boolean',
        'queued' => 'boolean',
    ];
}
