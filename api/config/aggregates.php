<?php

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\CommandRecord;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\MailRecord;
use App\Models\Telemetry\NotificationRecord;
use App\Models\Telemetry\OutgoingRequest;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\Telemetry\ScheduledTask;

/*
|--------------------------------------------------------------------------
| Aggregated list registry (docs parity)
|--------------------------------------------------------------------------
|
| Drives GET /api/apps/{app}/aggregate/{resource} via
| App\Actions\Aggregates\IndexAggregate (app/Actions/, a cross-cutting
| Action, not a Domain — see api-domain-dev's carve-out). Each list page in
| docs/pages/*-list.md rolls raw telemetry up per key
| (route / job class / command / query shape / host / cache key / mailable /
| notification / user / exception class) into stat panels + a sortable,
| searchable table.
|
| Aggregation is computed **on-the-fly from the raw nightowl_* tables**
| (GROUP BY + Postgres percentile_cont for exact avg/p95), scoped by
| `where app_id = ? and created_at between [from,to]` — the app_id index +
| bounded period window keep it cheap at demo volumes, and it stays
| uniformly app-scoped (unlike the pre-app_id nightowl_*_rollups tables).
|
| No closures (config:cache-safe). Declarative specs only:
|   'model'        raw Eloquent model to GROUP BY.
|   'group_by'     column(s) forming the aggregation key.
|   'label'        column shown as the row's representative label.
|   'duration'     true → emit avg/p95/min/max over the `duration` column.
|   'count_buckets' [alias => [[col, op, val], ...]] → conditional COUNT()s
|                   (conditions AND'd). App\Support\AggregateQuery turns each
|                   into SUM(CASE WHEN … THEN 1 ELSE 0 END).
|   'last'         column to emit MAX() of as `last_<name>` (e.g. last_sent).
|   'sortable'     whitelisted sort keys (metric aliases or group columns).
|   'default_sort' default order (e.g. '-total').
|   'search'       columns matched by ?q= (ILIKE).
|   'scope'        page-scope filters that apply (user_id/connection/level).
|
*/

return [

    'requests' => [
        'model' => RequestRecord::class,
        'group_by' => ['route_path'], 'label' => 'route_path',
        'extra' => ['method'], // carried through as a representative value
        'duration' => true,
        'count_buckets' => [
            'c2xx' => [['status_code', '<', 400]],
            'c4xx' => [['status_code', '>=', 400], ['status_code', '<', 500]],
            'c5xx' => [['status_code', '>=', 500]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'c5xx', 'route_path'],
        'default_sort' => '-total',
        'search' => ['route_path', 'route_name', 'url'], 'scope' => ['user_id'],
    ],

    'outgoing-requests' => [
        'model' => OutgoingRequest::class,
        'group_by' => ['host'], 'label' => 'host',
        'duration' => true,
        'count_buckets' => [
            'c2xx' => [['status_code', '<', 400]],
            'c4xx' => [['status_code', '>=', 400], ['status_code', '<', 500]],
            'c5xx' => [['status_code', '>=', 500]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'c5xx', 'host'],
        'default_sort' => '-total',
        'search' => ['host', 'url'], 'scope' => ['user_id'],
    ],

    'jobs' => [
        'model' => JobRecord::class,
        'group_by' => ['job_class'], 'label' => 'job_class',
        'duration' => true,
        'count_buckets' => [
            'queued' => [['status', '=', 'queued']],
            'processed' => [['status', '=', 'processed']],
            'released' => [['status', '=', 'released']],
            'failed' => [['status', '=', 'failed']],
        ],
        'sortable' => ['total', 'avg', 'p95', 'failed', 'job_class'],
        'default_sort' => '-total',
        'search' => ['job_class', 'queue'], 'scope' => ['user_id'],
    ],

    'commands' => [
        'model' => CommandRecord::class,
        'group_by' => ['command'], 'label' => 'command',
        'duration' => true,
        'count_buckets' => [
            'successful' => [['exit_code', '=', 0]],
            'failed' => [['exit_code', '!=', 0]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'failed', 'command'],
        'default_sort' => '-total',
        'search' => ['command'], 'scope' => [],
    ],

    'scheduled-tasks' => [
        'model' => ScheduledTask::class,
        'group_by' => ['command', 'expression'], 'label' => 'command',
        'duration' => true,
        'count_buckets' => [
            'processed' => [['status', '=', 'processed']],
            'failed' => [['status', '=', 'failed']],
            'skipped' => [['status', '=', 'skipped']],
        ],
        'sortable' => ['total', 'avg', 'p95', 'command'],
        'default_sort' => '-total',
        'search' => ['command'], 'scope' => [],
    ],

    'queries' => [
        'model' => QueryRecord::class,
        'group_by' => ['group_hash'], 'label' => 'sql_query',
        'extra' => ['connection', 'connection_type'],
        'duration' => true,
        'sortable' => ['total', 'calls', 'avg', 'p95'],
        'default_sort' => '-calls',
        'search' => ['sql_query', 'connection'], 'scope' => ['connection'],
    ],

    'cache' => [
        'model' => CacheEvent::class,
        'group_by' => ['key', 'store'], 'label' => 'key',
        'count_buckets' => [
            'hits' => [['event_type', '=', 'hit']],
            'misses' => [['event_type', '=', 'missed']],
            'writes' => [['event_type', '=', 'write']],
            'deletes' => [['event_type', '=', 'forget']],
            'failures' => [['event_type', '=', 'failed']],
        ],
        'sortable' => ['total', 'hits', 'misses', 'writes', 'deletes', 'key'],
        'default_sort' => '-total',
        'search' => ['key', 'store'], 'scope' => [],
    ],

    'mail' => [
        'model' => MailRecord::class,
        'group_by' => ['mailable'], 'label' => 'mailable',
        'duration' => true, 'last' => 'created_at',
        'sortable' => ['count', 'avg', 'p95', 'last_created_at', 'mailable'],
        'default_sort' => '-count',
        'search' => ['mailable', 'subject'], 'scope' => ['user_id'],
    ],

    'notifications' => [
        'model' => NotificationRecord::class,
        'group_by' => ['notification'], 'label' => 'notification',
        'duration' => true, 'last' => 'created_at',
        // channel values collected distinct per group by App\Support\AggregateQuery.
        'collect_distinct' => ['channels' => 'channel'],
        'sortable' => ['count', 'avg', 'p95', 'last_created_at', 'notification'],
        'default_sort' => '-count',
        'search' => ['notification', 'channel'], 'scope' => ['user_id'],
    ],

    'exceptions' => [
        'model' => ExceptionRecord::class,
        'group_by' => ['class'], 'label' => 'class',
        'extra' => ['message', 'execution_source'],
        'count_buckets' => [
            'handled' => [['handled', '=', true]],
            'unhandled' => [['handled', '=', false]],
        ],
        'distinct_count' => ['users' => 'user_id'],
        'last' => 'created_at',
        'sortable' => ['count', 'users', 'last_created_at', 'class'],
        'default_sort' => '-last_created_at',
        'search' => ['class', 'message'], 'scope' => ['user_id'],
    ],

    // users aggregate is bespoke (correlates requests + jobs + exceptions per
    // user_id); App\Actions\Aggregates\IndexAggregate::users() handles it
    // directly. Listed here for routing/whitelisting only.
    'users' => [
        'source' => 'bespoke',
        'sortable' => ['requests', 'queued_jobs', 'exceptions', 'last_seen', 'user_id'],
        'default_sort' => '-requests',
        'search' => ['user_id'], 'scope' => [],
    ],

];
