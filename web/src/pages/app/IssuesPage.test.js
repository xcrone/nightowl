import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../../services/api'
import IssuesPage from './IssuesPage.vue'

const rows = [
  {
    id: 23,
    priority: null,
    exception_class: 'GuzzleHttp\\Exception\\ConnectException',
    exception_message: 'cURL error 28: timed out',
    occurrences_count: 12,
    users_count: 4,
    first_seen_at: new Date(Date.now() - 10 * 86400 * 1000).toISOString(),
    last_seen_at: new Date(Date.now() - 52 * 60 * 1000).toISOString(),
    assigned_to: null,
  },
]

async function mountPage(appState = {}, rowsData = rows) {
  api.get.mockResolvedValue({ data: { data: rowsData, last_page: 1 } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/dashboard/:appId/issues', component: { template: '<div />' } },
      { path: '/dashboard/:appId/issues/:id', component: { template: '<div />' } },
    ],
  })
  await router.push('/dashboard/app1/issues')
  await router.isReady()
  const wrapper = mount(IssuesPage, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            app: { period: '1h', timezone: 'Local', timeFormat: '24h', ...appState },
            auth: { user: { email: 'z@x.c' } },
          },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => vi.clearAllMocks())

describe('IssuesPage', () => {
  it('fetches issues for the period and renders rows', async () => {
    const { wrapper } = await mountPage()
    const first = api.get.mock.calls[0]
    expect(first[0]).toBe('/api/apps/app1/issues')
    expect(first[1].params.period).toBe('1h')
    expect(wrapper.text()).toContain('GuzzleHttp\\Exception\\ConnectException')
  })

  it('includes the store environment as ?environment=', async () => {
    await mountPage({ environment: 'staging' })
    expect(api.get.mock.calls[0][1].params.environment).toBe('staging')
  })

  it('navigates to the issue detail when a non-priority/assigned cell is clicked', async () => {
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')
    // Issue cell (3rd column) is interactive → navigates.
    const issueCell = wrapper.findAll('tbody td')[2]
    await issueCell.trigger('click')
    expect(push).toHaveBeenCalledWith('/dashboard/app1/issues/23')
  })

  it('does NOT navigate when the Priority or Assigned cell is clicked', async () => {
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')
    const cells = wrapper.findAll('tbody td')
    // Column order: ID, Priority, Issue, Count, Users, First Seen, Last Seen, Assigned
    await cells[1].trigger('click') // Priority
    await cells[cells.length - 1].trigger('click') // Assigned
    expect(push).not.toHaveBeenCalled()
  })
})

// First seen / Last seen were relative ("10d ago"), which ignores the top-bar
// timezone selector by design. They must render absolute and follow the store.
describe('IssuesPage timestamps', () => {
  const INSTANT = '2026-07-16T15:04:05Z'
  const FIRST = '2026-07-14T09:30:00Z'
  const timedRows = [{ ...rows[0], first_seen_at: FIRST, last_seen_at: INSTANT }]
  // Pinned so the buggy relative rendering is a deterministic "4h ago" rather
  // than "just now"/"Nh ago" depending on the real instant the suite runs at.
  const NOW = '2026-07-16T20:00:00Z'

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(NOW))
  })
  afterEach(() => vi.useRealTimers())

  // Column order: ID, Priority, Issue, Count, Users, First Seen, Last Seen, Assigned
  const seenCells = (wrapper) => {
    const cells = wrapper.findAll('tbody td')
    return { first: cells[5], last: cells[6] }
  }

  it('renders First seen / Last seen absolutely in the store timezone (UTC)', async () => {
    const { wrapper } = await mountPage({ timezone: 'UTC', timeFormat: '24h' }, timedRows)
    const { first, last } = seenCells(wrapper)

    expect(last.text()).toContain('15:04:05')
    expect(first.text()).toContain('09:30:00')
    expect(last.text()).not.toMatch(/ago/)
    expect(first.text()).not.toMatch(/ago/)
  })

  it('honours the 12h time format from the store', async () => {
    const { wrapper } = await mountPage({ timezone: 'UTC', timeFormat: '12h' }, timedRows)
    const { last } = seenCells(wrapper)

    expect(last.text()).toMatch(/0?3:04:05/)
    expect(last.text()).toMatch(/\bPM\b/i)
  })

  // The Local-vs-UTC contrast is only observable when the ambient TZ isn't UTC.
  // There's no TZ pinned in vite.config.js, so guard rather than flake on CI.
  it.skipIf(new Date(INSTANT).getTimezoneOffset() === 0)(
    'shifts Last seen when the store timezone flips from UTC to Local',
    async () => {
      const utc = await mountPage({ timezone: 'UTC', timeFormat: '24h' }, timedRows)
      const local = await mountPage({ timezone: 'Local', timeFormat: '24h' }, timedRows)

      expect(seenCells(local.wrapper).last.text()).not.toBe(seenCells(utc.wrapper).last.text())
      expect(seenCells(local.wrapper).last.text()).not.toMatch(/ago/)
    },
  )
})
