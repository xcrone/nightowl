// Doc-accurate per-app sidebar (docs/pages/app-dashboard.md "Layout").
// `path` is relative to /dashboard/:appId — AppShell prepends the current
// app id. `badge` names a getter on the app store (e.g. open issue count).
//
// Note: docs/README groups Queries + Cache under "Activity" while their
// individual page docs say "Data" — we follow the README grouping here.

export const navGroups = [
  {
    label: null, // top-level, no group header
    items: [
      { key: 'dashboard', label: 'Dashboard', path: '', icon: 'dashboard' },
      { key: 'issues', label: 'Issues', path: 'issues', icon: 'issues', badge: 'openIssues' },
    ],
  },
  {
    label: 'Activity',
    items: [
      { key: 'requests', label: 'Requests', path: 'requests', icon: 'requests' },
      { key: 'jobs', label: 'Jobs', path: 'jobs', icon: 'jobs' },
      { key: 'commands', label: 'Commands', path: 'commands', icon: 'commands' },
      { key: 'scheduled-tasks', label: 'Scheduled Tasks', path: 'scheduled-tasks', icon: 'clock' },
      { key: 'exceptions', label: 'Exceptions', path: 'exceptions', icon: 'exceptions' },
      { key: 'queries', label: 'Queries', path: 'queries', icon: 'queries' },
      { key: 'notifications', label: 'Notifications', path: 'notifications', icon: 'bell' },
      { key: 'mail', label: 'Mail', path: 'mail', icon: 'mail' },
      { key: 'cache', label: 'Cache', path: 'cache', icon: 'cache' },
      { key: 'outgoing-requests', label: 'Outgoing Requests', path: 'outgoing-requests', icon: 'outgoing' },
    ],
  },
  {
    label: 'Monitoring',
    items: [
      { key: 'users', label: 'Users', path: 'users', icon: 'users' },
      { key: 'logs', label: 'Logs', path: 'logs', icon: 'logs' },
      { key: 'health', label: 'Agent Health', path: 'health', icon: 'health' },
    ],
  },
  {
    label: null,
    items: [
      { key: 'data-management', label: 'Data Management', path: 'data-management', icon: 'data' },
      { key: 'settings', label: 'Settings', path: 'settings', icon: 'settings' },
    ],
  },
]

// The 11 telemetry categories offered on the Data Management page — mirrors
// the Activity group + Logs (docs/pages/data-management.md).
export const dataTypes = [
  'requests', 'queries', 'exceptions', 'commands', 'jobs', 'cache-events',
  'mail', 'notifications', 'outgoing-requests', 'scheduled-tasks', 'logs',
]

// Period selector pills (docs: top bar), value → label.
export const periods = [
  { value: '1h', label: '1H' },
  { value: '6h', label: '6H' },
  { value: '24h', label: '24H' },
  { value: '7d', label: '7D' },
  { value: '14d', label: '14D' },
  { value: '30d', label: '30D' },
]
