export function formatDatetime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

// Durations are stored in microseconds throughout, matching the original
// Filament UI (which labelled timing fields "(μs)" rather than converting).
export function formatDuration(microseconds) {
  if (microseconds === null || microseconds === undefined) return '—'
  if (microseconds < 1000) return `${microseconds}μs`
  if (microseconds < 1_000_000) return `${(microseconds / 1000).toFixed(1)}ms`
  return `${(microseconds / 1_000_000).toFixed(2)}s`
}

export function formatValue(value, format) {
  if (format === 'datetime') return formatDatetime(value)
  if (format === 'duration') return formatDuration(value)
  if (format === 'boolean') return value ? 'Yes' : 'No'
  if (value === null || value === undefined || value === '') return '—'
  return value
}
