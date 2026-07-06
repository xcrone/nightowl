import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import {
  formatDuration,
  formatDurationColor,
  formatPercent,
  relativeTime,
  absoluteTime,
  formatValue,
} from './format'

describe('formatDuration', () => {
  it('renders sub-millisecond durations in microseconds', () => {
    expect(formatDuration(500)).toBe('500µs')
    expect(formatDuration(0)).toBe('0µs')
  })

  it('renders sub-second durations in milliseconds with two decimals', () => {
    expect(formatDuration(14_310)).toBe('14.31ms')
    expect(formatDuration(2_500)).toBe('2.50ms')
  })

  it('renders durations under ten seconds with two decimals', () => {
    expect(formatDuration(1_570_000)).toBe('1.57s')
  })

  it('renders large durations as whole seconds', () => {
    expect(formatDuration(112_000_000)).toBe('112s')
  })

  it('renders null as an em dash', () => {
    expect(formatDuration(null)).toBe('—')
    expect(formatDuration(undefined)).toBe('—')
  })
})

describe('formatDurationColor', () => {
  it('is empty for fast durations', () => {
    expect(formatDurationColor(1000)).toBe('')
  })

  it('flags amber over 300ms', () => {
    expect(formatDurationColor(400_000)).toContain('amber')
  })

  it('flags red over 1s', () => {
    expect(formatDurationColor(2_000_000)).toContain('red')
  })
})

describe('formatPercent', () => {
  it('appends a percent sign at one decimal', () => {
    expect(formatPercent(12.3)).toBe('12.3%')
    expect(formatPercent(0)).toBe('0.0%')
  })

  it('renders nullish as an em dash', () => {
    expect(formatPercent(null)).toBe('—')
  })
})

describe('relativeTime', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-06T12:00:00Z'))
  })
  afterEach(() => vi.useRealTimers())

  it('renders seconds ago', () => {
    expect(relativeTime('2026-07-06T11:59:06Z')).toBe('54s ago')
  })

  it('renders minutes ago', () => {
    expect(relativeTime('2026-07-06T11:30:00Z')).toBe('30m ago')
  })

  it('renders hours ago', () => {
    expect(relativeTime('2026-07-06T09:00:00Z')).toBe('3h ago')
  })

  it('renders nullish as an em dash', () => {
    expect(relativeTime(null)).toBe('—')
  })
})

describe('absoluteTime', () => {
  it('renders a UTC timestamp when timezone is UTC', () => {
    const out = absoluteTime('2026-07-06T12:00:00Z', { timezone: 'UTC', format: '24h' })
    expect(out).toContain('12:00:00')
  })

  it('renders nullish as an em dash', () => {
    expect(absoluteTime(null)).toBe('—')
  })
})

describe('formatValue', () => {
  it('renders booleans as Yes/No', () => {
    expect(formatValue(true, 'boolean')).toBe('Yes')
    expect(formatValue(false, 'boolean')).toBe('No')
  })

  it('renders empty values as an em dash', () => {
    expect(formatValue('', undefined)).toBe('—')
    expect(formatValue(null, undefined)).toBe('—')
  })

  it('passes through plain values unchanged', () => {
    expect(formatValue('hello', undefined)).toBe('hello')
  })
})
