<?php

namespace App\Models\Telemetry;

class IssueActivity extends TelemetryRecord
{
    protected $table = 'nightowl_issue_activity';

    protected $fillable = ['issue_id', 'user_id', 'user_name', 'actor_type', 'actor_meta', 'action', 'old_value', 'new_value', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'actor_meta' => 'array',
    ];
}
