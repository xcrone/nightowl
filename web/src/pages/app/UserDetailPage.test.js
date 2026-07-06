import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))
import api from '../../services/api'
import UserDetailPage from './UserDetailPage.vue'

const payload = {
  user: { id: 'user_11', name: 'Priya Nair', email: 'priya.nair11@example.com', last_seen: '7m ago' },
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
