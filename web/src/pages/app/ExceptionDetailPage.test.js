import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
vi.mock('vue-chartjs', () => ({ Bar: { template: '<div class="chart" />' }, Line: { template: '<div class="chart" />' } }))

import api from '../../services/api'
import { base64UrlEncode } from '../../utils/format'
import { useAppStore } from '../../store/app'
import ExceptionDetailPage from './ExceptionDetailPage.vue'

const KEY = base64UrlEncode('App\\LogicException')

const payload = {
  from: 'a', to: 'b', period: '1h',
  key: KEY,
  class: 'App\\LogicException',
  message: 'Undefined array key "total"',
  handled: false,
  file: '/app/Http/Controllers/OrderController.php', line: 44,
  php_version: '8.4.15', laravel_version: '12.43.1',
  stack_frames: [{ index: 0, file: '/app/Http/Controllers/OrderController.php', line: 44, function: 'handle' }],
  issue: { id: 7, uuid: 'abc-uuid-9' },
  panels: { occurrences: { total: 21, handled: 5, unhandled: 16 } },
  info: {
    first_seen: new Date().toISOString(), last_seen: new Date().toISOString(),
    first_reported_in: 'request: POST https://app.example.com/api/orders',
    impacted_users: 12, occurrences_24h: 3, occurrences_7d: 21, servers: ['web-1', 'web-2'],
  },
  occurrences: {
    data: [{ id: 1, created_at: new Date().toISOString(), message: 'Undefined array key "total"', handled: false, user_id: 'u1' }],
    current_page: 1, last_page: 1, per_page: 25, total: 21,
  },
}

async function mountPage(data = payload, key = KEY) {
  api.get.mockResolvedValue({ data })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId/exceptions/:key', name: 'exception-detail', component: ExceptionDetailPage },
      { path: '/dashboard/:appId/issues/:id', name: 'issue-detail', component: { template: '<div />' } },
      { path: '/dashboard/:appId/users/:userId', name: 'user-detail', component: { template: '<div />' } },
    ],
  })
  await router.push(`/dashboard/app1/exceptions/${key}`)
  await router.isReady()
  const wrapper = mount(ExceptionDetailPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
  window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})

describe('ExceptionDetailPage', () => {
  it('fetches the exception group for the period and renders class/message/stack + runtime', async () => {
    const { wrapper } = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe(`/api/apps/app1/exception-groups/${KEY}`)
    expect(first[1].params.period).toBe('1h')

    expect(wrapper.text()).toContain('Undefined array key "total"')
    expect(wrapper.text()).toContain('App\\LogicException')
    expect(wrapper.text()).toContain('Unhandled')
    expect(wrapper.text()).toContain('Laravel 12.43.1')
    expect(wrapper.text()).toContain('PHP 8.4.15')
    // origin file:line + stack frame
    expect(wrapper.text()).toContain('/app/Http/Controllers/OrderController.php:44')
    expect(wrapper.text()).toContain('handle')
    // info block
    expect(wrapper.text()).toContain('impacted_users')
  })

  it('links "View issue" to the deduplicated issue by its integer id (id-based route)', async () => {
    // The Issue model binds route-model-binding on its integer id, not uuid, and
    // the issues list/detail link by id — so this link must use id (7), not the
    // additive uuid, or the target route 404s.
    const { wrapper } = await mountPage()
    const link = wrapper.findAll('a').find((a) => a.text() === 'View issue')
    expect(link).toBeTruthy()
    expect(link.attributes('href')).toBe('/dashboard/app1/issues/7')
  })

  it('omits the "View issue" link when there is no associated issue', async () => {
    const { wrapper } = await mountPage({ ...payload, issue: null })
    const link = wrapper.findAll('a').find((a) => a.text() === 'View issue')
    expect(link).toBeUndefined()
  })

  it('ignores a stale response that resolves after a newer one (latest-wins)', async () => {
    const resolvers = []
    api.get.mockImplementation(() => new Promise((res) => resolvers.push(res)))
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/dashboard/:appId/exceptions/:key', name: 'exception-detail', component: ExceptionDetailPage },
        { path: '/dashboard/:appId/issues/:id', name: 'issue-detail', component: { template: '<div />' } },
        { path: '/dashboard/:appId/users/:userId', name: 'user-detail', component: { template: '<div />' } },
      ],
    })
    await router.push(`/dashboard/app1/exceptions/${KEY}`)
    await router.isReady()
    const wrapper = mount(ExceptionDetailPage, {
      global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })] },
    })
    await flushPromises()

    // First load in flight; changing the period fires a second, newer load.
    const store = useAppStore()
    store.period = '24h'
    await flushPromises()
    expect(resolvers.length).toBe(2)

    // Newer (2nd) response resolves first…
    resolvers[1]({ data: { ...payload, message: 'FRESH message' } })
    await flushPromises()
    // …then the stale (1st) response resolves late and must NOT overwrite it.
    resolvers[0]({ data: { ...payload, message: 'STALE message' } })
    await flushPromises()

    expect(wrapper.text()).toContain('FRESH message')
    expect(wrapper.text()).not.toContain('STALE message')
  })

  it('clears the PHP/Laravel runtime badges when a reload fails', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Laravel 12.43.1')
    expect(wrapper.text()).toContain('PHP 8.4.15')

    api.get.mockRejectedValueOnce(new Error('boom'))
    const store = useAppStore()
    store.period = '24h'
    await flushPromises()

    expect(wrapper.text()).not.toContain('12.43.1')
    expect(wrapper.text()).not.toContain('8.4.15')
    expect(wrapper.text()).toContain('Laravel —')
    expect(wrapper.text()).toContain('PHP —')
  })

  it('shows a not-found state for a nonexistent exception key', async () => {
    api.get.mockRejectedValue({ response: { status: 404 } })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/dashboard/:appId/exceptions/:key', name: 'exception-detail', component: ExceptionDetailPage },
        { path: '/dashboard/:appId/issues/:id', name: 'issue-detail', component: { template: '<div />' } },
      ],
    })
    await router.push(`/dashboard/app1/exceptions/${KEY}`)
    await router.isReady()
    const wrapper = mount(ExceptionDetailPage, {
      global: {
        plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Exception not found')
    expect(wrapper.findAll('a').find((a) => a.text() === 'View issue')).toBeUndefined()
  })
})
