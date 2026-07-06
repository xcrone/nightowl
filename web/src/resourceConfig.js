// Mirrors api/config/telemetry.php's resource keys and filters. Detail pages
// render every field returned by the API generically (see ResourceDetail.vue)
// so only the list view — where a scannable column subset actually matters —
// needs per-resource configuration here.

export const navGroups = [
  {
    label: 'Overview',
    items: [{ key: 'issues', label: 'Issues' }, { key: 'exceptions', label: 'Exceptions' }],
  },
  {
    label: 'HTTP',
    items: [
      { key: 'requests', label: 'Requests' },
      { key: 'outgoing-requests', label: 'Outgoing Requests' },
    ],
  },
  {
    label: 'Execution',
    items: [
      { key: 'jobs', label: 'Jobs' },
      { key: 'commands', label: 'Commands' },
      { key: 'scheduled-tasks', label: 'Scheduled Tasks' },
    ],
  },
  {
    label: 'Data',
    items: [
      { key: 'queries', label: 'Queries' },
      { key: 'cache-events', label: 'Cache Events' },
    ],
  },
  {
    label: 'Communication',
    items: [
      { key: 'mail', label: 'Mail' },
      { key: 'notifications', label: 'Notifications' },
    ],
  },
  {
    label: 'Logs',
    items: [{ key: 'logs', label: 'Logs' }],
  },
  {
    label: 'Monitoring',
    items: [
      { key: 'rollups', label: 'Trends', route: '/rollups' },
      { key: 'nightowl-users', label: 'Users', route: '/nightowl-users' },
    ],
  },
  {
    label: 'Settings',
    items: [
      { key: 'alert-channels', label: 'Alert Channels', route: '/alert-channels' },
      { key: 'settings', label: 'Settings', route: '/settings' },
    ],
  },
]

// Fields used to build a short human-readable summary of a record for the
// "Related" panel (e.g. "Part of request: GET /api/orders") — kept separate
// from `columns` since list columns include timing/status noise that isn't
// useful for a one-line summary of an unfamiliar record.
export const titleFields = {
  requests: ['method', 'url'],
  'outgoing-requests': ['method', 'url'],
  jobs: ['job_class'],
  commands: ['command'],
  'scheduled-tasks': ['command'],
  queries: ['sql_query'],
  'cache-events': ['event_type', 'key'],
  mail: ['subject'],
  notifications: ['notification'],
  logs: ['level', 'message'],
  exceptions: ['class', 'message'],
}

// Singular form of each resource's label, for "Part of {singular} #{id}"
// copy — not derivable from `label` by stripping a trailing "s" (Queries).
export const singularLabels = {
  requests: 'request',
  'outgoing-requests': 'outgoing request',
  jobs: 'job',
  commands: 'command',
  'scheduled-tasks': 'scheduled task',
  queries: 'query',
  'cache-events': 'cache event',
  mail: 'mail',
  notifications: 'notification',
  logs: 'log',
  exceptions: 'exception',
}

export function summarize(resource, record) {
  if (!record) return ''
  const fields = titleFields[resource] ?? []
  return fields
    .map((field) => record[field])
    .filter((value) => value !== null && value !== undefined && value !== '')
    .join(' — ')
}

export const resources = {
  issues: {
    label: 'Issues',
    columns: [
      { key: 'status', label: 'Status', badge: statusColor },
      { key: 'priority', label: 'Priority' },
      { key: 'exception_class', label: 'Exception' },
      { key: 'occurrences_count', label: 'Occurrences' },
      { key: 'users_count', label: 'Users' },
      { key: 'last_seen_at', label: 'Last seen', format: 'datetime' },
    ],
    filters: [
      { key: 'status', label: 'Status', options: ['open', 'resolved', 'ignored'] },
      { key: 'type', label: 'Type', options: ['exception', 'performance'] },
    ],
    defaultSort: '-last_seen_at',
    searchPlaceholder: 'Search exception class, description…',
  },
  exceptions: {
    label: 'Exceptions',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'class', label: 'Class' },
      { key: 'message', label: 'Message' },
      { key: 'handled', label: 'Handled', format: 'boolean' },
    ],
    filters: [
      { key: 'handled', label: 'Handled', options: ['1', '0'] },
      { key: 'unhandled_only', label: 'Unhandled only', flag: true },
    ],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search class, message, file, trace…',
  },
  requests: {
    label: 'Requests',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'method', label: 'Method', badge: methodColor },
      { key: 'url', label: 'URL' },
      { key: 'status_code', label: 'Status', badge: statusCodeColor },
      { key: 'duration', label: 'Duration', format: 'duration' },
      { key: 'exceptions', label: 'Exceptions', badge: (v) => (v > 0 ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400' : '') },
    ],
    filters: [
      { key: 'status_code', label: 'Status code' },
      { key: 'failed', label: 'Failed (5xx)', flag: true },
      { key: 'slow', label: 'Slow (>1s)', flag: true },
      { key: 'has_exceptions', label: 'Has exceptions', flag: true },
    ],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search URL, route…',
  },
  'outgoing-requests': {
    label: 'Outgoing Requests',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'method', label: 'Method', badge: methodColor },
      { key: 'url', label: 'URL' },
      { key: 'host', label: 'Host' },
      { key: 'status_code', label: 'Status', badge: statusCodeColor },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [{ key: 'failed', label: 'Failed (4xx/5xx)', flag: true }],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search host, URL…',
  },
  jobs: {
    label: 'Jobs',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'job_class', label: 'Job' },
      { key: 'queue', label: 'Queue' },
      { key: 'status', label: 'Status', badge: statusColor },
      { key: 'attempts', label: 'Attempts' },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [{ key: 'status', label: 'Status', options: ['queued', 'processed', 'released', 'failed'] }],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search job class, queue…',
  },
  commands: {
    label: 'Commands',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'command', label: 'Command' },
      {
        key: 'exit_code',
        label: 'Exit code',
        badge: (v) =>
          v === 0
            ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
            : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
      },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search command, class, name…',
  },
  'scheduled-tasks': {
    label: 'Scheduled Tasks',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'command', label: 'Command' },
      { key: 'expression', label: 'Expression' },
      { key: 'status', label: 'Status', badge: statusColor },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [{ key: 'status', label: 'Status', options: ['success', 'failed'] }],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search command, expression…',
  },
  queries: {
    label: 'Queries',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'sql_query', label: 'SQL' },
      { key: 'connection', label: 'Connection' },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [{ key: 'slow', label: 'Slow (>100ms)', flag: true }],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search SQL, file, connection…',
  },
  'cache-events': {
    label: 'Cache Events',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'event_type', label: 'Event', badge: () => 'bg-primary-100 text-primary-700' },
      { key: 'key', label: 'Key' },
      { key: 'store', label: 'Store' },
      { key: 'duration', label: 'Duration', format: 'duration' },
    ],
    filters: [{ key: 'event_type', label: 'Type', options: ['hit', 'missed', 'write', 'forget'] }],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search key, store…',
  },
  mail: {
    label: 'Mail',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'subject', label: 'Subject' },
      { key: 'mailable', label: 'Mailable' },
      { key: 'recipients', label: 'Recipients' },
      { key: 'failed', label: 'Failed', format: 'boolean' },
    ],
    filters: [],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search subject, mailable, recipients…',
  },
  notifications: {
    label: 'Notifications',
    columns: [
      { key: 'created_at', label: 'Time', format: 'datetime' },
      { key: 'notification', label: 'Notification' },
      { key: 'channel', label: 'Channel', badge: () => 'bg-primary-100 text-primary-700' },
      { key: 'notifiable_type', label: 'Notifiable' },
      { key: 'failed', label: 'Failed', format: 'boolean' },
    ],
    filters: [],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search notification, channel…',
  },
  logs: {
    label: 'Logs',
    columns: [
      { key: 'created_at', label: 'Time' },
      { key: 'level', label: 'Level', badge: levelColor },
      { key: 'message', label: 'Message' },
      { key: 'channel', label: 'Channel' },
    ],
    filters: [
      { key: 'level', label: 'Level', options: ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] },
    ],
    defaultSort: '-created_at',
    searchPlaceholder: 'Search log messages…',
  },
}

const BADGE = {
  gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-400',
  green: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400',
  yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
  red: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
  redStrong: 'bg-red-200 text-red-800 dark:bg-red-500/25 dark:text-red-300',
  redStrongest: 'bg-red-300 text-red-900 dark:bg-red-500/40 dark:text-red-200',
  primary: 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400',
}

export function statusColor(status) {
  return {
    open: BADGE.red,
    resolved: BADGE.green,
    ignored: BADGE.gray,
    // Neutral "waiting" state, not a warning — doesn't belong in the brand/amber color.
    queued: BADGE.blue,
    processed: BADGE.green,
    released: BADGE.yellow,
    failed: BADGE.red,
    success: BADGE.green,
  }[status] ?? BADGE.gray
}

export function statusCodeColor(code) {
  if (code >= 500) return BADGE.red
  if (code >= 400) return BADGE.yellow
  // A redirect isn't a caution state, so 3xx gets info-blue rather than the brand/amber color.
  if (code >= 300) return BADGE.blue
  return BADGE.green
}

export function levelColor(level) {
  return {
    debug: BADGE.gray,
    info: BADGE.blue,
    notice: BADGE.blue,
    warning: BADGE.yellow,
    error: BADGE.red,
    critical: BADGE.redStrong,
    alert: BADGE.redStrong,
    emergency: BADGE.redStrongest,
  }[level] ?? BADGE.gray
}

export function methodColor(method) {
  return {
    GET: BADGE.blue,
    HEAD: BADGE.blue,
    OPTIONS: BADGE.gray,
    POST: BADGE.green,
    PUT: BADGE.primary,
    PATCH: BADGE.primary,
    DELETE: BADGE.red,
  }[method?.toUpperCase()] ?? BADGE.gray
}
