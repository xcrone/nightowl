// Durations arrive from the API in microseconds (fields not ending in `_ms`).
// Rendered in the compact "14.31ms" / "1.57s" / "112s" / "0µs" style the
// reference dashboard uses.
export function formatDuration(microseconds) {
  if (microseconds === null || microseconds === undefined) return '—'
  const us = Number(microseconds)
  if (!Number.isFinite(us)) return '—'
  if (us < 1000) return `${Math.round(us)}µs`
  if (us < 1_000_000) return `${(us / 1000).toFixed(2)}ms`
  const s = us / 1_000_000
  if (s < 10) return `${s.toFixed(2)}s`
  return `${Math.round(s)}s`
}

// Tailwind text-color class flagging slow durations (amber >300ms, red >1s).
export function formatDurationColor(microseconds) {
  const us = Number(microseconds)
  if (!Number.isFinite(us)) return ''
  if (us >= 1_000_000) return 'text-red-600 dark:text-red-400'
  if (us >= 300_000) return 'text-amber-600 dark:text-amber-400'
  return ''
}

// Human-readable byte size ("812 KB", "272 MB", "1.4 GB"). Uses 1024-based
// units to mirror Postgres' pg_size_pretty (the Storage tab's footprint numbers
// come straight from pg_total_relation_size).
export function formatBytes(bytes) {
  if (bytes === null || bytes === undefined || bytes === '') return '—'
  const n = Number(bytes)
  if (!Number.isFinite(n)) return '—'
  if (n < 1024) return `${Math.round(n)} B`
  const units = ['KB', 'MB', 'GB', 'TB', 'PB']
  let value = n / 1024
  let i = 0
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024
    i++
  }
  return `${value >= 100 ? Math.round(value) : value.toFixed(1)} ${units[i]}`
}

// value is already a percentage (e.g. error_rate 12.3), not a 0..1 fraction.
export function formatPercent(value, decimals = 1) {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${n.toFixed(decimals)}%`
}

// "54s ago" style relative timestamp (docs: org-dashboard live freshness).
export function relativeTime(iso) {
  if (!iso) return '—'
  const then = new Date(iso).getTime()
  if (Number.isNaN(then)) return '—'
  const seconds = Math.round((Date.now() - then) / 1000)
  if (seconds < 0) return 'just now'
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  const months = Math.floor(days / 30)
  if (months < 12) return `${months}mo ago`
  return `${Math.floor(months / 12)}y ago`
}

// Absolute timestamp honouring the top-bar timezone ("Local"/"UTC") and
// time-format ("24h"/"12h") toggles held in the app store.
export function absoluteTime(iso, { timezone = 'Local', format = '24h' } = {}) {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  const opts = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: format === '12h',
  }
  if (timezone === 'UTC') opts.timeZone = 'UTC'
  return new Intl.DateTimeFormat(undefined, opts).format(date)
}

// Base64url-encode an aggregate key (RFC 4648 §5): standard base64 with
// `+`→`-`, `/`→`_`, and `=` padding stripped. UTF-8 safe — the raw key can hold
// slashes, backslashes, spaces, or SQL, so it's TextEncoder'd to bytes first.
// Mirrors the api's `App\Support\AggregateKey::encode`; drives the per-item
// drill-down route params (aggregate detail + exception detail).
export function base64UrlEncode(value) {
  if (value === null || value === undefined) return ''
  const bytes = new TextEncoder().encode(String(value))
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

// `opts` carries the top-bar timezone/time-format toggles for the `datetime`
// format; callers rendering timestamps must pass them through from the app
// store, or cells fall back to Local/24h and ignore the selector.
export function formatValue(value, format, opts = {}) {
  if (format === 'datetime') return absoluteTime(value, opts)
  if (format === 'duration') return formatDuration(value)
  if (format === 'percent') return formatPercent(value)
  if (format === 'relative') return relativeTime(value)
  if (format === 'boolean') return value ? 'Yes' : 'No'
  if (value === null || value === undefined || value === '') return '—'
  return value
}
