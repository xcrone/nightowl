import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  csrfCookie: vi.fn(),
}))
import api from '../services/api'
import AppFormModal from './AppFormModal.vue'

beforeEach(() => vi.clearAllMocks())

describe('AppFormModal', () => {
  it('renders nothing until opened', () => {
    const wrapper = mount(AppFormModal)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('openCreate opens a blank "Add app" form and POSTs to the team', async () => {
    api.post.mockResolvedValue({ data: { app_id: 'a1', name: 'New App', description: '' } })
    const wrapper = mount(AppFormModal)
    wrapper.vm.openCreate({ uuid: 'team-1', name: 'Delta Payments' })
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Add app to Delta Payments')
    expect(wrapper.find('[data-test="app-modal-name"]').element.value).toBe('')

    await wrapper.find('[data-test="app-modal-name"]').setValue('New App')
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/api/teams/team-1/apps', {
      name: 'New App',
      description: '',
    })
    expect(wrapper.emitted('saved').at(-1)[0]).toMatchObject({
      mode: 'create',
      team: { uuid: 'team-1', name: 'Delta Payments' },
      app: { app_id: 'a1', name: 'New App' },
    })
    // closes itself on success
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('openEdit prefills the form from the given app and PUTs to /api/apps/{id}', async () => {
    api.put.mockResolvedValue({ data: { app_id: 'a1', name: 'Renamed', description: 'desc' } })
    const wrapper = mount(AppFormModal)
    wrapper.vm.openEdit({ app_id: 'a1', name: 'Delta API', description: 'desc' })
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Edit app')
    expect(wrapper.find('[data-test="app-modal-name"]').element.value).toBe('Delta API')

    await wrapper.find('[data-test="app-modal-name"]').setValue('Renamed')
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith('/api/apps/a1', {
      name: 'Renamed',
      description: 'desc',
    })
    expect(wrapper.emitted('saved').at(-1)[0]).toMatchObject({ mode: 'edit', app: { name: 'Renamed' } })
  })

  it('openEdit works without a team (no team context needed to save)', async () => {
    api.put.mockResolvedValue({ data: { app_id: 'a1', name: 'Solo Edit' } })
    const wrapper = mount(AppFormModal)
    wrapper.vm.openEdit({ app_id: 'a1', name: 'Delta API' })
    await wrapper.vm.$nextTick()
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/api/apps/a1', expect.any(Object))
    expect(wrapper.emitted('saved').at(-1)[0].team).toBeNull()
  })

  it('surfaces a save error instead of swallowing it', async () => {
    api.put.mockRejectedValue({ response: { data: { message: 'Could not save app.' } } })
    const wrapper = mount(AppFormModal)
    wrapper.vm.openEdit({ app_id: 'a1', name: 'Delta API' })
    await wrapper.vm.$nextTick()
    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Could not save app.')
    // stays open on failure
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
  })

  it('shows inline feedback and does not save when name is blank', async () => {
    const wrapper = mount(AppFormModal)
    wrapper.vm.openCreate({ uuid: 'team-1', name: 'Delta Payments' })
    await wrapper.vm.$nextTick()

    await wrapper.find('[data-test="app-modal-submit"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Name is required.')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('closes on Cancel without saving', async () => {
    const wrapper = mount(AppFormModal)
    wrapper.vm.openEdit({ app_id: 'a1', name: 'Delta API' })
    await wrapper.vm.$nextTick()
    await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(api.put).not.toHaveBeenCalled()
  })
})
