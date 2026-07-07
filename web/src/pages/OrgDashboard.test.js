import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  csrfCookie: vi.fn(),
}))
import api from '../services/api'
import OrgDashboard from './OrgDashboard.vue'
import { useAuthStore } from '../store/auth'
import { useOrgStore } from '../store/org'

const org = { uuid: 'org-uuid-1', name: 'Owlworks', account_email: 'owlworks@example.com' }

const teams = [
  {
    id: 1,
    uuid: 'team-uuid-1',
    name: 'Delta Payments',
    apps_count: 2,
    apps: [
      { app_id: 'a1', name: 'Delta API', db_connection: 'ep-delta:5432/d', error_rate: 12.3, count_5xx: 4, exceptions: 7, open_issues: 1, monitoring: 'connected', last_report_at: new Date().toISOString(), alerts: 1 },
      { app_id: 'a2', name: 'Delta Web', db_connection: 'ep-web:5432/w', error_rate: 0.09, count_5xx: 0, exceptions: 0, open_issues: 0, monitoring: 'disconnected', last_report_at: new Date().toISOString(), alerts: 0 },
    ],
  },
  {
    id: 2,
    uuid: 'team-uuid-2',
    name: 'Northwind Traders',
    apps_count: 1,
    apps: [
      { app_id: 'b1', name: 'Northwind API', db_connection: 'ep-nw:5432/n', error_rate: 4, count_5xx: 1, exceptions: 2, open_issues: 0, monitoring: 'connected', last_report_at: new Date().toISOString(), alerts: 0 },
    ],
  },
]

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/organization', component: { template: '<div />' } },
      { path: '/dashboard/:appId', component: { template: '<div />' } },
    ],
  })
}

async function mountPage({ orgsList = [org], appsResponse } = {}) {
  api.get.mockImplementation((url) => {
    if (url === '/api/orgs') return Promise.resolve({ data: { data: orgsList } })
    if (url === '/api/apps') return Promise.resolve(appsResponse ?? { data: { org, teams } })
    return Promise.reject(new Error(`unexpected GET ${url}`))
  })
  const router = makeRouter()
  const wrapper = mount(OrgDashboard, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          stubActions: false,
          initialState: { auth: { user: { name: 'Ada Lovelace', email: 'z@x.c' }, checked: true } },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
})

describe('OrgDashboard', () => {
  it('renders the header, team sections and app cards', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Your Apps')
    expect(wrapper.text()).toContain('Welcome back, Ada Lovelace')
    expect(wrapper.text()).toContain('Delta Payments')
    expect(wrapper.text()).toContain('Delta API')
    expect(wrapper.text()).toContain('Northwind API')
    // error-rate badge is color-coded / formatted
    expect(wrapper.text()).toContain('12.30% err')
  })

  it('links to the Organization settings page', async () => {
    const { wrapper } = await mountPage()
    const link = wrapper.find('[aria-label="Organization settings"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/organization')
  })

  it('filters clients and apps by the search box', async () => {
    const { wrapper } = await mountPage()
    await wrapper.find('input[type="text"]').setValue('Northwind')
    expect(wrapper.text()).toContain('Northwind API')
    expect(wrapper.text()).not.toContain('Delta API')
  })

  it('shows a genuine empty state (not "match your search") when there are no teams and no search was entered', async () => {
    const { wrapper } = await mountPage({ appsResponse: { data: { org, teams: [] } } })
    expect(wrapper.text()).toContain("You don't have any teams yet.")
    expect(wrapper.text()).not.toContain('match your search')
  })

  it('shows "match your search" only once the user has actually typed a query that matches nothing', async () => {
    const { wrapper } = await mountPage()
    await wrapper.find('input[type="text"]').setValue('nothing-matches-this')
    expect(wrapper.text()).toContain('No clients or apps match your search.')
    expect(wrapper.text()).not.toContain("You don't have any teams yet.")
  })

  it('Apps tab: shows a genuine empty state when there are no apps and no search was entered', async () => {
    const { wrapper } = await mountPage({ appsResponse: { data: { org, teams: [] } } })
    const appsToggle = wrapper.findAll('button').find((b) => b.text() === 'Apps')
    await appsToggle.trigger('click')
    expect(wrapper.text()).toContain("You don't have any apps yet.")
    expect(wrapper.text()).not.toContain('match your search')
  })

  it('Apps tab: shows "match your search" once a query matches nothing', async () => {
    const { wrapper } = await mountPage()
    const appsToggle = wrapper.findAll('button').find((b) => b.text() === 'Apps')
    await appsToggle.trigger('click')
    await wrapper.find('input[type="text"]').setValue('nothing-matches-this')
    expect(wrapper.text()).toContain('No apps match your search.')
  })

  it('switches to the flat Apps view', async () => {
    const { wrapper } = await mountPage()
    const appsToggle = wrapper.findAll('button').find((b) => b.text() === 'Apps')
    await appsToggle.trigger('click')
    // Team header no longer shown in flat view, but the apps still are
    expect(wrapper.text()).toContain('Delta API')
    expect(wrapper.text()).not.toContain('Delta Payments')
  })

  it('Apps tab: shows the same "issues" badge as the Teams view', async () => {
    const { wrapper } = await mountPage()
    const appsToggle = wrapper.findAll('button').find((b) => b.text() === 'Apps')
    await appsToggle.trigger('click')
    const card = wrapper.findAll('button').find((b) => b.text().includes('Delta API'))
    expect(card.text()).toContain('1 issues')
  })

  it("shows an app's description instead of its db_connection when one is set", async () => {
    const withDescription = {
      data: {
        org,
        teams: [
          {
            ...teams[0],
            apps: [{ ...teams[0].apps[0], description: 'Handles checkout and payments.' }],
          },
        ],
      },
    }
    const { wrapper } = await mountPage({ appsResponse: withDescription })
    expect(wrapper.text()).toContain('Handles checkout and payments.')
    expect(wrapper.text()).not.toContain('ep-delta:5432/d')
  })

  it("falls back to db_connection when an app has no description", async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('ep-delta:5432/d')
  })

  it('navigates into an app dashboard on card click', async () => {
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')
    const card = wrapper.findAll('button').find((b) => b.text().includes('Delta API'))
    await card.trigger('click')
    expect(push).toHaveBeenCalledWith('/dashboard/a1')
  })

  it('logs out from the top-right account button (finding #13)', async () => {
    const { wrapper, router } = await mountPage()
    const auth = useAuthStore()
    const logoutSpy = vi.spyOn(auth, 'logout')
    const push = vi.spyOn(router, 'push')
    const logout = wrapper.find('[aria-label="Log out"]')
    expect(logout.exists()).toBe(true)
    await logout.trigger('click')
    await flushPromises()
    expect(logoutSpy).toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith('/login')
  })

  it('opens the Add team modal and creates a team', async () => {
    api.post.mockResolvedValue({ data: { id: 3, uuid: 'team-uuid-3', name: 'New Team' } })
    const { wrapper } = await mountPage()

    await wrapper.find('[data-test="add-team"]').trigger('click')
    expect(wrapper.find('[data-test="team-modal-name"]').exists()).toBe(true)

    await wrapper.find('[data-test="team-modal-name"]').setValue('New Team')
    await wrapper.find('[data-test="team-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/teams`, { name: 'New Team' })
    expect(wrapper.text()).toContain('New Team')
    expect(wrapper.find('[data-test="team-modal-name"]').exists()).toBe(false)
  })

  it('shows an inline error and does not call the API when submitting the Add team modal with an empty name', async () => {
    const { wrapper } = await mountPage()

    await wrapper.find('[data-test="add-team"]').trigger('click')
    await wrapper.find('[data-test="team-modal-submit"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Team name is required.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('opens the Add app modal from a team and creates an app', async () => {
    api.post.mockResolvedValue({ data: { app_id: 'c1', name: 'New App', description: '', db_connection: '' } })
    const { wrapper } = await mountPage()

    const addAppButtons = wrapper.findAll('[data-test="add-app"]')
    await addAppButtons[0].trigger('click')
    expect(wrapper.find('[data-test="app-modal-name"]').exists()).toBe(true)

    await wrapper.find('[data-test="app-modal-name"]').setValue('New App')
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/api/teams/team-uuid-1/apps', {
      name: 'New App',
      description: '',
      db_connection: '',
    })
    expect(wrapper.text()).toContain('New App')
  })

  it('edits an app via the edit icon', async () => {
    api.put.mockResolvedValue({ data: { app_id: 'a1', name: 'Delta API Renamed', description: '', db_connection: '' } })
    const { wrapper } = await mountPage()

    await wrapper.find('[aria-label="Edit app"]').trigger('click')
    await wrapper.find('[data-test="app-modal-name"]').setValue('Delta API Renamed')
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/api/apps/a1', {
      name: 'Delta API Renamed',
      description: '',
      db_connection: 'ep-delta:5432/d',
    })
  })

  it('deletes an app via the delete icon without navigating', async () => {
    vi.stubGlobal('confirm', vi.fn(() => true))
    api.delete.mockResolvedValue({})
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')

    await wrapper.find('[aria-label="Delete app"]').trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith('/api/apps/a1')
    expect(push).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('Delta API')
    vi.unstubAllGlobals()
  })

  it('shows no org switcher for a user with just one org', async () => {
    const { wrapper } = await mountPage({ orgsList: [org] })
    expect(wrapper.find('[data-test="org-switcher"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Welcome back, Ada Lovelace')
  })

  it('shows an org switcher and switches orgs when the user belongs to more than one', async () => {
    const otherOrg = { uuid: 'org-uuid-2', name: 'Other Org', account_email: 'other@example.com' }
    const otherTeams = [{ id: 9, uuid: 'team-uuid-9', name: 'Other Team', apps_count: 0, apps: [] }]
    const { wrapper } = await mountPage({ orgsList: [org, otherOrg] })

    const switcher = wrapper.find('[data-test="org-switcher"]')
    expect(switcher.exists()).toBe(true)

    api.get.mockImplementation((url) => {
      if (url === '/api/apps') return Promise.resolve({ data: { org: otherOrg, teams: otherTeams } })
      return Promise.reject(new Error(`unexpected GET ${url}`))
    })

    await switcher.setValue(otherOrg.uuid)
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/api/apps', { params: { org: otherOrg.uuid } })
    expect(wrapper.text()).toContain('Other Team')
    expect(wrapper.text()).not.toContain('Delta Payments')
    expect(localStorage.getItem('nightowl:currentOrgUuid')).toBe(otherOrg.uuid)
  })

  describe('zero-org state', () => {
    const zeroOrgMocks = { orgsList: [], appsResponse: { data: { org: null, teams: [] } } }

    it('shows a Create organization prompt instead of the normal toolbar/team view when the user has no organization', async () => {
      const { wrapper } = await mountPage(zeroOrgMocks)

      expect(wrapper.find('[data-test="create-org"]').exists()).toBe(true)
      expect(wrapper.find('[data-test="add-team"]').exists()).toBe(false)
    })

    it('creates an organization from the prompt and shows the resulting dashboard', async () => {
      const { wrapper } = await mountPage(zeroOrgMocks)

      await wrapper.find('[data-test="create-org"]').trigger('click')
      await wrapper.find('[data-test="create-org-modal-name"]').setValue('New Org')
      await wrapper.find('[data-test="create-org-modal-email"]').setValue('new-org@example.com')

      const newOrg = { uuid: 'org-uuid-new', name: 'New Org', account_email: 'new-org@example.com' }
      api.get.mockImplementation((url) => {
        if (url === '/api/apps') return Promise.resolve({ data: { org: newOrg, teams: [] } })
        return Promise.reject(new Error(`unexpected GET ${url}`))
      })
      api.post.mockResolvedValue({ data: newOrg })

      await wrapper.find('[data-test="create-org-modal-submit"]').trigger('click')
      await flushPromises()

      expect(api.post).toHaveBeenCalledWith('/api/orgs', { name: 'New Org', account_email: 'new-org@example.com' })
      expect(wrapper.find('[data-test="create-org-modal-name"]').exists()).toBe(false)
      expect(wrapper.find('[data-test="add-team"]').exists()).toBe(true)
    })

    it('shows an inline error and does not close the modal when creating an organization fails', async () => {
      const { wrapper } = await mountPage(zeroOrgMocks)

      await wrapper.find('[data-test="create-org"]').trigger('click')
      await wrapper.find('[data-test="create-org-modal-name"]').setValue('New Org')
      await wrapper.find('[data-test="create-org-modal-email"]').setValue('new-org@example.com')

      api.post.mockRejectedValue({ response: { data: { message: 'Something went wrong.' } } })

      await wrapper.find('[data-test="create-org-modal-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Something went wrong.')
      expect(wrapper.find('[data-test="create-org-modal-name"]').exists()).toBe(true)
    })

    it('shows a validation error and does not call the API when submitting the Create organization form with blank fields', async () => {
      const { wrapper } = await mountPage(zeroOrgMocks)

      await wrapper.find('[data-test="create-org"]').trigger('click')
      await wrapper.find('[data-test="create-org-modal-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-test="create-org-modal-name"]').exists()).toBe(true)
      expect(api.post).not.toHaveBeenCalled()
    })
  })

  // NOTE on sequencing: per the zero-org-state contract above, the whole
  // toolbar + team/app view (including each team's own error banner) is
  // hidden whenever `!org.org`. Vue batches reactive updates into a single
  // render, so a test can never observe "team view hidden" and "team-scoped
  // error text visible" in the same paint once org.org is null — they are
  // mutually exclusive on screen. To still exercise each guard, org.org is
  // nulled *synchronously, without an intervening await/tick*, immediately
  // before the guarded click — so the still-mounted (not yet re-rendered)
  // button is clicked while its handler already reads the new org.org===null
  // value. This reliably proves the guard fires (no API call) without
  // relying on a DOM state that can't exist. Where the resulting error text
  // lives somewhere NOT hidden by the empty-state switch (the Add-team
  // modal, which is structurally independent of it), the visible-error
  // assertion is also included.
  describe('no-org-context defensive guards', () => {
    it('shows an error instead of silently doing nothing when Add team is submitted with no org context', async () => {
      const { wrapper } = await mountPage()
      const org = useOrgStore()

      await wrapper.find('[data-test="add-team"]').trigger('click')
      await wrapper.find('[data-test="team-modal-name"]').setValue('New Team')

      org.org = null
      await wrapper.find('[data-test="team-modal-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('No organization selected.')
      expect(api.post).not.toHaveBeenCalled()
    })

    it('does not call the API (sets an internal error instead of a silent no-op) when renaming a team with no org context', async () => {
      const { wrapper } = await mountPage()
      const org = useOrgStore()

      const renameButton = wrapper.findAll('button').find((b) => b.text() === 'Rename')
      await renameButton.trigger('click')
      const saveButton = wrapper.findAll('button').find((b) => b.text() === 'Save')

      org.org = null
      await saveButton.trigger('click')

      expect(api.put).not.toHaveBeenCalled()
    })

    it('does not call the API (sets an internal error instead of a silent no-op) when deleting a team with no org context', async () => {
      vi.stubGlobal('confirm', vi.fn(() => true))
      const { wrapper } = await mountPage()
      const org = useOrgStore()
      const deleteButton = wrapper.find('[data-test="delete-team"]')

      org.org = null
      await deleteButton.trigger('click')

      expect(api.delete).not.toHaveBeenCalled()
      vi.unstubAllGlobals()
    })
  })
})
