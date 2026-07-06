<?php

namespace App\Models\Telemetry;

use Illuminate\Support\Str;

class IssueActivity extends TelemetryRecord
{
    protected $table = 'nightowl_issue_activity';

    protected $fillable = ['issue_id', 'user_id', 'user_name', 'actor_type', 'actor_meta', 'action', 'old_value', 'new_value', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'actor_meta' => 'array',
    ];

    protected static function booted(): void
    {
        // uuid-public-ids: same cross-repo retrofit as Issue
        // (2026_07_06_140000_add_uuid_to_nightowl_issues_tables).
        static::creating(function (IssueActivity $activity) {
            $activity->uuid ??= (string) Str::uuid();
        });
    }
}
