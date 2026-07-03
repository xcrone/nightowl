<?php

namespace App\Models\Telemetry;

class Issue extends TelemetryRecord
{
    protected $table = 'nightowl_issues';

    public $timestamps = true;

    protected $fillable = ['status', 'priority', 'assigned_to', 'description'];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'occurrences_count' => 'integer',
        'users_count' => 'integer',
    ];

    public function activity()
    {
        return $this->hasMany(IssueActivity::class, 'issue_id')->latest('created_at');
    }

    public function comments()
    {
        return $this->hasMany(IssueComment::class, 'issue_id')->oldest('created_at');
    }
}
