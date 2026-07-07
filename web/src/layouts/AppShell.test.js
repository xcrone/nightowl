import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))

// jsdom lacks matchMedia, which the theme store reads when it initialises.
window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {} }))

import api from '../services/api'
import AppShell from './AppShell.vue'
import { useAuthStore } from '../store/auth'
import { useAppStore } from '../store/app'

const teams = [
  {
    id: 1,
    name: 'Delta Payments',
    apps: [
      { app_id: 'a1', name: 'Delta API' },
      { app_id: 'a2', name: 'Delta Web' },
    ],
  },
  {
    id: 2,
    name: 'Northwind Traders',
    apps: [{ app_id: 'b1', name: 'Northwind API' }],
  },
]

const appState = {
  current: {
    app_id: 'a1',
    name: 'Delta API',
    environments: { production: '#22c55e', staging: '#f59e0b' },
  },
  apps: teams.flatMap((t) => t.apps),
  teams,
  org: { name: 'Owlworks', account_email: 'z@x.c' },
  period: '1h',
  timezone: 'Local',
  timeFormat: '24h',
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/login', component: { template: '<div />' } },
      { path: '/dashboard/:appId', component: { template: '<div />' } },
    ],
  })
}

async function mountShell() {
  // Real store actions (stubActions:false) so loadApp's promise chain resolves;
  // the current-app fetch echoes the seeded record (incl. environments).
  api.get.mockImplementation((url) =>
    url === '/api/apps'
      ? Promise.resolve({ data: { org: appState.org, teams } })
      : Promise.resolve({ data: appState.current }),
  )
  api.post.mockResolvedValue({})

  const router = makeRouter()
  router.push('/dashboard/a1')
  await router.isReady()

  const wrapper = mount(AppShell, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          stubActions: false,
          initialState: {
            app: JSON.parse(JSON.stringify(appState)),
            auth: { user: { email: 'z@x.c' }, checked: true },
            theme: { mode: 'light', isDark: false },
          },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => vi.clearAllMocks())

describe('AppShell — app switcher (finding #11)', () => {
  it('keeps the switcher dropdown closed until its trigger is clicked', async () => {
    const { wrapper } = await mountShell()
    expect(wrapper.find('[data-testid="app-switcher-menu"]').exists()).toBe(false)
    await wrapper.find('[data-testid="app-switcher-trigger"]').trigger('click')
    expect(wrapper.find('[data-testid="app-switcher-menu"]').exists()).toBe(true)
  })

  it('lists the environment filter and every sibling app grouped by team', async () => {
    const { wrapper } = await mountShell()
    await wrapper.find('[data-testid="app-switcher-trigger"]').trigger('click')
    const menu = wrapper.find('[data-testid="app-switcher-menu"]')
    expect(menu.text()).toContain('Environment')
    expect(menu.text()).toContain('All environments')
    expect(menu.text()).toContain('production')
    expect(menu.text()).toContain('staging')
    // Cross-team sibling list
    expect(menu.text()).toContain('Delta Payments')
    expect(menu.text()).toContain('Northwind Traders')
    expect(menu.text()).toContain('Delta Web')
    expect(menu.text()).toContain('Northwind API')
    // Shortcut back to the org-dashboard Apps grid
    expect(menu.text()).toContain('Apps')
  })

  it('links each sibling app to its own dashboard', async () => {
    const { wrapper } = await mountShell()
    await wrapper.find('[data-testid="app-switcher-trigger"]').trigger('click')
    const link = wrapper
      .findAll('[data-testid="switcher-app"]')
      .find((a) => a.text().includes('Northwind API'))
    expect(link.attributes('href')).toBe('/dashboard/b1')
  })

  it('selecting an environment marks it active and updates the label', async () => {
    const { wrapper } = await mountShell()
    // Default label
    expect(wrapper.find('[data-testid="app-switcher-trigger"]').text()).toContain('All environments')
    await wrapper.find('[data-testid="app-switcher-trigger"]').trigger('click')
    const prod = wrapper
      .findAll('[data-testid="env-option"]')
      .find((b) => b.text().includes('production'))
    await prod.trigger('click')
    expect(wrapper.find('[data-testid="app-switcher-trigger"]').text()).toContain('production')
    // Lifted into the app store so list pages can scope fetches by it.
    expect(useAppStore().environment).toBe('production')
  })
})

describe('AppShell — account menu (finding #12)', () => {
  it('opens a dropdown with Account / Team / theme toggle / Log out', async () => {
    const { wrapper } = await mountShell()
    expect(wrapper.find('[data-testid="account-menu"]').exists()).toBe(false)
    await wrapper.find('[data-testid="account-trigger"]').trigger('click')
    const menu = wrapper.find('[data-testid="account-menu"]')
    expect(menu.exists()).toBe(true)
    expect(menu.text()).toContain('Account')
    expect(menu.text()).toContain('Team')
    // light theme active -> offers switch to dark
    expect(menu.text()).toMatch(/mode/i)
    expect(menu.text()).toContain('Log out')
  })

  it('logs out and redirects to /login', async () => {
    const { wrapper, router } = await mountShell()
    const auth = useAuthStore()
    const logoutSpy = vi.spyOn(auth, 'logout')
    const push = vi.spyOn(router, 'push')
    await wrapper.find('[data-testid="account-trigger"]').trigger('click')
    await wrapper.find('[data-testid="account-logout"]').trigger('click')
    await flushPromises()
    expect(logoutSpy).toHaveBeenCalled()
    expect(push).toHaveBeenCalledWith('/login')
  })
})
