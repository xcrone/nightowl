<?php

namespace App\Models\Telemetry;

use Illuminate\Support\Str;

class IssueComment extends TelemetryRecord
{
    protected $table = 'nightowl_issue_comments';

    public $timestamps = true;

    protected $fillable = ['issue_id', 'user_id', 'user_name', 'user_email', 'actor_type', 'actor_meta', 'body'];

    protected $casts = [
        'actor_meta' => 'array',
    ];

    protected static function booted(): void
    {
        // uuid-public-ids: same cross-repo retrofit as Issue
        // (2026_07_06_140000_add_uuid_to_nightowl_issues_tables).
        static::creating(function (IssueComment $comment) {
            $comment->uuid ??= (string) Str::uuid();
        });
    }
}
