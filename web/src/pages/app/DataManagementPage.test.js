import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../../services/api', () => ({ default: { get: vi.fn(), post: vi.fn() }, csrfCookie: vi.fn() }))
import api from '../../services/api'
import DataManagementPage from './DataManagementPage.vue'

async function mountPage() {
  api.post.mockResolvedValue({ data: { counts: { requests: 1234, queries: 5678 }, total: 6912 } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/dashboard/:appId', component: { template: '<div />' } }],
  })
  await router.push('/dashboard/app1')
  await router.isReady()
  const wrapper = mount(DataManagementPage, {
    global: { plugins: [router, createTestingPinia({ createSpy: vi.fn })] },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('DataManagementPage', () => {
  it('validates that at least one data type must be selected', async () => {
    const wrapper = await mountPage()
    const previewBtn = wrapper.findAll('button').find((b) => b.text().includes('Preview Impact'))
    await previewBtn.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Select at least one data type.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('selects a chip, previews impact and renders the returned counts + total', async () => {
    const wrapper = await mountPage()
    const chip = wrapper.findAll('button').find((b) => b.text() === 'Requests')
    await chip.trigger('click')

    const previewBtn = wrapper.findAll('button').find((b) => b.text().includes('Preview Impact'))
    await previewBtn.trigger('click')
    await flushPromises()

    const call = api.post.mock.calls[0]
    expect(call[0]).toBe('/api/apps/app1/data-management/preview')
    expect(call[1].types).toEqual(['requests'])

    expect(wrapper.text()).toContain('1234')
    expect(wrapper.text()).toContain('6912')
  })

  it('reveals the optional Filters card only once a type is selected', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).not.toContain('Filters (optional)')
    const chip = wrapper.findAll('button').find((b) => b.text() === 'Requests')
    await chip.trigger('click')
    expect(wrapper.text()).toContain('Filters (optional)')
    // Log Level only shows when Logs is among the selected types.
    expect(wrapper.text()).not.toContain('Log Level')
    const logs = wrapper.findAll('button').find((b) => b.text() === 'Logs')
    await logs.trigger('click')
    expect(wrapper.text()).toContain('Log Level')
  })

  it('sends non-empty user_id and (logs-only) level in the preview body', async () => {
    const wrapper = await mountPage()
    const logs = wrapper.findAll('button').find((b) => b.text() === 'Logs')
    await logs.trigger('click')

    const userInput = wrapper.find('input[type="text"]')
    await userInput.setValue('user_11')
    const levelSelect = wrapper.find('select')
    await levelSelect.setValue('error')

    const previewBtn = wrapper.findAll('button').find((b) => b.text().includes('Preview Impact'))
    await previewBtn.trigger('click')
    await flushPromises()

    const body = api.post.mock.calls[0][1]
    expect(body.user_id).toBe('user_11')
    expect(body.level).toBe('error')
  })

  it('omits empty optional filters from the preview body', async () => {
    const wrapper = await mountPage()
    const chip = wrapper.findAll('button').find((b) => b.text() === 'Requests')
    await chip.trigger('click')
    const previewBtn = wrapper.findAll('button').find((b) => b.text().includes('Preview Impact'))
    await previewBtn.trigger('click')
    await flushPromises()

    const body = api.post.mock.calls[0][1]
    expect(body).not.toHaveProperty('user_id')
    expect(body).not.toHaveProperty('level')
  })

  it('select-all toggles every data type', async () => {
    const wrapper = await mountPage()
    const selectAll = wrapper.findAll('button').find((b) => b.text() === 'Select all')
    await selectAll.trigger('click')
    const previewBtn = wrapper.findAll('button').find((b) => b.text().includes('Preview Impact'))
    await previewBtn.trigger('click')
    await flushPromises()
    expect(api.post.mock.calls[0][1].types.length).toBe(11)
  })
})
