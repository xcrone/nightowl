import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))
import api from '../../services/api'
import HealthPage from './HealthPage.vue'

const payload = {
  status: 'healthy',
  score: 98,
  last_report_at: new Date().toISOString(),
  instances: [
    { name: 'nw_web-web-1:54', health: 'healthy', ingest_per_s: 12.3, drain_per_s: 11.9, pg_latency_ms: 69, write_queue_pct: 24, cpu_pct: 8, memory_bytes: 106_500_000, reject_pct: 0, last_seen_at: new Date().toISOString() },
  ],
  history: {
    throughput: [{ t: new Date().toISOString(), ingest: 12, drain: 11 }],
    buffer: [{ t: new Date().toISOString(), pending_rows: 5 }],
    pg_latency: [{ t: new Date().toISOString(), ms: 69 }],
    score: [{ t: new Date().toISOString(), score: 98 }],
  },
}

async function mountPage() {
  api.get.mockResolvedValue({ data: payload })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/dashboard/:appId', component: { template: '<div />' } }],
  })
  await router.push('/dashboard/app1')
  await router.isReady()
  const wrapper = mount(HealthPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})

describe('HealthPage', () => {
  it('fetches health for the period and renders the banner + instances', async () => {
    const wrapper = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe('/api/apps/app1/health')
    expect(first[1].params.period).toBe('1h')

    // banner status + score
    expect(wrapper.text()).toContain('healthy')
    expect(wrapper.text()).toContain('98')
    // instance table
    expect(wrapper.text()).toContain('Instances (1)')
    expect(wrapper.text()).toContain('nw_web-web-1:54')
    expect(wrapper.text()).toContain('69 ms')
    // memory formatted MB
    expect(wrapper.text()).toContain('106.5 MB')
    // history charts render
    expect(wrapper.text()).toContain('Performance History')
    expect(wrapper.findAll('.chart').length).toBe(4)
  })
})
