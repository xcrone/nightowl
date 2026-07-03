<?php

namespace App\Models\Telemetry;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class TelemetryRecord extends Model
{
    use HasFactory;

    protected $connection = 'nightowl';

    public $timestamps = false;

    protected $guarded = ['*'];
}
