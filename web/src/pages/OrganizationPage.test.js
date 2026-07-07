import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createTestingPinia } from '@pinia/testing'

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  csrfCookie: vi.fn(),
}))
import api from '../services/api'
import OrganizationPage from './OrganizationPage.vue'

const org = { uuid: 'org-uuid-1', name: 'Owlworks', account_email: 'owlworks@example.com' }

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/organization', component: OrganizationPage },
    ],
  })
}

async function mountPage({ existingOrg = org, members = [{ uuid: 'member-1', name: 'Zahir', email: 'z@x.c' }] } = {}) {
  api.get.mockImplementation((url) => {
    if (url === `/api/orgs/${org.uuid}/members`) return Promise.resolve({ data: { data: members } })
    return Promise.reject(new Error(`unexpected GET ${url}`))
  })
  const router = makeRouter()
  const wrapper = mount(OrganizationPage, {
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          stubActions: false,
          initialState: { org: { org: existingOrg, orgs: [], teams: [] } },
        }),
      ],
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => vi.clearAllMocks())

describe('OrganizationPage', () => {
  it('renders org details and loads members', async () => {
    const { wrapper } = await mountPage()
    expect(wrapper.text()).toContain('Owlworks')
    expect(wrapper.text()).toContain('owlworks@example.com')
    expect(api.get).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/members`)
    expect(wrapper.text()).toContain('Zahir')
  })

  it('edits and saves org details', async () => {
    api.put.mockResolvedValue({ data: { uuid: org.uuid, name: 'Renamed Org', account_email: org.account_email } })
    const { wrapper } = await mountPage()

    await wrapper.find('[data-test="edit-org"]').trigger('click')
    await wrapper.find('[data-test="org-name-input"]').setValue('Renamed Org')
    await wrapper.find('[data-test="save-org"]').trigger('click')
    await flushPromises()

    expect(api.put).toHaveBeenCalledWith(`/api/orgs/${org.uuid}`, {
      name: 'Renamed Org',
      account_email: org.account_email,
    })
    expect(wrapper.text()).toContain('Renamed Org')
  })

  it('adds a member', async () => {
    api.post.mockResolvedValue({ data: { uuid: 'member-2', name: 'New Member', email: 'new@example.com' } })
    const { wrapper } = await mountPage({ members: [] })

    await wrapper.find('[data-test="new-member-email"]').setValue('new@example.com')
    await wrapper.find('[data-test="add-member"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/members`, { email: 'new@example.com' })
    expect(wrapper.text()).toContain('New Member')
  })

  it('removes a member', async () => {
    vi.stubGlobal('confirm', vi.fn(() => true))
    api.delete.mockResolvedValue({})
    const { wrapper } = await mountPage()

    const removeButtons = wrapper.findAll('button').filter((b) => b.text() === 'Remove')
    await removeButtons[0].trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/members/member-1`)
    expect(wrapper.text()).not.toContain('Zahir')
    vi.unstubAllGlobals()
  })

  it('has a link back to the dashboard', async () => {
    const { wrapper, router } = await mountPage()
    const push = vi.spyOn(router, 'push')
    await wrapper.find('[data-test="back-to-dashboard"]').trigger('click')
    expect(push).toHaveBeenCalledWith('/')
  })
})
