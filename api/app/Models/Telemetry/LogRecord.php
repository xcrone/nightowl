<?php

namespace App\Models\Telemetry;

class LogRecord extends TelemetryRecord
{
    protected $table = 'nightowl_logs';

    /**
     * The raw logs list (App\Actions\Telemetry\IndexTelemetryResource)
     * returns the paginator's models directly, with no API Resource wrapper.
     * `search_vector` is a Postgres tsvector generated column that exists
     * only to back full-text search — it is internal plumbing and must never
     * cross the api -> web boundary, so hide it from every serialization.
     */
    protected $hidden = ['search_vector'];
}
