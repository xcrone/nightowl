<?php

namespace App\Models\Telemetry;

use Illuminate\Support\Str;

class Issue extends TelemetryRecord
{
    protected $table = 'nightowl_issues';

    public $timestamps = true;

    protected $fillable = ['status', 'priority', 'assigned_to', 'description'];

    /**
     * The raw issues list (App\Actions\Telemetry\IndexTelemetryResource)
     * returns the paginator's models directly, with no API Resource wrapper.
     * `search_vector` is a Postgres tsvector generated column that exists
     * only to back full-text search — it is internal plumbing and must never
     * cross the api -> web boundary, so hide it from every serialization.
     */
    protected $hidden = ['search_vector'];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'occurrences_count' => 'integer',
        'users_count' => 'integer',
    ];

    protected static function booted(): void
    {
        // uuid-public-ids: every new issue gets a public uuid identifier
        // going forward; existing rows are backfilled by the cross-repo
        // retrofit migration
        // (2026_07_06_140000_add_uuid_to_nightowl_issues_tables, run against
        // the `nightowl` connection since this table is owned by
        // nightowl/agent, not this repo). Route binding stays on `id` for
        // now (see App\Domains\Issues\README.md) — uuid is additive.
        static::creating(function (Issue $issue) {
            $issue->uuid ??= (string) Str::uuid();
        });
    }

    public function activity()
    {
        return $this->hasMany(IssueActivity::class, 'issue_id')->latest('created_at');
    }

    public function comments()
    {
        return $this->hasMany(IssueComment::class, 'issue_id')->oldest('created_at');
    }
}
