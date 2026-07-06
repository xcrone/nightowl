import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../../services/api'
import IssueDetailPage from './IssueDetailPage.vue'

const detail = {
  issue: {
    id: 5, type: 'exception', status: 'open', priority: null,
    exception_class: 'GuzzleHttp\\Exception\\ConnectException',
    exception_message: 'cURL error 28: timed out',
    file: 'app/Services/ShippingClient.php', line: 121,
    first_seen_at: new Date().toISOString(), last_seen_at: new Date().toISOString(),
    occurrences_count: 3, users_count: 2, handled: false,
    php_version: '8.4.15', laravel_version: '12.43.1',
  },
  stack_frames: [{ file: 'app/Services/ShippingClient.php', line: 121, function: 'ship', index: 0 }],
  occurrences: [{ id: 1, created_at: new Date().toISOString(), source: 'job', source_label: 'JOB', message: 'timed out', user_id: 'user_11' }],
  occurrences_by_environment: [{ environment: 'production', count: 3 }],
  activity: [{ id: 1, actor_type: 'system', actor_name: 'NightOwl', action: 'created the issue', created_at: new Date().toISOString() }],
}

function impl(url) {
  if (url.includes('/comments')) return Promise.resolve({ data: { data: [] } })
  return Promise.resolve({ data: detail })
}

async function mountPage() {
  api.get.mockImplementation(impl)
  api.post.mockResolvedValue({ data: {} })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId/issues/:id', component: { template: '<div />' } },
      { path: '/dashboard/:appId/users/:userId', component: { template: '<div />' } },
    ],
  })
  await router.push('/dashboard/app1/issues/5')
  await router.isReady()
  const wrapper = mount(IssueDetailPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' } } })],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('IssueDetailPage', () => {
  it('fetches the issue detail and renders occurrences + activity + stack', async () => {
    const wrapper = await mountPage()
    expect(api.get.mock.calls[0][0]).toBe('/api/apps/app1/issues/5')

    expect(wrapper.text()).toContain('GuzzleHttp\\Exception\\ConnectException')
    // stack frame
    expect(wrapper.text()).toContain('app/Services/ShippingClient.php:121')
    // occurrence + user link
    expect(wrapper.text()).toContain('user_11')
    // activity feed
    expect(wrapper.text()).toContain('created the issue')
    // per-environment breakdown
    expect(wrapper.text()).toContain('production')
    // unhandled badge
    expect(wrapper.text()).toContain('Unhandled')
  })

  it('posts a comment to the app-scoped issues route', async () => {
    const wrapper = await mountPage()
    const textarea = wrapper.find('textarea')
    await textarea.setValue('needs a retry')
    const btn = wrapper.findAll('button').find((b) => b.text() === 'Comment')
    await btn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/app1/issues/5/comments', { body: 'needs a retry' })
  })

  it('posts status actions to the app-scoped issues route', async () => {
    const wrapper = await mountPage()
    const btn = wrapper.findAll('button').find((b) => b.text() === 'Resolve')
    await btn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/app1/issues/5/resolve')
  })
})
