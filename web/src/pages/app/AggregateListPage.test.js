import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
// Stub the chart canvas so chart.js never touches jsdom's absent 2d context.
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))

import api from '../../services/api'
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

async function mountPage(resource = 'requests', impl = respond) {
  api.get.mockImplementation(impl)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId', component: { template: '<div />' } },
      { path: '/dashboard/:appId/users/:userId', component: { template: '<div />' } },
    ],
  })
  await router.push('/dashboard/app1')
  await router.isReady()

  const wrapper = mount(AggregateListPage, {
    props: { resource },
    global: {
      plugins: [
        router,
        createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'Local', timeFormat: '24h' } } }),
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

  it('re-fetches with a scope param when the user filter changes', async () => {
    const { wrapper } = await mountPage('requests')
    const select = wrapper.find('select')
    await select.setValue('u1')
    await flushPromises()

    const last = reqCalls().at(-1)
    expect(last[1].params.user_id).toBe('u1')
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
