<?php

namespace App\Models\Telemetry;

class Setting extends TelemetryRecord
{
    protected $table = 'nightowl_settings';

    public $timestamps = true;

    protected $fillable = ['key', 'value'];
}
