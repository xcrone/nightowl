import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import {
  formatDuration,
  formatDurationColor,
  formatPercent,
  relativeTime,
  absoluteTime,
  formatValue,
  base64UrlEncode,
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

describe('base64UrlEncode', () => {
  it('encodes plain ascii aggregate keys', () => {
    expect(base64UrlEncode('GET')).toBe('R0VU')
    expect(base64UrlEncode('/api/orders')).toBe('L2FwaS9vcmRlcnM')
  })

  it('keeps backslashes (FQCNs) out of the path segment', () => {
    // no `+`, `/`, or `=` may survive — RFC 4648 §5 base64url, padding stripped
    const out = base64UrlEncode('App\\Jobs\\ProcessPayment')
    expect(out).toBe('QXBwXEpvYnNcUHJvY2Vzc1BheW1lbnQ')
    expect(out).not.toMatch(/[+/=]/)
  })

  it('is UTF-8 safe for multi-byte and reserved characters', () => {
    expect(base64UrlEncode('café ☕ ~/+slash')).toBe('Y2Fmw6kg4piVIH4vK3NsYXNo')
  })

  it('renders nullish as an empty string', () => {
    expect(base64UrlEncode(null)).toBe('')
    expect(base64UrlEncode(undefined)).toBe('')
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

  // `formatValue` renders every cell of AggregateTable + ResourceDetailPage's
  // related-rows table, and resourceConfig declares ~11 `format: 'datetime'`
  // columns — so the top-bar timezone/time-format toggles have to reach it.
  describe("the 'datetime' format", () => {
    // Deliberately not a round hour: at 15:04:05Z the 24h and 12h renderings
    // differ in the hour itself, so an assertion on '15:04:05' can't pass by
    // accident on a machine whose ambient TZ happens to be UTC.
    const INSTANT = '2026-07-16T15:04:05Z'

    it('honours an explicit UTC timezone', () => {
      const out = formatValue(INSTANT, 'datetime', { timezone: 'UTC', format: '24h' })
      expect(out).toContain('15:04:05')
    })

    it("renders in absoluteTime's style rather than toLocaleString's numeric one", () => {
      const out = formatValue(INSTANT, 'datetime', { timezone: 'UTC', format: '24h' })
      // absoluteTime uses month: 'short' -> "Jul 16, 2026, 15:04:05"
      expect(out).toBe(absoluteTime(INSTANT, { timezone: 'UTC', format: '24h' }))
      expect(out).toContain('Jul')
      expect(out).not.toMatch(/\d{1,2}\/\d{1,2}\/\d{4}/)
    })

    // Only meaningful where the process TZ actually differs from UTC; on a
    // UTC-ambient machine (some CI runners) 'Local' and 'UTC' are legitimately
    // identical, so skip rather than assert something untrue.
    it.skipIf(new Date(INSTANT).getTimezoneOffset() === 0)(
      'renders Local differently from UTC when the process TZ is not UTC',
      () => {
        const local = formatValue(INSTANT, 'datetime', { timezone: 'Local', format: '24h' })
        const utc = formatValue(INSTANT, 'datetime', { timezone: 'UTC', format: '24h' })
        expect(local).not.toBe(utc)
        expect(local).toBe(absoluteTime(INSTANT, { timezone: 'Local', format: '24h' }))
      },
    )

    it('renders an AM/PM marker only for the 12h time format', () => {
      const twelve = formatValue(INSTANT, 'datetime', { timezone: 'UTC', format: '12h' })
      const twentyFour = formatValue(INSTANT, 'datetime', { timezone: 'UTC', format: '24h' })
      // Intl may separate the marker with a narrow no-break space (U+202F).
      expect(twelve).toMatch(/\bPM\b/i)
      expect(twelve).toContain('3:04:05')
      expect(twentyFour).not.toMatch(/\b[AP]M\b/i)
      expect(twentyFour).toContain('15:04:05')
    })

    it('still renders with no options object (defaults, no throw)', () => {
      const out = formatValue(INSTANT, 'datetime')
      expect(() => formatValue(INSTANT, 'datetime')).not.toThrow()
      // Defaults mirror absoluteTime's: Local + 24h.
      expect(out).toBe(absoluteTime(INSTANT, { timezone: 'Local', format: '24h' }))
      expect(out).toMatch(/\d{1,2}:\d{2}:\d{2}/)
      expect(out).not.toMatch(/\b[AP]M\b/i)
    })

    it('renders nullish as an em dash', () => {
      expect(formatValue(null, 'datetime', { timezone: 'UTC', format: '24h' })).toBe('—')
      expect(formatValue(undefined, 'datetime')).toBe('—')
    })
  })

  describe("the 'relative' format", () => {
    beforeEach(() => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2026-07-06T12:00:00Z'))
    })
    afterEach(() => vi.useRealTimers())

    it('is unaffected by a timezone option', () => {
      expect(formatValue('2026-07-06T11:59:06Z', 'relative', { timezone: 'UTC', format: '24h' })).toBe('54s ago')
      expect(formatValue('2026-07-06T11:59:06Z', 'relative', { timezone: 'Local', format: '12h' })).toBe('54s ago')
      expect(formatValue('2026-07-06T11:59:06Z', 'relative')).toBe('54s ago')
    })
  })
})
