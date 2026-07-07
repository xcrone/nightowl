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
  // FINDING #4: the api now returns the masked token under `agent_token`.
  agent_token: 'nw_****************abcd',
  template: { name: 'E-commerce Setup', synced_at: new Date().toISOString() },
  environments: { production: '#10b981', staging: '#f59e0b' },
  // Pre-existing setting keys the tabs hydrate from.
  'threshold.requests.duration_ms': 500,
  'issues.auto_resolve_days': 30,
}

const storage = {
  tables: [
    { name: 'nightowl_entries', bytes: 285_212_672, rows: 1_204_553 },
    { name: 'nightowl_exceptions', bytes: 4_194_304, rows: 812 },
  ],
  total_bytes: 289_406_976,
}

const currentApp = {
  app_id: '3FoNKDbo7D5S9MGhLx9qybejLCE',
  name: 'Northwind Web',
  description: 'The Northwind storefront',
  db_connection: 'pg://northwind',
  environments: { production: '#10b981', staging: '#f59e0b' },
}

async function mountPage() {
  api.get.mockImplementation((url) => {
    if (url.includes('/alert-channels')) return Promise.resolve({ data: { data: [] } })
    if (url.includes('/settings/storage')) return Promise.resolve({ data: storage })
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
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            app: { apps: [{ app_id: 'other1', name: 'Delta API' }], current: { ...currentApp } },
          },
        }),
      ],
    },
  })
  await flushPromises()
  return wrapper
}

const clickTab = async (wrapper, label) => {
  await wrapper.findAll('button').find((b) => b.text() === label).trigger('click')
  await flushPromises()
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
  })

  it('"Edit app" opens the shared app modal prefilled, and saves via PUT /api/apps/{id}', async () => {
    const wrapper = await mountPage()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    const editButton = wrapper.findAll('button').find((b) => b.text() === 'Edit app')
    await editButton.trigger('click')

    const dialog = wrapper.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.text()).toContain('Edit app')
    // Prefilled from the already-loaded app record (name/description/db_connection).
    expect(wrapper.find('[data-test="app-modal-name"]').element.value).toBe('Northwind Web')

    api.put.mockResolvedValueOnce({
      data: { app_id: '3FoNKDbo7D5S9MGhLx9qybejLCE', name: 'Northwind Renamed', description: 'The Northwind storefront', db_connection: 'pg://northwind' },
    })
    api.get.mockImplementation((url) => {
      if (url.includes('/alert-channels')) return Promise.resolve({ data: { data: [] } })
      if (url.includes('/settings/storage')) return Promise.resolve({ data: storage })
      return Promise.resolve({ data: { settings: { ...settings, name: 'Northwind Renamed' } } })
    })

    await wrapper.find('[data-test="app-modal-name"]').setValue('Northwind Renamed')
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE', {
      name: 'Northwind Renamed',
      description: 'The Northwind storefront',
      db_connection: 'pg://northwind',
    })
    // modal closes and the page header reflects the rename
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Northwind Renamed')
  })

  it('reads the masked token from the `agent_token` key (FINDING #4)', async () => {
    const wrapper = await mountPage()
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

  it('does not claim changes are disabled — Thresholds/Issues saves actually persist', async () => {
    const wrapper = await mountPage()
    // The old blanket banner falsely claimed every setting was read-only even
    // though Thresholds/Issues saves genuinely hit the API and persist.
    expect(wrapper.text()).not.toContain('Read-only demo — explore every setting; changes are disabled.')
    expect(wrapper.text()).not.toContain('changes are disabled')
  })

  describe('Thresholds tab', () => {
    it('lists every duration-threshold resource type', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Thresholds')
      for (const label of ['Routes', 'Jobs', 'Commands', 'Scheduled Tasks', 'Queries', 'Outgoing Requests', 'Mail', 'Notifications', 'Cache']) {
        expect(wrapper.text()).toContain(label)
      }
    })

    it('hydrates a pre-existing threshold value and persists edits via PUT settings/{key}', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Thresholds')
      // requests threshold pre-populated from settings → shown as an active input.
      const input = wrapper.findAll('input[type="number"]').find((i) => i.element.value === '500')
      expect(input).toBeTruthy()
      await input.setValue('750')
      await wrapper.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      // UpdateAppSetting requires a string value.
      expect(api.put).toHaveBeenCalledWith(
        '/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/settings/threshold.requests.duration_ms',
        { value: '750' },
      )
    })

    it('adds a threshold for an unconfigured resource type and saves it', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Thresholds')
      // Jobs has no configured threshold → its row shows an "Add threshold" button.
      const jobsRow = wrapper.findAll('li').find((li) => li.text().startsWith('Jobs'))
      expect(jobsRow.text()).toContain('Add threshold')
      await jobsRow.find('button').trigger('click')
      await flushPromises()
      await jobsRow.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      expect(api.put).toHaveBeenCalledWith(
        '/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/settings/threshold.jobs.duration_ms',
        { value: '1000' },
      )
    })

    it('surfaces a save error instead of swallowing it silently', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Thresholds')
      api.put.mockRejectedValueOnce({ response: { data: { message: 'Threshold must be positive.' } } })
      const requestsRow = wrapper.findAll('li').find((li) => li.text().startsWith('Routes'))
      await requestsRow.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      expect(requestsRow.text()).toContain('Threshold must be positive.')
    })
  })

  describe('Issues tab', () => {
    it('hydrates the auto-resolve window and persists it via PUT settings/{key}', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Issues')
      // Scope to the auto-resolve select (the "Apply a template" dropdown is also a <select>).
      const select = wrapper.findAll('select').find((s) => s.text().includes('days'))
      expect(select.element.value).toBe('30')
      await select.setValue('14')
      await wrapper.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      expect(api.put).toHaveBeenCalledWith(
        '/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/settings/issues.auto_resolve_days',
        { value: '14' },
      )
    })

    it('surfaces a save error instead of swallowing it silently', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Issues')
      api.put.mockRejectedValueOnce({ response: { data: { message: 'Could not update auto-resolve window.' } } })
      await wrapper.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      expect(wrapper.text()).toContain('Could not update auto-resolve window.')
    })
  })

  describe('Storage tab', () => {
    it('fetches live storage and renders per-table footprint plus total', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Storage')
      expect(api.get).toHaveBeenCalledWith('/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/settings/storage')
      expect(wrapper.text()).toContain('nightowl_entries')
      expect(wrapper.text()).toContain('nightowl_exceptions')
      // human-readable bytes (1024-based): total 289_406_976 → 276 MB,
      // nightowl_entries 285_212_672 → 272 MB
      expect(wrapper.text()).toContain('276 MB')
      expect(wrapper.text()).toContain('272 MB')
      // row counts rendered with thousands separators
      expect(wrapper.text()).toContain('1,204,553')
    })
  })

  describe('Danger Zone tab', () => {
    it('keeps the disabled destructive actions and its own accurate notice', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Danger Zone')
      const transfer = wrapper.findAll('button').find((b) => b.text() === 'Transfer app')
      const destroy = wrapper.findAll('button').find((b) => b.text() === 'Delete app')
      expect(transfer.attributes('disabled')).toBeDefined()
      expect(destroy.attributes('disabled')).toBeDefined()
      expect(wrapper.text()).toContain('Destructive actions are disabled in this read-only demo.')
    })
  })

  describe('Alerts tab', () => {
    it('offers an "Add channel" affordance', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Alerts')
      const addButton = wrapper.findAll('button').find((b) => b.text().includes('Add channel'))
      expect(addButton).toBeTruthy()
    })

    it('opens a modal and creates a slack channel, refreshing the list afterward', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Alerts')

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      await wrapper.findAll('button').find((b) => b.text().includes('Add channel')).trigger('click')
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

      const newChannel = {
        id: 1,
        uuid: 'chan-uuid-1',
        app_id: '3FoNKDbo7D5S9MGhLx9qybejLCE',
        name: '#incidents',
        type: 'slack',
        config: { webhook_url: 'https://hooks.slack.com/services/xxx' },
        enabled: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      api.post.mockResolvedValueOnce({ data: newChannel })
      api.get.mockImplementation((url) => {
        if (url.includes('/alert-channels')) return Promise.resolve({ data: { data: [newChannel] } })
        if (url.includes('/settings/storage')) return Promise.resolve({ data: storage })
        return Promise.resolve({ data: { settings } })
      })

      await wrapper.find('[data-test="alert-channel-modal-name"]').setValue('#incidents')
      await wrapper.find('[data-test="alert-channel-modal-type"]').setValue('slack')
      await wrapper.find('[data-test="alert-channel-modal-webhook-url"]').setValue('https://hooks.slack.com/services/xxx')
      await wrapper.find('[data-test="alert-channel-modal-submit"]').trigger('click')
      await flushPromises()

      expect(api.post).toHaveBeenCalledWith('/api/apps/3FoNKDbo7D5S9MGhLx9qybejLCE/alert-channels', {
        name: '#incidents',
        type: 'slack',
        config: { webhook_url: 'https://hooks.slack.com/services/xxx' },
      })
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(wrapper.text()).toContain('#incidents')
    })

    it('shows an inline error and does not call the API when name is empty', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Alerts')
      await wrapper.findAll('button').find((b) => b.text().includes('Add channel')).trigger('click')

      await wrapper.find('[data-test="alert-channel-modal-type"]').setValue('slack')
      await wrapper.find('[data-test="alert-channel-modal-webhook-url"]').setValue('https://hooks.slack.com/services/xxx')
      await wrapper.find('[data-test="alert-channel-modal-submit"]').trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Name is required.')
      expect(api.post).not.toHaveBeenCalled()
    })
  })

  describe('Thresholds tab — save confirmation', () => {
    it('shows a "Saved!" confirmation after a successful save', async () => {
      const wrapper = await mountPage()
      await clickTab(wrapper, 'Thresholds')
      const requestsRow = wrapper.findAll('li').find((li) => li.text().startsWith('Routes'))
      await requestsRow.findAll('button').find((b) => b.text() === 'Save').trigger('click')
      await flushPromises()
      expect(requestsRow.text()).toContain('Saved!')
    })
  })
})
