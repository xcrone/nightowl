import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))

import { useLivePoll } from './useLivePoll'
import { useAppStore } from '../store/app'

// The composable is called from a page's setup(), so exercise it through a
// throwaway host component rather than calling it bare (it owns mount/unmount
// lifecycle: the interval and the visibilitychange listener).
function mountPoll(fn, appState = {}) {
  const pinia = createTestingPinia({
    createSpy: vi.fn,
    initialState: { app: { live: false, liveInterval: 3000, ...appState } },
  })
  const wrapper = mount(
    {
      template: '<div />',
      setup() {
        useLivePoll(fn)
        return {}
      },
    },
    { global: { plugins: [pinia] } },
  )
  return { wrapper, app: useAppStore(pinia) }
}

// jsdom's `hidden` lives on Document.prototype; shadow it with an own property
// and fire the event the browser would fire.
function setHidden(hidden) {
  Object.defineProperty(document, 'hidden', { configurable: true, get: () => hidden })
  document.dispatchEvent(new Event('visibilitychange'))
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  delete document.hidden
})

describe('useLivePoll', () => {
  it('invokes fn on every interval tick while live', async () => {
    const fn = vi.fn().mockResolvedValue()
    mountPoll(fn, { live: true, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(2)
  })

  it('never invokes fn while paused', async () => {
    const fn = vi.fn().mockResolvedValue()
    mountPoll(fn, { live: false, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(30_000)
    expect(fn).not.toHaveBeenCalled()
  })

  // The page owns its own initial load (the period watcher); a mount-time
  // invoke would double-fetch every page load.
  it('does not invoke fn on mount', async () => {
    const fn = vi.fn().mockResolvedValue()
    mountPoll(fn, { live: true, liveInterval: 3000 })

    await flushPromises()
    expect(fn).not.toHaveBeenCalled()
  })

  it('clears the interval and removes the visibilitychange listener on unmount', async () => {
    const remove = vi.spyOn(document, 'removeEventListener')
    const fn = vi.fn().mockResolvedValue()
    const { wrapper } = mountPoll(fn, { live: true, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    wrapper.unmount()
    expect(remove).toHaveBeenCalledWith('visibilitychange', expect.any(Function))

    // A leaked interval would tick; a leaked listener would resurrect one.
    await vi.advanceTimersByTimeAsync(9000)
    setHidden(false)
    await vi.advanceTimersByTimeAsync(9000)
    expect(fn).toHaveBeenCalledTimes(1)
  })

  it('skips a tick while the previous fetch is still in flight', async () => {
    let resolveFetch
    const fn = vi.fn(() => new Promise((resolve) => { resolveFetch = resolve }))
    mountPoll(fn, { live: true, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    // Tick lands while the first promise is unresolved — no overlap/pile-up.
    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    resolveFetch()
    await flushPromises()

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(2)
  })

  it('pauses while the document is hidden and resumes when it becomes visible', async () => {
    const fn = vi.fn().mockResolvedValue()
    mountPoll(fn, { live: true, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    setHidden(true)
    await vi.advanceTimersByTimeAsync(9000)
    expect(fn).toHaveBeenCalledTimes(1)

    setHidden(false)
    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(2)
  })

  it('restarts the timer at the new period when liveInterval changes', async () => {
    const fn = vi.fn().mockResolvedValue()
    const { app } = mountPoll(fn, { live: true, liveInterval: 3000 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    app.liveInterval = 10_000
    await flushPromises()

    // The old 3s cadence must be gone, not running alongside the new one.
    await vi.advanceTimersByTimeAsync(3000)
    expect(fn).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(7000)
    expect(fn).toHaveBeenCalledTimes(2)
  })
})
