import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
// Stub the chart canvas so chart.js never touches jsdom's absent 2d context.
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))

import api from '../../services/api'
import { base64UrlEncode } from '../../utils/format'
import { useAppStore } from '../../store/app'
import AggregateDetailPage from './AggregateDetailPage.vue'

const KEY = base64UrlEncode('/api/orders')

const payload = {
  from: 'a', to: 'b', period: '1h',
  resource: 'requests',
  key: KEY,
  label: '/api/orders',
  meta: { method: 'GET' },
  panels: {
    requests: { total: 56, c2xx: 46, c4xx: 6, c5xx: 4 },
    duration: { min: 1000, max: 2000000, avg: 14310, p95: 1570000 },
  },
  percentiles: { avg: 14310, p50: 9000, p95: 1570000, p99: 2500000 },
  occurrences: {
    // Occurrence rows are RAW Eloquent models — the status lives in the real DB
    // column `status_code`, not a fabricated `response_status`.
    data: [
      { id: 1, created_at: new Date().toISOString(), method: 'GET', route_path: '/api/orders', status_code: 200, duration: 14310 },
      { id: 2, created_at: new Date().toISOString(), method: 'GET', route_path: '/api/orders', status_code: 503, duration: 1570000 },
    ],
    current_page: 1, last_page: 2, per_page: 25, total: 56,
  },
}

async function mountPage(resource = 'requests', key = KEY, impl) {
  api.get.mockImplementation(impl ?? (() => Promise.resolve({ data: payload })))
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId/:resource/:key', name: 'aggregate-detail', component: AggregateDetailPage },
    ],
  })
  await router.push(`/dashboard/app1/${resource}/${key}`)
  await router.isReady()
  const wrapper = mount(AggregateDetailPage, {
    global: {
      plugins: [
        router,
        createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})

describe('AggregateDetailPage', () => {
  it('fetches the per-key aggregate for the period and renders the header + occurrences', async () => {
    const { wrapper } = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe(`/api/apps/app1/aggregate/requests/${KEY}`)
    expect(first[1].params.period).toBe('1h')

    // header: method badge + label
    expect(wrapper.text()).toContain('GET')
    expect(wrapper.text()).toContain('/api/orders')
    // volume panel total + occurrence count header
    expect(wrapper.text()).toContain('56 Requests')
  })

  it('defaults the duration headline to P95 and switches it via the percentile toggle', async () => {
    const { wrapper } = await mountPage()
    const headline = () => wrapper.get('[data-test="duration-headline"]').text()
    // p95 = 1_570_000µs -> 1.57s
    expect(headline()).toBe('1.57s')

    const p99 = wrapper.findAll('button').find((b) => b.text() === 'P99')
    await p99.trigger('click')
    // p99 = 2_500_000µs -> 2.50s
    expect(headline()).toBe('2.50s')
  })

  it('re-fetches with ?bucket= when a duration filter chip is clicked', async () => {
    const { wrapper } = await mountPage()
    const chip = wrapper.findAll('button').find((b) => b.text().startsWith('≥ P95'))
    await chip.trigger('click')
    await flushPromises()

    const last = api.get.mock.calls.at(-1)
    expect(last[1].params.bucket).toBe('p95')
  })

  it('re-fetches with ?outcome= when a resource outcome chip is clicked', async () => {
    const { wrapper } = await mountPage()
    const chip = wrapper.findAll('button').find((b) => b.text().startsWith('5XX'))
    await chip.trigger('click')
    await flushPromises()

    const last = api.get.mock.calls.at(-1)
    expect(last[1].params.outcome).toBe('c5xx')
  })

  it('passes the scheduled-tasks expression query param through to the fetch', async () => {
    const tasksPayload = { ...payload, resource: 'scheduled-tasks', meta: { schedule: 'Daily', expression: '0 0 * * *' } }
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/dashboard/:appId/:resource/:key', name: 'aggregate-detail', component: AggregateDetailPage }],
    })
    api.get.mockResolvedValue({ data: tasksPayload })
    const cmdKey = base64UrlEncode('config:cache')
    await router.push({ path: `/dashboard/app1/scheduled-tasks/${cmdKey}`, query: { expression: '0 0 * * *' } })
    await router.isReady()
    mount(AggregateDetailPage, {
      global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })] },
    })
    await flushPromises()

    const first = api.get.mock.calls[0]
    expect(first[0]).toBe(`/api/apps/app1/aggregate/scheduled-tasks/${cmdKey}`)
    expect(first[1].params.expression).toBe('0 0 * * *')
  })

  it('renders the requests Status cell from the raw status_code column', async () => {
    const { wrapper } = await mountPage()
    const body = wrapper.find('tbody').text()
    // Real column values render (not em dashes from a mismatched key).
    expect(body).toContain('200')
    expect(body).toContain('503')
    expect(body).not.toContain('—')
  })

  it('renders queries Source/Type from the raw execution_source/connection_type columns', async () => {
    const queriesPayload = {
      ...payload,
      resource: 'queries',
      label: 'select * from orders',
      meta: { connection: 'pgsql' },
      occurrences: {
        data: [
          { id: 1, created_at: new Date().toISOString(), execution_source: 'OrderController', connection_type: 'read', connection: 'pgsql', duration: 14310 },
        ],
        current_page: 1, last_page: 1, per_page: 25, total: 1,
      },
    }
    const key = base64UrlEncode('grouphash123')
    const { wrapper } = await mountPage('queries', key, () => Promise.resolve({ data: queriesPayload }))
    const body = wrapper.find('tbody').text()
    expect(body).toContain('OrderController')
    expect(body).toContain('read')
  })

  it('ignores a stale response that resolves after a newer one (latest-wins)', async () => {
    const resolvers = []
    api.get.mockImplementation(() => new Promise((res) => resolvers.push(res)))
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/dashboard/:appId/:resource/:key', name: 'aggregate-detail', component: AggregateDetailPage }],
    })
    await router.push(`/dashboard/app1/requests/${KEY}`)
    await router.isReady()
    const wrapper = mount(AggregateDetailPage, {
      global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })] },
    })
    await flushPromises()

    // First load in flight; changing the period fires a second, newer load.
    const store = useAppStore()
    store.period = '24h'
    await flushPromises()
    expect(resolvers.length).toBe(2)

    // Newer (2nd) response resolves first…
    resolvers[1]({ data: { ...payload, label: '/api/fresh' } })
    await flushPromises()
    // …then the stale (1st) response resolves late and must NOT overwrite it.
    resolvers[0]({ data: { ...payload, label: '/api/stale' } })
    await flushPromises()

    expect(wrapper.text()).toContain('/api/fresh')
    expect(wrapper.text()).not.toContain('/api/stale')
  })
})
