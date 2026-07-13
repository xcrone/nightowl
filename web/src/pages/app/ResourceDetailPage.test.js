import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))

import api from '../../services/api'
import ResourceDetailPage from './ResourceDetailPage.vue'

const record = {
  id: 1,
  app_id: 5,
  group_hash: 'abc',
  v: 1,
  trace_id: 'trace-1',
  created_at: new Date().toISOString(),
  method: 'GET',
  url: 'https://app.example.com/api/orders',
  route_path: '/api/orders',
  status_code: 200,
  duration: 14310,
  user_id: 'u1',
  payload: JSON.stringify({ query: { foo: 'bar' } }),
  headers: 'X-Test: 1\nContent-Type: text/plain',
}

const related = {
  origin: null,
  children_filter: { execution_source: 'request', execution_id: 'trace-1' },
  children: { queries: 2, logs: 1 },
}

const queriesPage = {
  data: [{ id: 10, created_at: new Date().toISOString(), sql_query: 'select * from orders', connection: 'pgsql', duration: 500 }],
  current_page: 1, last_page: 1, per_page: 25, total: 1,
}

const logsPage = {
  data: [{ id: 20, created_at: new Date().toISOString(), level: 'error', message: 'Something broke', channel: 'stack' }],
  current_page: 1, last_page: 1, per_page: 25, total: 1,
}

const userDetail = { user: { id: 'u1', name: 'Alice', email: 'alice@example.com' } }

function apiImpl({ recordData = record, relatedData = related } = {}) {
  return (url) => {
    if (url === '/api/apps/app1/requests/1') return Promise.resolve({ data: recordData })
    if (url === '/api/apps/app1/requests/1/related') return Promise.resolve({ data: relatedData })
    if (url === '/api/apps/app1/users/u1') return Promise.resolve({ data: userDetail })
    if (url === '/api/apps/app1/queries') return Promise.resolve({ data: queriesPage })
    if (url === '/api/apps/app1/logs') return Promise.resolve({ data: logsPage })
    return Promise.reject(new Error(`unexpected url ${url}`))
  }
}

async function mountPage(impl = apiImpl()) {
  api.get.mockImplementation(impl)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId/:resource/record/:id', name: 'resource-detail', component: ResourceDetailPage },
      { path: '/dashboard/:appId/:resource', name: 'resource-list', component: { template: '<div />' } },
      { path: '/dashboard/:appId/users/:userId', name: 'user-detail', component: { template: '<div />' } },
    ],
  })
  await router.push('/dashboard/app1/requests/record/1')
  await router.isReady()
  const wrapper = mount(ResourceDetailPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ResourceDetailPage', () => {
  it('fetches the record + related in parallel and renders the detail fields', async () => {
    const { wrapper } = await mountPage()

    expect(api.get.mock.calls[0][0]).toBe('/api/apps/app1/requests/1')
    expect(api.get.mock.calls[1][0]).toBe('/api/apps/app1/requests/1/related')

    // header badges
    expect(wrapper.text()).toContain('GET')
    expect(wrapper.text()).toContain('200')
    // generic Details table: humanized labels + raw values
    expect(wrapper.text()).toContain('Route Path')
    expect(wrapper.text()).toContain('/api/orders')
    expect(wrapper.text()).toContain('Duration')
    expect(wrapper.text()).toContain('14.31ms')
    // internal fields never rendered
    expect(wrapper.text()).not.toContain('group_hash')

    // Authenticated User panel — id link plus the email fetched from /users/u1
    expect(wrapper.text()).toContain('Authenticated User')
    const userLink = wrapper.findAll('a').find((a) => a.text() === 'u1')
    expect(userLink).toBeTruthy()
    expect(userLink.attributes('href')).toBe('/dashboard/app1/users/u1')
    expect(wrapper.text()).toContain('Email Address')
    expect(wrapper.text()).toContain('alice@example.com')
  })

  it('defaults to the first blob tab as a JSON tree, and switching to a non-JSON blob tab shows a <pre> fallback', async () => {
    const { wrapper } = await mountPage()

    // Payload is the first blob field present on the record -> selected by
    // default, and it parses as JSON -> JsonViewer tree (<details>/<summary>).
    expect(wrapper.findAll('details').length).toBeGreaterThan(0)
    expect(wrapper.text()).toContain('foo')
    expect(wrapper.text()).toContain('"bar"')
    expect(wrapper.find('pre').exists()).toBe(false)

    // Switch to the Request Headers tab: plain text, not JSON -> <pre> fallback.
    await wrapper.findAll('button').find((b) => b.text() === 'Request Headers').trigger('click')
    await flushPromises()

    const pre = wrapper.find('pre')
    expect(pre.exists()).toBe(true)
    expect(pre.text()).toContain('X-Test: 1')
  })

  it('lists blob fields and related resources in one tab strip; a related tab lazily fetches on first select', async () => {
    const { wrapper } = await mountPage()

    const buttons = wrapper.findAll('button')
    expect(buttons.find((b) => b.text() === 'Payload')).toBeTruthy()
    expect(buttons.find((b) => b.text() === 'Request Headers')).toBeTruthy()
    expect(buttons.find((b) => b.text() === 'Queries (2)')).toBeTruthy()
    expect(buttons.find((b) => b.text() === 'Logs (1)')).toBeTruthy()

    // Related tabs aren't fetched until selected — only record/related/user email so far.
    expect(api.get.mock.calls.some(([url]) => url === '/api/apps/app1/queries')).toBe(false)

    await buttons.find((b) => b.text() === 'Queries (2)').trigger('click')
    await flushPromises()

    const queriesCall = api.get.mock.calls.find(([url]) => url === '/api/apps/app1/queries')
    expect(queriesCall).toBeTruthy()
    expect(queriesCall[1].params).toMatchObject({ execution_source: 'request', execution_id: 'trace-1', period: '1h' })
    expect(wrapper.text()).toContain('select * from orders')
    // Switching away from Payload hides its tree.
    expect(wrapper.text()).not.toContain('"bar"')
  })

  it('does not refetch a related tab that was already loaded (lazy + cached)', async () => {
    const { wrapper } = await mountPage()

    await wrapper.findAll('button').find((b) => b.text() === 'Queries (2)').trigger('click')
    await flushPromises()
    expect(api.get.mock.calls.filter(([url]) => url === '/api/apps/app1/queries')).toHaveLength(1)

    await wrapper.findAll('button').find((b) => b.text() === 'Logs (1)').trigger('click')
    await flushPromises()
    await wrapper.findAll('button').find((b) => b.text() === 'Queries (2)').trigger('click')
    await flushPromises()

    expect(api.get.mock.calls.filter(([url]) => url === '/api/apps/app1/queries')).toHaveLength(1)
    expect(api.get.mock.calls.filter(([url]) => url === '/api/apps/app1/logs')).toHaveLength(1)
  })

  it('shows a not-found state for a nonexistent record id', async () => {
    const { wrapper } = await mountPage(() => Promise.reject({ response: { status: 404 } }))

    expect(wrapper.text()).toContain('Record not found')
    const backLink = wrapper.findAll('a').find((a) => a.text().startsWith('Back to'))
    expect(backLink).toBeTruthy()
    expect(backLink.attributes('href')).toBe('/dashboard/app1/requests')
  })

  it('ignores a stale response that resolves after a newer one (latest-wins)', async () => {
    const resolvers = []
    api.get.mockImplementation(() => new Promise((res) => resolvers.push(res)))
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/dashboard/:appId/:resource/record/:id', name: 'resource-detail', component: ResourceDetailPage },
        { path: '/dashboard/:appId/:resource', name: 'resource-list', component: { template: '<div />' } },
      ],
    })
    await router.push('/dashboard/app1/requests/record/1')
    await router.isReady()
    const wrapper = mount(ResourceDetailPage, {
      global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })] },
    })
    await flushPromises()

    await router.push('/dashboard/app1/requests/record/2')
    await flushPromises()
    expect(resolvers.length).toBe(4)

    // Newer (record 2) pair resolves first…
    resolvers[2]({ data: { ...record, id: 2, route_path: '/api/fresh' } })
    resolvers[3]({ data: related })
    await flushPromises()
    // …then the stale (record 1) pair resolves late and must NOT overwrite it.
    resolvers[0]({ data: { ...record, id: 1, route_path: '/api/stale' } })
    resolvers[1]({ data: related })
    await flushPromises()

    expect(wrapper.text()).toContain('/api/fresh')
    expect(wrapper.text()).not.toContain('/api/stale')
  })
})
