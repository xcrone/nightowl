import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { aggregateConfig } from './aggregateConfig'

// Pinned so the buggy relative rendering ("4h ago") is deterministic rather
// than depending on the real instant the suite happens to run at. Only Date is
// faked — timers stay real so mounts/flushes behave normally.
const NOW = '2026-07-16T20:00:00Z'

// Every aggregate list page's only time column used to render relative
// ("2d ago"), which is timezone-independent by design — so flipping the top-bar
// timezone selector visibly did nothing on any list page. These columns must
// render ABSOLUTE and follow the selector.
const TIME_KEYS = ['last_triggered', 'last_finished', 'last_sent', 'last_seen']

// The jobs "Triggered" column is the one exception: jobs store only a finish
// timestamp + duration, so its instant is derived from the row and it stays a
// function format (asserted separately below).
const isDerived = (resource, col) => resource === 'jobs' && col.key === 'last_triggered'

function timeColumns() {
  return Object.entries(aggregateConfig).flatMap(([resource, cfg]) =>
    cfg.columns
      .filter((col) => TIME_KEYS.includes(col.key))
      .map((col) => ({ resource, key: col.key, label: col.label, col })),
  )
}

const opts = { timezone: 'UTC', format: '24h' }

describe('aggregateConfig time columns', () => {
  it('declares a time column on every aggregate resource that has one', () => {
    // Guards the sweep below from silently passing on an empty set.
    expect(timeColumns().length).toBeGreaterThanOrEqual(12)
  })

  it.each(timeColumns().filter(({ resource, col }) => !isDerived(resource, col)))(
    "$resource '$label' ($key) uses the 'datetime' format so it honours the timezone selector",
    ({ col }) => {
      expect(col.format).toBe('datetime')
    },
  )
})

// Every aggregate list page used to open sorted by its volume column
// ('-total'/'-count'/'-requests'), so the most recent activity could sit
// anywhere — often pages deep. Each resource now opens on its own
// latest-timestamp column, descending: newest first.
//
// jobs is the exception: it sorts by '-last_finished', not '-last_triggered'.
// Its "Triggered" column is derived client-side (finish time minus duration,
// see the suite below), so there is no such column for the api to ORDER BY.
const DEFAULT_SORTS = {
  requests: '-last_triggered',
  'outgoing-requests': '-last_triggered',
  jobs: '-last_finished',
  commands: '-last_triggered',
  'scheduled-tasks': '-last_triggered',
  queries: '-last_triggered',
  cache: '-last_triggered',
  mail: '-last_sent',
  notifications: '-last_sent',
  exceptions: '-last_seen',
  users: '-last_seen',
}

const resources = () => Object.keys(aggregateConfig).map((resource) => ({ resource }))

describe('aggregateConfig default sorts', () => {
  it('expects a default sort for every aggregate resource, and no others', () => {
    // Guards the sweep below: a resource added to the config without an entry
    // here (or a renamed key) would otherwise silently go unswept.
    expect(Object.keys(DEFAULT_SORTS).sort()).toEqual(Object.keys(aggregateConfig).sort())
    expect(Object.keys(aggregateConfig)).toHaveLength(11)
  })

  it.each(Object.entries(DEFAULT_SORTS).map(([resource, sort]) => ({ resource, sort })))(
    '$resource opens sorted by $sort (newest activity first)',
    ({ resource, sort }) => {
      expect(aggregateConfig[resource].defaultSort).toBe(sort)
    },
  )

  it.each(resources())(
    "$resource's defaultSort names a column that actually exists",
    ({ resource }) => {
      // Strip the descending '-' prefix: '-last_seen' sorts on the `last_seen`
      // column. A column rename that misses defaultSort would leave the page
      // asking the api to sort by a field it no longer sends.
      const cfg = aggregateConfig[resource]
      const field = cfg.defaultSort.replace(/^-/, '')

      expect(cfg.columns.map((col) => col.key)).toContain(field)
    },
  )
})

describe('aggregateConfig jobs "Triggered" column', () => {
  const column = aggregateConfig.jobs.columns.find((c) => c.key === 'last_triggered')

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(NOW))
  })
  afterEach(() => vi.useRealTimers())

  it('is a function format (its instant is derived from finish time - duration)', () => {
    expect(typeof column.format).toBe('function')
  })

  it('renders the derived instant absolutely, honouring the passed opts', () => {
    // last_duration is microseconds: 5_000_000µs = 5s, so a 15:04:05Z finish
    // means a 15:04:00Z trigger.
    const row = { last_finished: '2026-07-16T15:04:05Z', last_duration: 5_000_000 }

    const out = column.format(row.last_triggered, row, opts)

    expect(out).toContain('15:04:00')
    expect(out).not.toMatch(/ago/)
  })

  it('renders the derived instant in 12h when the opts say so', () => {
    const row = { last_finished: '2026-07-16T15:04:05Z', last_duration: 5_000_000 }

    const out = column.format(row.last_triggered, row, { timezone: 'UTC', format: '12h' })

    // Intl may emit U+202F before the day period, so match the word, not ' PM'.
    expect(out).toMatch(/\bPM\b/i)
    expect(out).toMatch(/0?3:04:00/)
  })

  it('renders an em dash for a row that never finished, rather than an epoch date', () => {
    // triggeredAt() returns null here — absoluteTime(null) must short-circuit
    // instead of new Date(null) => 1970.
    const out = column.format(null, { last_finished: null, last_duration: null }, opts)

    expect(out).toBe('—')
    expect(out).not.toContain('1970')
  })
})
