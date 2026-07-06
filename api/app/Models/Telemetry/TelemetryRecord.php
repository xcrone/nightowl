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

    /**
     * Scope a telemetry query to one app by its opaque app_id.
     * Usage: RequestRecord::forApp($app->app_id)->…
     */
    public function scopeForApp($query, string $appId)
    {
        return $query->where('app_id', $appId);
    }
}
