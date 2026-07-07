import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../services/api'
import OrgDashboard from './OrgDashboard.vue'
import { useAuthStore } from '../store/auth'

const teams = [
  {
    id: 1,
    name: 'Delta Payments',
    apps_count: 2,
    apps: [
      { app_id: 'a1', name: 'Delta API', db_connection: 'ep-delta:5432/d', error_rate: 12.3, count_5xx: 4, exceptions: 7, open_issues: 1, monitoring: 'connected', last_report_at: new Date().toISOString(), alerts: 1 },
      { app_id: 'a2', name: 'Delta Web', db_connection: 'ep-web:5432/w', error_rate: 0.09, count_5xx: 0, exceptions: 0, open_issues: 0, monitoring: 'disconnected', last_report_at: new Date().toISOString(), alerts: 0 },
    ],
  },
  {
    id: 2,
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
      { path: '/dashboard/:appId', component: { template: '<div />' } },
    ],
  })
}

async function mountPage() {
  api.get.mockImplementation((url) =>
    url === '/api/orgs'
      ? Promise.resolve({ data: { data: [{ name: 'Owlworks' }] } })
      : Promise.resolve({ data: { org: { name: 'Owlworks' }, teams } }),
  )
  const router = makeRouter()
  const wrapper = mount(OrgDashboard, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          stubActions: false,
          initialState: { auth: { user: { email: 'z@x.c' }, checked: true } },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => vi.clearAllMocks())

describe('OrgDashboard', () => {
  it('renders the header, team sections and app cards', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Your Apps')
    expect(wrapper.text()).toContain('Welcome back, Owlworks')
    expect(wrapper.text()).toContain('Delta Payments')
    expect(wrapper.text()).toContain('Delta API')
    expect(wrapper.text()).toContain('Northwind API')
    // error-rate badge is color-coded / formatted
    expect(wrapper.text()).toContain('12.30% err')
  })

  it('filters clients and apps by the search box', async () => {
    const { wrapper } = await mountPage()
    await wrapper.find('input[type="text"]').setValue('Northwind')
    expect(wrapper.text()).toContain('Northwind API')
    expect(wrapper.text()).not.toContain('Delta API')
  })

  it('switches to the flat Apps view', async () => {
    const { wrapper } = await mountPage()
    const appsToggle = wrapper.findAll('button').find((b) => b.text() === 'Apps')
    await appsToggle.trigger('click')
    // Team header no longer shown in flat view, but the apps still are
    expect(wrapper.text()).toContain('Delta API')
    expect(wrapper.text()).not.toContain('Delta Payments')
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
})
