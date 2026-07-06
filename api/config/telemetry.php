<?php

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\CommandRecord;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\MailRecord;
use App\Models\Telemetry\NotificationRecord;
use App\Models\Telemetry\OutgoingRequest;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\Telemetry\ScheduledTask;

/*
|--------------------------------------------------------------------------
| Telemetry resource registry
|--------------------------------------------------------------------------
|
| One generic TelemetryController serves every read-only nightowl_* table
| (mirrors the 12 Filament resources in the old app/ dashboard) instead of
| 12 near-identical controllers. Each entry declares which query-string
| filters are allowed and how they translate to a where() clause.
|
| Filter shapes:
|   - param-driven:  ['column' => 'status', 'op' => '=']
|                     value comes from the query string, e.g. ?status=open
|   - flag:          ['column' => 'status_code', 'op' => '>=', 'value' => 500]
|                     triggered by presence of a truthy query param,
|                     e.g. ?failed=1 — the where() value is fixed by config,
|                     not user input.
|
| `search` (optional): declares how ?q= free-text search is applied for this
| resource, via TelemetryController::applySearch(). Either or both keys:
|   - 'tsvector' => 'search_vector'   matches a generated tsvector column
|                                     (word-stemmed; see the
|                                     nightowl_*_search_vector migrations)
|   - 'trigram'  => ['col1', 'col2']  ILIKE '%q%' over these columns
|                                     (substring match, accelerated by a
|                                     pg_trgm GIN index on each column)
| A resource declaring both matches on either (OR'd together). Prose columns
| (log/exception messages) use tsvector; identifier-like columns (URLs, SQL,
| class names, cache keys) use trigram since a remembered fragment rarely
| lands on a word boundary.
|
| No closures here (this file is safe to `config:cache`).
|
|--------------------------------------------------------------------------
| Trace correlation (Telescope-style "related entries")
|--------------------------------------------------------------------------
|
| Every telemetry row belongs to one "execution" — a request, a queued
| job's attempt, an artisan command, or a scheduled task run. The Nightwatch
| sensor stamps two different kinds of correlation columns depending on
| which side of that relationship a row is on:
|
|   - "Origin" rows (requests, commands, scheduled-tasks, and a processed/
|     failed/released job attempt) carry their own identity in `parent_key`
|     — `trace_id` for requests/commands/scheduled-tasks, but `attempt_id`
|     for a job attempt (a queue worker's `trace_id` spans many job
|     attempts, so `attempt_id` is the per-attempt identity instead).
|   - "Child" rows (queries, cache events, mail, notifications, outgoing
|     requests, logs, exceptions, and a job's *queued* dispatch event) carry
|     `execution_source` (one of 'request'|'command'|'scheduled_task'|'job')
|     and `execution_id`, which equals the origin's `parent_key` value.
|
| `jobs` is deliberately in both lists: the row written when a job is
| dispatched is a child of whatever queued it, while the row written when
| that job actually runs is itself an origin for its own queries/etc.
| Verified against real drained data in nightowl_jobs/nightowl_queries
| rather than assumed from the SDK source, since trace_id/execution_id
| don't always coincide (see TelemetryController::related()).
|
*/

return [

    'resources' => [

        'issues' => [
            'model' => Issue::class,
            'default_sort' => '-last_seen_at',
            'sortable' => ['last_seen_at', 'first_seen_at', 'occurrences_count', 'users_count', 'created_at'],
            'filters' => [
                'status' => ['column' => 'status', 'op' => '='],
                'type' => ['column' => 'type', 'op' => '='],
            ],
            'search' => [
                'tsvector' => 'search_vector',
                'trigram' => ['exception_class'],
            ],
        ],

        'exceptions' => [
            'model' => ExceptionRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at'],
            'filters' => [
                'handled' => ['column' => 'handled', 'op' => '='],
                'unhandled_only' => ['column' => 'handled', 'op' => '=', 'value' => false],
            ],
            'traces_to_parent' => true,
            'search' => [
                'tsvector' => 'search_vector',
                'trigram' => ['class', 'trace', 'file'],
            ],
        ],

        'requests' => [
            'model' => RequestRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration', 'status_code'],
            'filters' => [
                'status_code' => ['column' => 'status_code', 'op' => '='],
                'failed' => ['column' => 'status_code', 'op' => '>=', 'value' => 500],
                'slow' => ['column' => 'duration', 'op' => '>', 'value' => 1000 * 1000],
                'has_exceptions' => ['column' => 'exceptions', 'op' => '>', 'value' => 0],
            ],
            'parent_key' => 'trace_id',
            'parent_source' => 'request',
            'search' => [
                'trigram' => ['url', 'route_name', 'route_path', 'route_action'],
            ],
        ],

        'outgoing-requests' => [
            'model' => OutgoingRequest::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration', 'status_code'],
            'filters' => [
                'failed' => ['column' => 'status_code', 'op' => '>=', 'value' => 400],
            ],
            'traces_to_parent' => true,
            'search' => [
                'trigram' => ['host', 'url'],
            ],
        ],

        'jobs' => [
            'model' => JobRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'status' => ['column' => 'status', 'op' => '='],
            ],
            // A job is both: the dispatch event is a child of whatever
            // queued it, and a processed/failed/released attempt is itself
            // the parent of the queries/etc. that ran during it.
            'traces_to_parent' => true,
            'parent_key' => 'attempt_id',
            'parent_source' => 'job',
            'search' => [
                'trigram' => ['job_class', 'queue'],
            ],
        ],

        'commands' => [
            'model' => CommandRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
            'parent_key' => 'trace_id',
            'parent_source' => 'command',
            'search' => [
                'trigram' => ['class', 'name', 'command'],
            ],
        ],

        'scheduled-tasks' => [
            'model' => ScheduledTask::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'status' => ['column' => 'status', 'op' => '='],
            ],
            'parent_key' => 'trace_id',
            'parent_source' => 'scheduled_task',
            'search' => [
                'trigram' => ['command', 'expression'],
            ],
        ],

        'queries' => [
            'model' => QueryRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'slow' => ['column' => 'duration', 'op' => '>', 'value' => 100 * 1000],
            ],
            'traces_to_parent' => true,
            'search' => [
                'trigram' => ['sql_query', 'file', 'connection'],
            ],
        ],

        'cache-events' => [
            'model' => CacheEvent::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'event_type' => ['column' => 'event_type', 'op' => '='],
            ],
            'traces_to_parent' => true,
            'search' => [
                'trigram' => ['key', 'store'],
            ],
        ],

        'mail' => [
            'model' => MailRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
            'traces_to_parent' => true,
            'search' => [
                'trigram' => ['subject', 'mailable', 'recipients'],
            ],
        ],

        'notifications' => [
            'model' => NotificationRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
            'traces_to_parent' => true,
            'search' => [
                'trigram' => ['notification', 'channel', 'notifiable_type'],
            ],
        ],

        'logs' => [
            'model' => LogRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at'],
            'filters' => [
                'level' => ['column' => 'level', 'op' => '='],
            ],
            'traces_to_parent' => true,
            'search' => [
                'tsvector' => 'search_vector',
            ],
        ],

    ],

];
