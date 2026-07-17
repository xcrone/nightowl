import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../../services/api'
import LogsPage from './LogsPage.vue'

const rows = [
  { id: 1, created_at: '2026-07-06T09:33:17Z', source: 'request', level: 'error', message: 'Unhandled exception in request handler' },
  { id: 2, created_at: '2026-07-06T09:30:00Z', source: 'queue', level: 'info', message: 'Job processed' },
]

async function mountPage(appState = {}, impl) {
  if (impl) api.get.mockImplementation(impl)
  else api.get.mockResolvedValue({ data: { data: rows, last_page: 1 } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/dashboard/:appId', component: { template: '<div />' } }],
  })
  await router.push('/dashboard/app1')
  await router.isReady()
  const wrapper = mount(LogsPage, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: { app: { period: '7d', timezone: 'UTC', timeFormat: '24h', ...appState } },
        }),
      ],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('LogsPage', () => {
  it('fetches logs for the period and renders the flat table', async () => {
    const wrapper = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe('/api/apps/app1/logs')
    expect(first[1].params.period).toBe('7d')

    expect(wrapper.text()).toContain('Unhandled exception in request handler')
    expect(wrapper.text()).toContain('request')
    expect(wrapper.text()).toContain('2 Logs')
    // no chart panels on this page
    expect(wrapper.find('canvas').exists()).toBe(false)
  })

  it('re-fetches with a level filter when the dropdown changes', async () => {
    const wrapper = await mountPage()
    const select = wrapper.find('select')
    await select.setValue('error')
    await flushPromises()

    const last = api.get.mock.calls.at(-1)
    expect(last[1].params.level).toBe('error')
  })
})

describe('LogsPage — live mode', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('re-fetches logs on the live interval', async () => {
    await mountPage({ live: true, liveInterval: 3000 })
    expect(api.get.mock.calls.length).toBe(1)

    await vi.advanceTimersByTimeAsync(3000)
    expect(api.get.mock.calls.length).toBe(2)

    await vi.advanceTimersByTimeAsync(3000)
    expect(api.get.mock.calls.length).toBe(3)
  })

  it('renders rows that arrive on a live tick', async () => {
    const fresh = { id: 3, created_at: '2026-07-06T09:35:00Z', source: 'cache', level: 'info', message: 'Cache warmed' }
    let hits = 0
    const impl = () => {
      hits += 1
      if (hits === 1) return Promise.resolve({ data: { data: rows, last_page: 1 } })
      return Promise.resolve({ data: { data: [fresh, ...rows], last_page: 1 } })
    }
    const wrapper = await mountPage({ live: true, liveInterval: 3000 }, impl)
    expect(wrapper.text()).not.toContain('Cache warmed')

    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()

    expect(wrapper.text()).toContain('Cache warmed')
    expect(wrapper.text()).toContain('3 Logs')
  })

  it('preserves the level filter on a live tick', async () => {
    const wrapper = await mountPage({ live: true, liveInterval: 3000 })
    await wrapper.find('select').setValue('error')
    await flushPromises()

    const before = api.get.mock.calls.length
    await vi.advanceTimersByTimeAsync(3000)

    expect(api.get.mock.calls.length).toBe(before + 1)
    const last = api.get.mock.calls.at(-1)
    expect(last[1].params.level).toBe('error')
    expect(last[1].params.period).toBe('7d')
  })
})
