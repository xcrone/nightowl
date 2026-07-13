// Per-resource configuration for the aggregated list pages
// (pages/app/AggregateListPage.vue). Each entry describes the AggregateTable
// column set, default sort, the optional scope-filter dropdown
// (user/connection), the "N Things" table caption, and a `panels(p)` builder
// that turns the endpoint's `panels` totals into the two stat/chart panels
// shown above the table.
//
// Shape:
//   {
//     resource,           // API path segment: /api/apps/{app}/aggregate/{resource}
//     countLabel,         // "Routes" -> table header "34 Routes"
//     rowKey,             // unique row field
//     defaultSort,        // e.g. '-total'
//     searchable,         // false hides the search box (cache)
//     searchPlaceholder,
//     scope,              // { param, label, source } | null
//     rowLink?,           // (row, appId) => router path
//     columns,            // AggregateTable column defs
//     panels,             // (panelsData) => panel descriptor[]
//   }
//
// scope.source: 'users' fetches the users aggregate for the dropdown; a
// 'rows:<field>' value derives the options from the loaded rows' distinct
// values (e.g. queries -> distinct connections).
//
// panel descriptor kinds:
//   { kind: 'bar', title, labels, datasets, stacked?, total?, breakdown? }
//   { kind: 'stat', title, stats: [{ label, value, class? }], caption? }

import {
  base64UrlEncode,
  formatDuration,
  formatDurationColor,
  formatPercent,
  relativeTime,
} from './utils/format'
import { BADGE, methodColor } from './resourceConfig'

// Chart colours reused from the chart panels' palettes.
const COLOR = { green: '#10b981', amber: '#f59e0b', red: '#ef4444', blue: '#3b82f6', gray: '#9ca3af' }

const num = (v) => Number(v ?? 0)

// Per-item drill-down link: base64url-encode the aggregate's raw group-by key
// into the /dashboard/:appId/:resource/:key detail route (see aggregate-detail
// / exception-detail pages). `keyField` is the row field the api groups by.
// A null/undefined/empty key would base64url-encode to '' and produce a broken
// `/…/{resource}/` route with an empty `:key` segment, so return null instead
// (onRowClick no-ops, matching non-clickable rows).
const isEmptyKey = (v) => v === null || v === undefined || v === ''

// Jobs only store a finish timestamp + duration (no separate "triggered" event),
// so derive it: last_duration arrives in microseconds, Date arithmetic needs ms.
const triggeredAt = (row) =>
  row.last_finished ? new Date(new Date(row.last_finished).getTime() - Number(row.last_duration ?? 0) / 1000) : null

const detailLink = (resource, keyField) => (row, appId) =>
  isEmptyKey(row[keyField])
    ? null
    : `/dashboard/${appId}/${resource}/${base64UrlEncode(row[keyField])}`

// A red pill only once a count is non-zero (failed jobs, 5xx, …).
const redWhenPositive = (v) => (num(v) > 0 ? BADGE.red : '')
// Slow-latency text colour for avg/p95 duration cells.
const durColor = (v) => formatDurationColor(v)

const rwBadge = (v) => (String(v).toUpperCase() === 'WRITE' ? BADGE.yellow : BADGE.blue)
const rwLabel = (v) => String(v ?? 'READ').toUpperCase()
const handledBadge = (v) => (v ? BADGE.green : BADGE.red)
const handledLabel = (v) => (v ? 'Handled' : 'Unhandled')
const channelsBadge = () => BADGE.primary
const channelsLabel = (v) => (Array.isArray(v) ? v.join(', ') : (v ?? '—'))

// --- shared column fragments -------------------------------------------------

const durationCols = () => [
  { key: 'avg', label: 'Avg', format: formatDuration, cellClass: durColor, align: 'right' },
  { key: 'p95', label: 'P95', format: formatDuration, cellClass: durColor, align: 'right' },
]

const statusCols = () => [
  { key: 'c2xx', label: '1/2/3XX', align: 'right' },
  { key: 'c4xx', label: '4XX', align: 'right' },
  { key: 'c5xx', label: '5XX', align: 'right', badge: redWhenPositive },
]

// --- shared panel builders ---------------------------------------------------

function durationPanel(dur = {}, title = 'Duration') {
  const has = dur.min != null || dur.max != null
  return {
    kind: 'stat',
    title,
    stats: [
      { label: 'Avg', value: formatDuration(dur.avg), class: durColor(dur.avg) },
      { label: 'P95', value: formatDuration(dur.p95), class: durColor(dur.p95) },
    ],
    caption: has ? `${formatDuration(dur.min)} – ${formatDuration(dur.max)}` : '',
  }
}

function statusBarPanel(reqs = {}, title = 'Requests') {
  return {
    kind: 'bar',
    title,
    stacked: true,
    labels: [title],
    total: num(reqs.total),
    datasets: [
      { label: '1/2/3XX', data: [num(reqs.c2xx)], backgroundColor: COLOR.green },
      { label: '4XX', data: [num(reqs.c4xx)], backgroundColor: COLOR.amber },
      { label: '5XX', data: [num(reqs.c5xx)], backgroundColor: COLOR.red },
    ],
    breakdown: [
      { label: '1/2/3XX', value: num(reqs.c2xx), color: COLOR.green },
      { label: '4XX', value: num(reqs.c4xx), color: COLOR.amber },
      { label: '5XX', value: num(reqs.c5xx), color: COLOR.red },
    ],
  }
}

export const aggregateConfig = {
  requests: {
    resource: 'requests',
    countLabel: 'Routes',
    rowKey: 'route_path',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search routes…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    rowLink: detailLink('requests', 'route_path'),
    columns: [
      { key: 'method', label: 'Method', badge: methodColor },
      { key: 'route_path', label: 'Path' },
      ...statusCols(),
      { key: 'total', label: 'Total', align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => [statusBarPanel(p.requests), durationPanel(p.duration)],
  },

  'outgoing-requests': {
    resource: 'outgoing-requests',
    countLabel: 'Domains',
    rowKey: 'host',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search hosts…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    rowLink: detailLink('outgoing-requests', 'host'),
    columns: [
      { key: 'host', label: 'Host' },
      ...statusCols(),
      { key: 'total', label: 'Total', align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => [statusBarPanel(p.requests), durationPanel(p.duration)],
  },

  jobs: {
    resource: 'jobs',
    countLabel: 'Jobs',
    rowKey: 'job_class',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search jobs…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    rowLink: detailLink('jobs', 'job_class'),
    columns: [
      { key: 'job_class', label: 'Job Class' },
      { key: 'queued', label: 'Queued', align: 'right' },
      { key: 'processed', label: 'Processed', align: 'right' },
      { key: 'released', label: 'Released', align: 'right' },
      { key: 'failed', label: 'Failed', align: 'right', badge: redWhenPositive },
      { key: 'total', label: 'Total', align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Triggered', format: (v, row) => relativeTime(triggeredAt(row)), align: 'right' },
      { key: 'last_finished', label: 'Finished', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const a = p.attempts ?? p.jobs ?? {}
      return [
        {
          kind: 'bar',
          title: 'Attempts',
          stacked: true,
          labels: ['Attempts'],
          total: num(a.total),
          datasets: [
            { label: 'Processed', data: [num(a.processed)], backgroundColor: COLOR.green },
            { label: 'Released', data: [num(a.released)], backgroundColor: COLOR.amber },
            { label: 'Failed', data: [num(a.failed)], backgroundColor: COLOR.red },
          ],
          breakdown: [
            { label: 'Processed', value: num(a.processed), color: COLOR.green },
            { label: 'Released', value: num(a.released), color: COLOR.amber },
            { label: 'Failed', value: num(a.failed), color: COLOR.red },
          ],
        },
        durationPanel(p.duration),
      ]
    },
  },

  commands: {
    resource: 'commands',
    countLabel: 'Commands',
    rowKey: 'command',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search commands…',
    scope: null,
    rowLink: detailLink('commands', 'command'),
    columns: [
      { key: 'command', label: 'Command' },
      { key: 'successful', label: 'Successful', align: 'right' },
      { key: 'failed', label: 'Failed', align: 'right', badge: redWhenPositive },
      { key: 'total', label: 'Total', align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const c = p.calls ?? p.commands ?? {}
      return [
        {
          kind: 'bar',
          title: 'Calls',
          stacked: true,
          labels: ['Calls'],
          total: num(c.total),
          datasets: [
            { label: 'Successful', data: [num(c.successful)], backgroundColor: COLOR.green },
            { label: 'Failed', data: [num(c.failed)], backgroundColor: COLOR.red },
          ],
          breakdown: [
            { label: 'Successful', value: num(c.successful), color: COLOR.green },
            { label: 'Failed', value: num(c.failed), color: COLOR.red },
          ],
        },
        durationPanel(p.duration),
      ]
    },
  },

  'scheduled-tasks': {
    resource: 'scheduled-tasks',
    countLabel: 'Tasks',
    rowKey: 'command',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search tasks…',
    scope: null,
    // Composite group key (command + cron expression) — carry `expression` as a
    // query param so one command with several schedules resolves to the right row.
    // A null/empty command would yield a broken empty `:key` segment, so no-op.
    rowLink: (row, appId) =>
      isEmptyKey(row.command)
        ? null
        : {
            path: `/dashboard/${appId}/scheduled-tasks/${base64UrlEncode(row.command)}`,
            query: row.expression ? { expression: row.expression } : {},
          },
    columns: [
      { key: 'command', label: 'Command' },
      { key: 'schedule', label: 'Schedule' },
      { key: 'processed', label: 'Processed', align: 'right' },
      { key: 'failed', label: 'Failed', align: 'right', badge: redWhenPositive },
      { key: 'skipped', label: 'Skipped', align: 'right' },
      { key: 'total', label: 'Total', align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const t = p.tasks ?? p.scheduled_tasks ?? {}
      return [
        {
          kind: 'bar',
          title: 'Scheduled Tasks',
          stacked: true,
          labels: ['Tasks'],
          total: num(t.total),
          datasets: [
            { label: 'Processed', data: [num(t.processed)], backgroundColor: COLOR.green },
            { label: 'Skipped', data: [num(t.skipped)], backgroundColor: COLOR.gray },
            { label: 'Failed', data: [num(t.failed)], backgroundColor: COLOR.red },
          ],
          breakdown: [
            { label: 'Processed', value: num(t.processed), color: COLOR.green },
            { label: 'Skipped', value: num(t.skipped), color: COLOR.gray },
            { label: 'Failed', value: num(t.failed), color: COLOR.red },
          ],
        },
        durationPanel(p.duration),
      ]
    },
  },

  queries: {
    resource: 'queries',
    countLabel: 'Queries',
    rowKey: 'sql_query',
    defaultSort: '-total',
    searchable: true,
    searchPlaceholder: 'Search queries…',
    scope: { param: 'connection', label: 'All Connections', source: 'rows:connection' },
    // Query drill-down keys on the stable `group_hash`, not the SQL text.
    rowLink: detailLink('queries', 'group_hash'),
    columns: [
      { key: 'rw', label: 'R/W', badge: rwBadge, format: rwLabel, sortable: false },
      { key: 'sql_query', label: 'SQL', sortable: false },
      { key: 'connection', label: 'Connection' },
      { key: 'calls', label: 'Calls', align: 'right' },
      { key: 'total', label: 'Total', format: formatDuration, cellClass: durColor, align: 'right' },
      ...durationCols(),
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const c = p.calls ?? p.queries ?? {}
      return [
        {
          kind: 'bar',
          title: 'Calls',
          labels: ['Calls'],
          total: num(c.total),
          datasets: [{ label: 'Queries', data: [num(c.total)], backgroundColor: COLOR.blue }],
          breakdown: [{ label: 'Queries', value: num(c.total), color: COLOR.blue }],
        },
        durationPanel(p.duration),
      ]
    },
  },

  notifications: {
    resource: 'notifications',
    countLabel: 'Notifications',
    rowKey: 'notification',
    defaultSort: '-count',
    searchable: true,
    searchPlaceholder: 'Search notifications…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    rowLink: detailLink('notifications', 'notification'),
    columns: [
      { key: 'notification', label: 'Notification' },
      { key: 'channels', label: 'Channels', badge: channelsBadge, format: channelsLabel, sortable: false },
      { key: 'count', label: 'Count', align: 'right' },
      ...durationCols(),
      { key: 'last_sent', label: 'Last Sent', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const v = p.volume ?? p.notifications ?? {}
      const total = num(v.total ?? v.sent)
      return [
        {
          kind: 'bar',
          title: 'Volume',
          labels: ['Volume'],
          total,
          datasets: [{ label: 'Sent', data: [total], backgroundColor: COLOR.amber }],
          breakdown: [{ label: 'Sent', value: total, color: COLOR.amber }],
        },
        durationPanel(p.duration),
      ]
    },
  },

  mail: {
    resource: 'mail',
    countLabel: 'Mails',
    rowKey: 'mailable',
    defaultSort: '-count',
    searchable: true,
    searchPlaceholder: 'Search mailables…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    rowLink: detailLink('mail', 'mailable'),
    columns: [
      { key: 'mailable', label: 'Mailable' },
      { key: 'count', label: 'Count', align: 'right' },
      ...durationCols(),
      { key: 'last_sent', label: 'Last Sent', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const v = p.volume ?? p.mail ?? {}
      const total = num(v.total ?? v.sent)
      return [
        {
          kind: 'bar',
          title: 'Volume',
          labels: ['Volume'],
          total,
          datasets: [{ label: 'Sent', data: [total], backgroundColor: COLOR.amber }],
          breakdown: [{ label: 'Sent', value: total, color: COLOR.amber }],
        },
        durationPanel(p.duration),
      ]
    },
  },

  cache: {
    resource: 'cache',
    countLabel: 'Keys',
    rowKey: 'key',
    defaultSort: '-total',
    searchable: false,
    searchPlaceholder: '',
    scope: null,
    columns: [
      { key: 'key', label: 'Key' },
      { key: 'hit_rate', label: 'Hit %', format: (v) => formatPercent(v), align: 'right' },
      { key: 'hits', label: 'Hits', align: 'right' },
      { key: 'misses', label: 'Misses', align: 'right' },
      { key: 'writes', label: 'Writes', align: 'right' },
      { key: 'deletes', label: 'Deletes', align: 'right' },
      { key: 'failures', label: 'Failures', align: 'right', badge: redWhenPositive },
      { key: 'total', label: 'Total', align: 'right' },
      { key: 'last_triggered', label: 'Last Triggered', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const e = p.events ?? {}
      const f = p.failures ?? {}
      return [
        {
          kind: 'bar',
          title: 'Events',
          stacked: true,
          labels: ['Events'],
          total: num(e.hits) + num(e.misses) + num(e.writes) + num(e.deletes),
          datasets: [
            { label: 'Hits', data: [num(e.hits)], backgroundColor: COLOR.green },
            { label: 'Misses', data: [num(e.misses)], backgroundColor: COLOR.amber },
            { label: 'Writes', data: [num(e.writes)], backgroundColor: COLOR.blue },
            { label: 'Deletes', data: [num(e.deletes)], backgroundColor: COLOR.red },
          ],
          breakdown: [
            { label: 'Hits', value: num(e.hits), color: COLOR.green },
            { label: 'Misses', value: num(e.misses), color: COLOR.amber },
            { label: 'Writes', value: num(e.writes), color: COLOR.blue },
            { label: 'Deletes', value: num(e.deletes), color: COLOR.red },
          ],
        },
        {
          kind: 'stat',
          title: 'Failures',
          stats: [
            { label: 'Write', value: num(f.write), class: redWhenPositive(f.write) ? 'text-red-600 dark:text-red-400' : '' },
            { label: 'Delete', value: num(f.delete), class: redWhenPositive(f.delete) ? 'text-red-600 dark:text-red-400' : '' },
          ],
        },
      ]
    },
  },

  users: {
    resource: 'users',
    countLabel: 'Users',
    rowKey: 'user_id',
    defaultSort: '-requests',
    searchable: true,
    searchPlaceholder: 'Search users…',
    scope: null,
    rowLink: (row, appId) => `/dashboard/${appId}/users/${row.user_id}`,
    columns: [
      { key: 'user_id', label: 'User', sortable: false, format: (v, row) => `${row.user_id}${row.email ? ` · ${row.email}` : ''}` },
      ...statusCols(),
      { key: 'requests', label: 'Requests', align: 'right' },
      { key: 'queued_jobs', label: 'Queued Jobs', align: 'right' },
      { key: 'exceptions', label: 'Exceptions', align: 'right', badge: redWhenPositive },
      { key: 'last_seen', label: 'Last Seen', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const u = p.users ?? p.authenticated ?? {}
      const total = num(u.total ?? u.authenticated_total)
      const split = p.requests_split ?? p.requests ?? {}
      return [
        {
          kind: 'bar',
          title: 'Authenticated Users',
          labels: ['Users'],
          total,
          datasets: [{ label: 'Users', data: [total], backgroundColor: COLOR.blue }],
          breakdown: [{ label: 'Users', value: total, color: COLOR.blue }],
        },
        {
          kind: 'bar',
          title: 'Requests',
          stacked: true,
          labels: ['Requests'],
          total: num(split.authenticated) + num(split.guest),
          datasets: [
            { label: 'Authenticated', data: [num(split.authenticated)], backgroundColor: COLOR.gray },
            { label: 'Guest', data: [num(split.guest)], backgroundColor: COLOR.amber },
          ],
          breakdown: [
            { label: 'Authenticated', value: num(split.authenticated), color: COLOR.gray },
            { label: 'Guest', value: num(split.guest), color: COLOR.amber },
          ],
        },
      ]
    },
  },

  exceptions: {
    resource: 'exceptions',
    countLabel: 'Exceptions',
    rowKey: 'class',
    defaultSort: '-count',
    searchable: true,
    searchPlaceholder: 'Search exceptions…',
    scope: { param: 'user_id', label: 'All Users', source: 'users' },
    handledFilter: true,
    // Exceptions drill into the error-tracking exception-groups detail, keyed on
    // the exception class — not the generic aggregate/{resource}/{key} page.
    rowLink: detailLink('exceptions', 'class'),
    columns: [
      { key: 'handled', label: 'Status', badge: handledBadge, format: handledLabel, sortable: false },
      { key: 'class', label: 'Exception' },
      { key: 'message', label: 'Message', sortable: false },
      { key: 'source', label: 'Source', sortable: false },
      { key: 'count', label: 'Count', align: 'right' },
      { key: 'users', label: 'Users', align: 'right' },
      { key: 'last_seen', label: 'Last Seen', format: relativeTime, align: 'right' },
    ],
    panels: (p) => {
      const o = p.occurrences ?? p.exceptions ?? {}
      const handled = num(o.handled)
      const unhandled = num(o.unhandled)
      return [
        {
          kind: 'bar',
          title: 'Occurrences',
          stacked: true,
          labels: ['Occurrences'],
          total: num(o.total ?? handled + unhandled),
          datasets: [
            { label: 'Handled', data: [handled], backgroundColor: COLOR.amber },
            { label: 'Unhandled', data: [unhandled], backgroundColor: COLOR.red },
          ],
          breakdown: [
            { label: 'Handled', value: handled, color: COLOR.amber },
            { label: 'Unhandled', value: unhandled, color: COLOR.red },
          ],
        },
        durationPanel(p.duration),
      ]
    },
  },
}
