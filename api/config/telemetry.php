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
| No closures here (this file is safe to `config:cache`).
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
        ],

        'exceptions' => [
            'model' => ExceptionRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at'],
            'filters' => [
                'handled' => ['column' => 'handled', 'op' => '='],
                'unhandled_only' => ['column' => 'handled', 'op' => '=', 'value' => false],
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
        ],

        'outgoing-requests' => [
            'model' => OutgoingRequest::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration', 'status_code'],
            'filters' => [
                'failed' => ['column' => 'status_code', 'op' => '>=', 'value' => 400],
            ],
        ],

        'jobs' => [
            'model' => JobRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'status' => ['column' => 'status', 'op' => '='],
            ],
        ],

        'commands' => [
            'model' => CommandRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
        ],

        'scheduled-tasks' => [
            'model' => ScheduledTask::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'status' => ['column' => 'status', 'op' => '='],
            ],
        ],

        'queries' => [
            'model' => QueryRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'slow' => ['column' => 'duration', 'op' => '>', 'value' => 100 * 1000],
            ],
        ],

        'cache-events' => [
            'model' => CacheEvent::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [
                'event_type' => ['column' => 'event_type', 'op' => '='],
            ],
        ],

        'mail' => [
            'model' => MailRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
        ],

        'notifications' => [
            'model' => NotificationRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at', 'duration'],
            'filters' => [],
        ],

        'logs' => [
            'model' => LogRecord::class,
            'default_sort' => '-created_at',
            'sortable' => ['created_at'],
            'filters' => [
                'level' => ['column' => 'level', 'op' => '='],
            ],
        ],

    ],

];
