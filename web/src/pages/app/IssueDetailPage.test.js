import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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

async function mountPage(authState = { user: null, checked: true }) {
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
      plugins: [router, createTestingPinia({
        createSpy: vi.fn,
        initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' }, auth: authState },
      })],
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

  it('assigns the issue to the current user, then unassigns it', async () => {
    const wrapper = await mountPage({ user: { id: 1, name: 'Dev', email: 'dev@example.test' }, checked: true })

    const assignBtn = wrapper.findAll('button').find((b) => b.text() === 'Assign to me')
    await assignBtn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/app1/issues/5/assign', { assigned_to: 'dev@example.test' })

    const unassignBtn = wrapper.findAll('button').find((b) => b.text() === 'Unassign')
    await unassignBtn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/app1/issues/5/assign', { assigned_to: null })
  })

  it('assigns via the free-text input on blur', async () => {
    const wrapper = await mountPage()
    const input = wrapper.find('input[placeholder="Unassigned"]')
    await input.setValue('teammate@example.test')
    await input.trigger('blur')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/app1/issues/5/assign', { assigned_to: 'teammate@example.test' })
  })

  it('shows a not-found state for a nonexistent issue id, without fetching comments', async () => {
    api.get.mockImplementation((url) => {
      if (url.includes('/comments')) return Promise.resolve({ data: { data: [] } })
      return Promise.reject({ response: { status: 404 } })
    })
    api.post.mockResolvedValue({ data: {} })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/dashboard/:appId/issues/:id', component: { template: '<div />' } }],
    })
    await router.push('/dashboard/app1/issues/999999')
    await router.isReady()
    const wrapper = mount(IssueDetailPage, {
      global: {
        plugins: [router, createTestingPinia({
          createSpy: vi.fn,
          initialState: { app: { period: '1h', timezone: 'UTC', timeFormat: '24h' }, auth: { user: null, checked: true } },
        })],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Issue not found')
    expect(wrapper.findAll('button').find((b) => b.text() === 'Resolve')).toBeUndefined()
    expect(api.get).not.toHaveBeenCalledWith(expect.stringContaining('/comments'))
  })
})

// The detail page's timestamps were relative ("3h ago"), so they ignored the
// top-bar timezone selector. They must render absolute and follow the store.
describe('IssueDetailPage timestamps', () => {
  const LAST_SEEN = '2026-07-16T15:04:05Z'
  const FIRST_SEEN = '2026-07-14T09:30:00Z'
  const OCCURRED = '2026-07-16T11:22:33Z'
  // Pinned so the buggy relative rendering is a deterministic "4h ago" rather
  // than "just now"/"Nh ago" depending on the real instant the suite runs at.
  const NOW = '2026-07-16T20:00:00Z'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(NOW))
  })
  afterEach(() => vi.useRealTimers())

  const timedDetail = {
    ...detail,
    issue: { ...detail.issue, first_seen_at: FIRST_SEEN, last_seen_at: LAST_SEEN },
    occurrences: [{ ...detail.occurrences[0], created_at: OCCURRED }],
  }

  async function mountTimed(appState) {
    api.get.mockImplementation((url) =>
      url.includes('/comments')
        ? Promise.resolve({ data: { data: [] } })
        : Promise.resolve({ data: timedDetail }),
    )
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
        plugins: [router, createTestingPinia({
          createSpy: vi.fn,
          initialState: { app: { period: '1h', ...appState }, auth: { user: null, checked: true } },
        })],
      },
    })
    await flushPromises()
    return wrapper
  }

  // Scoped to the Details <dd>s on purpose: the Activity feed's own relative
  // timestamps are out of scope here, so a wrapper.text() /ago/ assertion would
  // be over-broad and fail even once these cells are fixed.
  const detailValue = (wrapper, label) => {
    const row = wrapper.findAll('dl > div').find((d) => d.find('dt').text() === label)
    return row.find('dd')
  }

  it('renders First seen / Last seen absolutely in the store timezone (UTC)', async () => {
    const wrapper = await mountTimed({ timezone: 'UTC', timeFormat: '24h' })

    expect(detailValue(wrapper, 'Last seen').text()).toContain('15:04:05')
    expect(detailValue(wrapper, 'First seen').text()).toContain('09:30:00')
    expect(detailValue(wrapper, 'Last seen').text()).not.toMatch(/ago/)
    expect(detailValue(wrapper, 'First seen').text()).not.toMatch(/ago/)
  })

  it('renders the occurrence timestamp absolutely in the store timezone (UTC)', async () => {
    const wrapper = await mountTimed({ timezone: 'UTC', timeFormat: '24h' })
    const occurrenceCell = wrapper.findAll('table tbody td')[0]

    expect(occurrenceCell.text()).toContain('11:22:33')
    expect(occurrenceCell.text()).not.toMatch(/ago/)
  })

  it('honours the 12h time format from the store', async () => {
    const wrapper = await mountTimed({ timezone: 'UTC', timeFormat: '12h' })
    const lastSeen = detailValue(wrapper, 'Last seen')

    expect(lastSeen.text()).toMatch(/0?3:04:05/)
    expect(lastSeen.text()).toMatch(/\bPM\b/i)
  })

  // Ambient TZ isn't pinned (no TZ in vite.config.js), so this contrast is only
  // meaningful off-UTC; guarded rather than flaky.
  it.skipIf(new Date(LAST_SEEN).getTimezoneOffset() === 0)(
    'shifts Last seen when the store timezone flips from UTC to Local',
    async () => {
      const utc = await mountTimed({ timezone: 'UTC', timeFormat: '24h' })
      const local = await mountTimed({ timezone: 'Local', timeFormat: '24h' })

      expect(detailValue(local, 'Last seen').text()).not.toContain('15:04:05')
      expect(detailValue(local, 'Last seen').text()).not.toMatch(/ago/)
      expect(detailValue(utc, 'Last seen').text()).toContain('15:04:05')
    },
  )
})
