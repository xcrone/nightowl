import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))
import api from '../../services/api'
import UserDetailPage from './UserDetailPage.vue'

const payload = {
  user: { id: 'user_11', name: 'Priya Nair', email: 'priya.nair11@example.com', last_seen: new Date(Date.now() - 7 * 60 * 1000 - 30 * 1000).toISOString() },
  requests: { total: 42, c2xx: 38, c4xx: 3, c5xx: 1 },
  top_routes: [{ method: 'GET', route_path: '/orders', count: 20 }],
  slowest_routes: [{ method: 'POST', route_path: '/checkout', p95: 1570000 }],
  top_jobs: [{ job_class: 'App\\Jobs\\ProcessPayment', count: 5 }],
}

async function mountPage() {
  api.get.mockResolvedValue({ data: payload })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/dashboard/:appId/users/:userId', component: { template: '<div />' } }],
  })
  await router.push('/dashboard/app1/users/user_11')
  await router.isReady()
  const wrapper = mount(UserDetailPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '7d', timezone: 'UTC', timeFormat: '24h' } } })],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})

describe('UserDetailPage', () => {
  it('fetches the user for the period and renders all panels', async () => {
    const wrapper = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe('/api/apps/app1/users/user_11')
    expect(first[1].params.period).toBe('7d')

    expect(wrapper.text()).toContain('Priya Nair')
    // last_seen renders absolutely in the store timezone (UTC above), so it
    // follows the top-bar toggle. Derived from the payload rather than
    // hardcoded — the fixture's last_seen is relative to Date.now().
    expect(wrapper.text()).toContain('Last seen')
    expect(wrapper.text()).toContain(payload.user.last_seen.slice(11, 19))
    expect(wrapper.text()).toContain('Top Routes')
    expect(wrapper.text()).toContain('/orders')
    expect(wrapper.text()).toContain('Slowest Routes')
    // p95 duration formatted
    expect(wrapper.text()).toContain('1.57s')
    expect(wrapper.text()).toContain('Top Queued Jobs')
    expect(wrapper.text()).toContain('App\\Jobs\\ProcessPayment')
    // requests total
    expect(wrapper.text()).toContain('42')
  })
})

// "Last seen" was relative ("7m ago"), which ignores the top-bar timezone
// selector. It must render absolute and follow the store.
describe('UserDetailPage last seen', () => {
  const LAST_SEEN = '2026-07-16T15:04:05Z'
  // Pinned so the buggy relative rendering is a deterministic "4h ago" rather
  // than "just now"/"Nh ago" depending on the real instant the suite runs at.
  const NOW = '2026-07-16T20:00:00Z'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(NOW))
  })
  afterEach(() => vi.useRealTimers())

  // The panel also renders the raw user JSON (JsonViewer), which contains the
  // literal ISO string — so assert on the "Last seen" line only, never on
  // wrapper.text(), or the raw ISO makes the assertion pass against any code.
  const lastSeenLine = (wrapper) =>
    wrapper.findAll('span').find((s) => s.text().startsWith('Last seen'))

  async function mountTimed(appState) {
    api.get.mockResolvedValue({
      data: { ...payload, user: { ...payload.user, last_seen: LAST_SEEN } },
    })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/dashboard/:appId/users/:userId', component: { template: '<div />' } }],
    })
    await router.push('/dashboard/app1/users/user_11')
    await router.isReady()
    const wrapper = mount(UserDetailPage, {
      global: {
        plugins: [router, createTestingPinia({
          createSpy: vi.fn,
          initialState: { app: { period: '7d', ...appState } },
        })],
      },
    })
    await flushPromises()
    return wrapper
  }

  it('renders Last seen absolutely in the store timezone (UTC)', async () => {
    const wrapper = await mountTimed({ timezone: 'UTC', timeFormat: '24h' })
    const line = lastSeenLine(wrapper)

    expect(line.text()).toContain('15:04:05')
    expect(line.text()).not.toMatch(/ago/)
  })

  it('honours the 12h time format from the store', async () => {
    const wrapper = await mountTimed({ timezone: 'UTC', timeFormat: '12h' })
    const line = lastSeenLine(wrapper)

    expect(line.text()).toMatch(/0?3:04:05/)
    expect(line.text()).toMatch(/\bPM\b/i)
  })

  // Ambient TZ isn't pinned (no TZ in vite.config.js), so guard the contrast.
  it.skipIf(new Date(LAST_SEEN).getTimezoneOffset() === 0)(
    'shifts Last seen when the store timezone flips from UTC to Local',
    async () => {
      const local = lastSeenLine(await mountTimed({ timezone: 'Local', timeFormat: '24h' }))

      expect(local.text()).not.toContain('15:04:05')
      expect(local.text()).not.toMatch(/ago/)
    },
  )
})
