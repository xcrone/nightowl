import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
// Stub the chart canvas so chart.js never touches jsdom's absent 2d context.
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))

import api from '../../services/api'
import { base64UrlEncode } from '../../utils/format'
import AggregateTable from '../../components/AggregateTable.vue'
import AggregateListPage from './AggregateListPage.vue'

const rows = [
  { method: 'GET', route_path: '/orders', c2xx: 40, c4xx: 5, c5xx: 4, total: 49, avg: 14310, p95: 1570000 },
  { method: 'POST', route_path: '/checkout', c2xx: 6, c4xx: 1, c5xx: 0, total: 7, avg: 8000, p95: 30000 },
]
const panels = {
  requests: { total: 56, c2xx: 46, c4xx: 6, c5xx: 4 },
  duration: { min: 1000, max: 2000000, avg: 14310, p95: 1570000 },
}

function respond(url) {
  if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [{ user_id: 'u1', email: 'a@b.c' }] } })
  return Promise.resolve({ data: { data: rows, panels } })
}

async function mountPage(resource = 'requests', impl = respond, appState = {}) {
  api.get.mockImplementation(impl)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId', component: { template: '<div />' } },
      { path: '/dashboard/:appId/users/:userId', component: { template: '<div />' } },
      { path: '/dashboard/:appId/exceptions/:key', name: 'exception-detail', component: { template: '<div />' } },
      { path: '/dashboard/:appId/:resource/:key', name: 'aggregate-detail', component: { template: '<div />' } },
    ],
  })
  await router.push('/dashboard/app1')
  await router.isReady()

  const wrapper = mount(AggregateListPage, {
    props: { resource },
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: { app: { period: '1h', timezone: 'Local', timeFormat: '24h', ...appState } },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

const reqCalls = () => api.get.mock.calls.filter((c) => c[0].includes('/aggregate/requests'))

beforeEach(() => {
  vi.clearAllMocks()
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})

describe('AggregateListPage', () => {
  it('fetches the resource aggregate for the current period and renders rows + panels', async () => {
    const { wrapper } = await mountPage('requests')
    const first = reqCalls()[0]
    expect(first[0]).toBe('/api/apps/app1/aggregate/requests')
    expect(first[1].params.period).toBe('1h')

    expect(wrapper.text()).toContain('/orders')
    expect(wrapper.text()).toContain('/checkout')
    expect(wrapper.text()).toContain('2 Routes')
    // panel headline total from response `panels`
    expect(wrapper.text()).toContain('56')
  })

  it('re-fetches with a sort param when a sortable header is clicked', async () => {
    const { wrapper } = await mountPage('requests')
    // P95 is not the default-sorted column, so first click sorts it descending.
    const p95Header = wrapper.findAll('th').find((th) => th.text().includes('P95'))
    await p95Header.trigger('click')
    await flushPromises()

    const last = reqCalls().at(-1)
    expect(last[1].params.sort).toBe('-p95')
  })

  it('includes the store environment as ?environment= on the fetch', async () => {
    await mountPage('requests', respond, { environment: 'production' })
    const first = reqCalls()[0]
    expect(first[1].params.environment).toBe('production')
  })

  it('omits ?environment= when no environment is selected', async () => {
    await mountPage('requests')
    const first = reqCalls()[0]
    expect(first[1].params.environment).toBeUndefined()
  })

  it('re-fetches with a scope param when the user filter changes', async () => {
    const { wrapper } = await mountPage('requests')
    const select = wrapper.find('select')
    await select.setValue('u1')
    await flushPromises()

    const last = reqCalls().at(-1)
    expect(last[1].params.user_id).toBe('u1')
  })

  it('navigates to the aggregate-detail page (base64url key) when a requests row is clicked', async () => {
    const { wrapper, router } = await mountPage('requests')
    const push = vi.spyOn(router, 'push')
    // first data row is /orders
    const firstRow = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('/orders'))
    await firstRow.trigger('click')
    await flushPromises()

    expect(push).toHaveBeenCalledWith(`/dashboard/app1/requests/${base64UrlEncode('/orders')}`)
    expect(router.currentRoute.value.params.key).toBe(base64UrlEncode('/orders'))
  })

  it('does not navigate when a clicked row has a null group key (broken URL guard)', async () => {
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({
        data: { data: [{ method: 'GET', route_path: null, c2xx: 1, c4xx: 0, c5xx: 0, total: 1, avg: 100, p95: 200 }], panels },
      })
    }
    const { wrapper, router } = await mountPage('requests', impl)
    const push = vi.spyOn(router, 'push')
    const row = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('GET'))
    await row.trigger('click')
    await flushPromises()

    expect(push).not.toHaveBeenCalled()
  })

  it('navigates to the exception-groups detail (keyed on class) from an exceptions row', async () => {
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({
        data: {
          data: [
            { class: 'App\\LogicException', message: 'boom', source: 'Ctrl', handled: false, count: 3, users: 2, last_seen: new Date().toISOString() },
          ],
          panels: { occurrences: { handled: 0, unhandled: 3, total: 3 } },
        },
      })
    }
    const { wrapper, router } = await mountPage('exceptions', impl)
    const push = vi.spyOn(router, 'push')
    const row = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('App\\LogicException'))
    await row.trigger('click')
    await flushPromises()

    expect(push).toHaveBeenCalledWith(`/dashboard/app1/exceptions/${base64UrlEncode('App\\LogicException')}`)
  })

  it('renders a Last Triggered column with an absolute-time cell for a non-jobs resource', async () => {
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({
        data: {
          data: [
            {
              method: 'GET',
              route_path: '/orders',
              c2xx: 40,
              c4xx: 5,
              c5xx: 4,
              total: 49,
              avg: 14310,
              p95: 1570000,
              last_triggered: '2026-07-06T11:30:00Z',
            },
          ],
          panels,
        },
      })
    }
    // Pinned to UTC: the default 'Local' would make the assertion depend on
    // the machine's ambient timezone.
    const { wrapper } = await mountPage('requests', impl, { timezone: 'UTC' })

    const header = wrapper.findAll('th').find((th) => th.text().includes('Last Triggered'))
    expect(header).toBeTruthy()
    expect(wrapper.text()).toContain('11:30:00')
  })

  it('renders both a Triggered and a Finished column for jobs, with Triggered reading earlier than Finished', async () => {
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({
        data: {
          data: [
            {
              job_class: 'App\\Jobs\\ProcessPayment',
              queued: 1,
              processed: 1,
              released: 0,
              failed: 0,
              total: 1,
              avg: 100,
              p95: 200,
              // Finished 11:00Z; duration was 90 minutes, so Triggered derives to 09:30Z.
              last_finished: '2026-07-06T11:00:00Z',
              last_duration: 5_400_000_000, // 90 minutes, in microseconds
            },
          ],
          panels: {
            attempts: { total: 1, processed: 1, released: 0, failed: 0 },
            duration: { min: 100, max: 200, avg: 100, p95: 200 },
          },
        },
      })
    }
    // Pinned to UTC: the default 'Local' would make the assertion depend on
    // the machine's ambient timezone.
    const { wrapper } = await mountPage('jobs', impl, { timezone: 'UTC' })

    const headers = wrapper.findAll('th').map((th) => th.text())
    expect(headers.some((h) => h.includes('Triggered'))).toBe(true)
    expect(headers.some((h) => h.includes('Finished'))).toBe(true)

    // Triggered = last_finished - last_duration (90min), so it derives to 09:30Z
    // against the 11:00Z finish — still proving the derivation, now absolutely.
    expect(wrapper.text()).toContain('09:30:00')
    expect(wrapper.text()).toContain('11:00:00')
  })

  it('client-side filters exceptions by handled/unhandled', async () => {
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({
        data: {
          data: [
            { class: 'ConnectException', message: 'timeout', source: 'ProcessPayment', handled: false, count: 3, users: 2, last_seen: new Date().toISOString() },
            { class: 'ValidationException', message: 'invalid', source: 'Controller', handled: true, count: 5, users: 4, last_seen: new Date().toISOString() },
          ],
          panels: { occurrences: { handled: 5, unhandled: 3, total: 8 } },
        },
      })
    }
    const { wrapper } = await mountPage('exceptions', impl)
    expect(wrapper.text()).toContain('ConnectException')
    expect(wrapper.text()).toContain('ValidationException')

    const handledBtn = wrapper.findAll('button').find((b) => b.text() === 'Handled')
    await handledBtn.trigger('click')
    expect(wrapper.text()).toContain('ValidationException')
    expect(wrapper.text()).not.toContain('ConnectException')
  })
})

describe('AggregateListPage — live mode', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('does not poll while live is off', async () => {
    await mountPage('requests', respond, { live: false, liveInterval: 3000 })
    expect(reqCalls().length).toBe(1)

    await vi.advanceTimersByTimeAsync(30_000)

    expect(reqCalls().length).toBe(1)
  })

  it('re-fetches on the live interval while live is on', async () => {
    await mountPage('requests', respond, { live: true, liveInterval: 3000 })
    expect(reqCalls().length).toBe(1)

    await vi.advanceTimersByTimeAsync(3000)
    expect(reqCalls().length).toBe(2)

    await vi.advanceTimersByTimeAsync(3000)
    expect(reqCalls().length).toBe(3)
  })

  // The whole point of a silent refresh: the table must not blink back to a
  // loading skeleton every few seconds while the user is reading it.
  it('keeps rows rendered and shows no loading skeleton during a live refresh', async () => {
    let resolveTick
    let hits = 0
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      hits += 1
      if (hits === 1) return Promise.resolve({ data: { data: rows, panels } })
      return new Promise((resolve) => { resolveTick = () => resolve({ data: { data: rows, panels } }) })
    }
    const { wrapper } = await mountPage('requests', impl, { live: true, liveInterval: 3000 })
    expect(wrapper.text()).toContain('/orders')

    // Tick fired; its response is still in flight.
    await vi.advanceTimersByTimeAsync(3000)
    expect(wrapper.text()).not.toContain('Loading…')
    expect(wrapper.text()).toContain('/orders')

    resolveTick()
    await flushPromises()
    expect(wrapper.text()).toContain('/orders')
  })

  it('preserves the current sort, search and scope params on a live tick', async () => {
    const { wrapper } = await mountPage('requests', respond, { live: true, liveInterval: 3000 })

    const p95Header = wrapper.findAll('th').find((th) => th.text().includes('P95'))
    await p95Header.trigger('click')
    await flushPromises()

    await wrapper.find('select').setValue('u1')
    await flushPromises()

    await wrapper.find('input[type="text"]').setValue('orders')
    await vi.advanceTimersByTimeAsync(300) // search debounce
    await flushPromises()

    const before = reqCalls().length
    await vi.advanceTimersByTimeAsync(3000)

    expect(reqCalls().length).toBe(before + 1)
    const last = reqCalls().at(-1)
    expect(last[1].params.sort).toBe('-p95')
    expect(last[1].params.q).toBe('orders')
    expect(last[1].params.user_id).toBe('u1')
    expect(last[1].params.period).toBe('1h')
  })

  it('stops polling once the page unmounts', async () => {
    const { wrapper } = await mountPage('requests', respond, { live: true, liveInterval: 3000 })
    await vi.advanceTimersByTimeAsync(3000)
    const settled = reqCalls().length

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(30_000)

    expect(reqCalls().length).toBe(settled)
  })

  it('passes new/changed row keys to AggregateTable as highlightKeys', async () => {
    const fresh = { method: 'GET', route_path: '/refunds', c2xx: 1, c4xx: 0, c5xx: 0, total: 1, avg: 900, p95: 900 }
    let hits = 0
    const impl = (url) => {
      if (url.includes('/aggregate/users')) return Promise.resolve({ data: { data: [] } })
      hits += 1
      if (hits === 1) return Promise.resolve({ data: { data: rows, panels } })
      return Promise.resolve({ data: { data: [fresh, ...rows], panels } })
    }
    const { wrapper } = await mountPage('requests', impl, { live: true, liveInterval: 3000 })

    const table = wrapper.findComponent(AggregateTable)
    // Cold mount: a first paint must not flash every row.
    expect(table.props('highlightKeys')).toEqual([])

    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()

    // requests rowKey is route_path.
    expect(table.props('highlightKeys')).toEqual(['/refunds'])
  })
})
