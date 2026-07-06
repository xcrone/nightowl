import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../../services/api'
import SettingsPage from './SettingsPage.vue'

const settings = {
  name: 'Northwind Web',
  app_id: '3FoNKDbo7D5S9MGhLx9qybejLCE',
  agent_token_masked: 'nw_****************abcd',
  template: { name: 'E-commerce Setup', synced_at: new Date().toISOString() },
  environments: { production: '#10b981', staging: '#f59e0b' },
}

async function mountPage() {
  api.get.mockImplementation((url) => {
    if (url.includes('/alert-channels')) return Promise.resolve({ data: { data: [] } })
    return Promise.resolve({ data: { settings } })
  })
  api.post.mockResolvedValue({ data: { agent_token: 'nw_freshly_generated_token' } })
  api.put.mockResolvedValue({ data: {} })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/dashboard/:appId', component: { template: '<div />' } }],
  })
  await router.push('/dashboard/3FoNKDbo7D5S9MGhLx9qybejLCE')
  await router.isReady()
  const wrapper = mount(SettingsPage, {
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: { app: { apps: [{ app_id: 'other1', name: 'Delta API' }] } } })],
    },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('SettingsPage', () => {
  it('fetches settings and renders app id, template and detected environments', async () => {
    const wrapper = await mountPage()
    expect(api.get.mock.calls[0][0]).toBe('/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/settings')

    expect(wrapper.text()).toContain('Northwind Web')
    expect(wrapper.text()).toContain('3FoNKDbo7D5S9MGhLx9qybejLCE')
    expect(wrapper.text()).toContain('E-commerce Setup')
    // environments (default tab)
    expect(wrapper.text()).toContain('production')
    expect(wrapper.text()).toContain('staging')
    // masked token shown
    expect(wrapper.text()).toContain('nw_****************abcd')
  })

  it('regenerates the token and reveals it once', async () => {
    const wrapper = await mountPage()
    const btn = wrapper.findAll('button').find((b) => b.text() === 'Regenerate Token')
    await btn.trigger('click')
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/token/regenerate')
    expect(wrapper.text()).toContain('nw_freshly_generated_token')
    expect(wrapper.text()).toContain("won't be shown again")
  })
})
