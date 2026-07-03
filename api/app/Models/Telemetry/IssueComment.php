<?php

namespace App\Models\Telemetry;

class IssueComment extends TelemetryRecord
{
    protected $table = 'nightowl_issue_comments';

    public $timestamps = true;

    protected $fillable = ['issue_id', 'user_id', 'user_name', 'user_email', 'actor_type', 'actor_meta', 'body'];

    protected $casts = [
        'actor_meta' => 'array',
    ];
}
