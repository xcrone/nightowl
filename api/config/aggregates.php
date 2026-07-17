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
|   'extra'        [col, ...] or [alias => col, ...] → extra columns carried
|                  through as the group's latest occurrence's value (ordered
|                  by created_at/id desc — not MAX(), which is a meaningless
|                  pick for text/bool columns; see App\Support\AggregateQuery).
|   'representative_bool' [alias => col, ...] → like 'extra', but for a
|                  boolean column that also has a same-named 'count_buckets'
|                  SUM (e.g. exceptions' handled/unhandled) — computed under
|                  an internal alias and promoted over the SUM by
|                  App\Support\AggregateQuery::normalizeRow(), since a group's
|                  badge needs the latest occurrence's flag, not "any
|                  occurrence in the group was true".
|   'duration'     true → emit avg/p95/min/max over the `duration` column.
|   'count_buckets' [alias => [[col, op, val], ...]] → conditional COUNT()s
|                   (conditions AND'd). App\Support\AggregateQuery turns each
|                   into SUM(CASE WHEN … THEN 1 ELSE 0 END).
|   'last'         column to emit MAX() of as `last_<name>` (e.g. last_sent).
|   'sortable'     whitelisted sort keys (metric aliases or group columns).
|   'default_sort' default order — newest-first on the 'last' column
|                  (e.g. '-last_triggered').
|   'search'       columns matched by ?q= (ILIKE).
|   'scope'        page-scope filters that apply (user_id/connection/level).
|   'detail'       true → this aggregate key is clickable and drills into
|                  App\Actions\Aggregates\ShowAggregateDetail
|                  (GET /aggregate/{resource}/{key}). The 8 Activity aggregates
|                  set it; cache (no detail page per docs), users (bespoke), and
|                  exceptions (its own exception-detail endpoint) do not.
|
*/

return [

    'requests' => [
        'model' => RequestRecord::class,
        'group_by' => ['route_path'], 'label' => 'route_path',
        'extra' => ['method'], // carried through as a representative value
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_triggered',
        'count_buckets' => [
            'c2xx' => [['status_code', '<', 400]],
            'c4xx' => [['status_code', '>=', 400], ['status_code', '<', 500]],
            'c5xx' => [['status_code', '>=', 500]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'c5xx', 'route_path', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['route_path', 'route_name', 'url'], 'scope' => ['user_id'],
        'panels' => [
            'requests' => ['total', 'c2xx', 'c4xx', 'c5xx'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'outgoing-requests' => [
        'model' => OutgoingRequest::class,
        'group_by' => ['host'], 'label' => 'host',
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_triggered',
        'count_buckets' => [
            'c2xx' => [['status_code', '<', 400]],
            'c4xx' => [['status_code', '>=', 400], ['status_code', '<', 500]],
            'c5xx' => [['status_code', '>=', 500]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'c5xx', 'host', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['host', 'url'], 'scope' => ['user_id'],
        'panels' => [
            'requests' => ['total', 'c2xx', 'c4xx', 'c5xx'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'jobs' => [
        'model' => JobRecord::class,
        'group_by' => ['job_class'], 'label' => 'job_class',
        // nightowl_jobs has no separate start-timestamp column (created_at is
        // written once, at completion, alongside `duration`); 'extra' carries
        // through the *same* latest occurrence's duration as `last_duration`
        // so the frontend can derive "triggered at" as last_finished -
        // last_duration client-side, rather than mixing the group's latest
        // created_at with an unrelated occurrence's duration.
        'extra' => ['last_duration' => 'duration'],
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_finished',
        'count_buckets' => [
            'queued' => [['status', '=', 'queued']],
            'processed' => [['status', '=', 'processed']],
            'released' => [['status', '=', 'released']],
            'failed' => [['status', '=', 'failed']],
        ],
        'sortable' => ['total', 'avg', 'p95', 'failed', 'job_class', 'last_finished'],
        'default_sort' => '-last_finished',
        'search' => ['job_class', 'queue'], 'scope' => ['user_id'],
        'panels' => [
            'attempts' => ['total', 'queued', 'processed', 'released', 'failed'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'commands' => [
        'model' => CommandRecord::class,
        'group_by' => ['command'], 'label' => 'command',
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_triggered',
        'count_buckets' => [
            'successful' => [['exit_code', '=', 0]],
            'failed' => [['exit_code', '!=', 0]],
        ],
        'sortable' => ['total', 'avg', 'p95', 'failed', 'command', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['command'], 'scope' => [],
        'panels' => [
            'calls' => ['total', 'successful', 'failed'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'scheduled-tasks' => [
        'model' => ScheduledTask::class,
        'group_by' => ['command', 'expression'], 'label' => 'command',
        // Humanize the raw cron `expression` group column into a `schedule` label.
        'cron' => 'expression',
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_triggered',
        'count_buckets' => [
            'processed' => [['status', '=', 'processed']],
            'failed' => [['status', '=', 'failed']],
            'skipped' => [['status', '=', 'skipped']],
        ],
        'sortable' => ['total', 'avg', 'p95', 'command', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['command'], 'scope' => [],
        'panels' => [
            'tasks' => ['total', 'processed', 'failed', 'skipped'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'queries' => [
        'model' => QueryRecord::class,
        'group_by' => ['group_hash'], 'label' => 'sql_query',
        'extra' => ['connection', 'rw' => 'connection_type'],
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_triggered',
        'sortable' => ['total', 'calls', 'avg', 'p95', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['sql_query', 'connection'], 'scope' => ['connection'],
        'panels' => [
            'calls' => ['total'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'cache' => [
        'model' => CacheEvent::class,
        'group_by' => ['key', 'store'], 'label' => 'key',
        'last' => 'created_at', 'last_alias' => 'last_triggered',
        'count_buckets' => [
            'hits' => [['event_type', '=', 'hit']],
            'misses' => [['event_type', '=', 'missed']],
            'writes' => [['event_type', '=', 'write']],
            'deletes' => [['event_type', '=', 'forget']],
            'failures' => [['event_type', '=', 'failed']],
        ],
        'sortable' => ['total', 'hits', 'misses', 'writes', 'deletes', 'key', 'last_triggered'],
        'default_sort' => '-last_triggered',
        'search' => ['key', 'store'], 'scope' => [],
        // 'failures.delete' has no distinct column in the raw stream (a single
        // 'failed' event_type, no failing-operation flag), so it resolves to 0;
        // 'write' carries the total failed count. See IndexAggregate notes.
        'panels' => [
            'events' => ['hits', 'misses', 'writes', 'deletes'],
            'failures' => ['write' => 'failures', 'delete' => 'delete_failures'],
        ],
    ],

    'mail' => [
        'model' => MailRecord::class,
        'group_by' => ['mailable'], 'label' => 'mailable',
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_sent',
        'sortable' => ['count', 'avg', 'p95', 'last_sent', 'mailable'],
        'default_sort' => '-last_sent',
        'search' => ['mailable', 'subject'], 'scope' => ['user_id'],
        'panels' => [
            'volume' => ['total'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'notifications' => [
        'model' => NotificationRecord::class,
        'group_by' => ['notification'], 'label' => 'notification',
        'duration' => true, 'detail' => true, 'last' => 'created_at', 'last_alias' => 'last_sent',
        // channel values collected distinct per group by App\Support\AggregateQuery.
        'collect_distinct' => ['channels' => 'channel'],
        'sortable' => ['count', 'avg', 'p95', 'last_sent', 'notification'],
        'default_sort' => '-last_sent',
        'search' => ['notification', 'channel'], 'scope' => ['user_id'],
        'panels' => [
            'volume' => ['total'],
            'duration' => ['min', 'max', 'avg', 'p95'],
        ],
    ],

    'exceptions' => [
        'model' => ExceptionRecord::class,
        'group_by' => ['class'], 'label' => 'class',
        'extra' => ['message', 'source' => 'execution_source'],
        // The list badge shows one handled/unhandled status per group, which
        // must match the group's *latest* occurrence (what the exception-group
        // detail page shows) — not "any occurrence in the group was handled",
        // which is what naively reusing the count_buckets SUM below as a
        // boolean would give. See App\Support\AggregateQuery.
        'representative_bool' => ['handled'],
        'count_buckets' => [
            'handled' => [['handled', '=', true]],
            'unhandled' => [['handled', '=', false]],
        ],
        'distinct_count' => ['users' => 'user_id'],
        'last' => 'created_at', 'last_alias' => 'last_seen',
        'sortable' => ['count', 'users', 'last_seen', 'class'],
        'default_sort' => '-last_seen',
        'search' => ['class', 'message'], 'scope' => ['user_id'],
        'panels' => [
            'occurrences' => ['total', 'handled', 'unhandled'],
        ],
    ],

    // users aggregate is bespoke (correlates requests + jobs + exceptions per
    // user_id); App\Actions\Aggregates\IndexAggregate::users() handles it
    // directly. Listed here for routing/whitelisting only.
    'users' => [
        'source' => 'bespoke',
        'sortable' => ['requests', 'queued_jobs', 'exceptions', 'last_seen', 'user_id'],
        'default_sort' => '-last_seen',
        'search' => ['user_id'], 'scope' => [],
    ],

];
