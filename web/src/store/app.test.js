import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
import { useAppStore } from './app'

const LIVE_KEY = 'nightowl-live'
const LIVE_INTERVAL_KEY = 'nightowl-live-interval'

// A real pinia (not createTestingPinia): these assert the actions' real
// persistence side-effects, and stubbed actions would never run.
beforeEach(() => {
  localStorage.clear()
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('live', () => {
  // Live polling is opt-in: an unvisited dashboard must not start hammering
  // the api on its own.
  it('defaults to false when nothing is persisted', () => {
    const app = useAppStore()

    expect(app.live).toBe(false)
  })

  it('hydrates true from the persisted flag', () => {
    localStorage.setItem(LIVE_KEY, '1')

    const app = useAppStore()

    expect(app.live).toBe(true)
  })

  it('hydrates false from a persisted off flag', () => {
    localStorage.setItem(LIVE_KEY, '0')

    const app = useAppStore()

    expect(app.live).toBe(false)
  })

  it('setLive updates the state and persists the flag', () => {
    const app = useAppStore()

    app.setLive(true)
    expect(app.live).toBe(true)
    expect(localStorage.getItem(LIVE_KEY)).toBe('1')

    app.setLive(false)
    expect(app.live).toBe(false)
    expect(localStorage.getItem(LIVE_KEY)).toBe('0')
  })
})

describe('liveInterval', () => {
  it('defaults to 3000 when nothing is persisted', () => {
    const app = useAppStore()

    expect(app.liveInterval).toBe(3000)
  })

  it('hydrates the persisted interval as a number', () => {
    localStorage.setItem(LIVE_INTERVAL_KEY, '10000')

    const app = useAppStore()

    expect(app.liveInterval).toBe(10_000)
  })

  it('setLiveInterval updates the state and persists the value', () => {
    const app = useAppStore()

    app.setLiveInterval(30_000)

    expect(app.liveInterval).toBe(30_000)
    expect(localStorage.getItem(LIVE_INTERVAL_KEY)).toBe('30000')
  })
})
