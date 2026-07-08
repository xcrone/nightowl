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

async function mountPage({
  existingOrg = org,
  members = [{ uuid: 'member-1', name: 'Zahir', email: 'z@x.c' }],
  pendingInvitations = [],
} = {}) {
  api.get.mockImplementation((url) => {
    if (url === `/api/orgs/${org.uuid}/members`) return Promise.resolve({ data: { data: members } })
    if (url === `/api/orgs/${org.uuid}/invitations`) return Promise.resolve({ data: { data: pendingInvitations } })
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

  it('sends an invitation', async () => {
    api.post.mockResolvedValue({
      data: {
        uuid: 'invite-1',
        email: 'new@example.com',
        status: 'pending',
        created_at: '2026-01-01T00:00:00Z',
        responded_at: null,
        org: { uuid: org.uuid, name: org.name },
      },
    })
    const { wrapper } = await mountPage({ members: [] })

    await wrapper.find('[data-test="new-member-email"]').setValue('new@example.com')
    await wrapper.find('[data-test="send-invite"]').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/invitations`, { email: 'new@example.com' })
    expect(wrapper.text()).toContain('new@example.com')
    expect(wrapper.text()).not.toContain('Zahir')
  })

  it('lists pending invitations sent by the org', async () => {
    const { wrapper } = await mountPage({
      pendingInvitations: [
        {
          uuid: 'invite-2',
          email: 'pending@example.com',
          status: 'pending',
          created_at: '2026-01-01T00:00:00Z',
          responded_at: null,
          org: { uuid: org.uuid, name: org.name },
        },
      ],
    })

    expect(api.get).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/invitations`)
    expect(wrapper.text()).toContain('pending@example.com')
  })

  it('cancels a pending invitation', async () => {
    vi.stubGlobal('confirm', vi.fn(() => true))
    api.delete.mockResolvedValue({})
    const { wrapper } = await mountPage({
      pendingInvitations: [
        {
          uuid: 'invite-2',
          email: 'pending@example.com',
          status: 'pending',
          created_at: '2026-01-01T00:00:00Z',
          responded_at: null,
          org: { uuid: org.uuid, name: org.name },
        },
      ],
    })

    await wrapper.find('[data-test="cancel-invitation"]').trigger('click')
    await flushPromises()

    expect(api.delete).toHaveBeenCalledWith(`/api/orgs/${org.uuid}/invitations/invite-2`)
    expect(wrapper.text()).not.toContain('pending@example.com')
    vi.unstubAllGlobals()
  })

  it('shows an inline error when inviting fails', async () => {
    api.post.mockRejectedValue({
      response: { data: { errors: { email: ['This person is already a member of this org.'] } } },
    })
    const { wrapper } = await mountPage({ members: [] })

    await wrapper.find('[data-test="new-member-email"]').setValue('new@example.com')
    await wrapper.find('[data-test="send-invite"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('This person is already a member of this org.')
    expect(wrapper.text()).not.toContain('new@example.com')
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

  it("shows a 'no organization yet' message and a link back to the dashboard instead of the org/members forms when the user has no organization", async () => {
    const { wrapper } = await mountPage({ existingOrg: null })

    const backLink = wrapper.find('[data-test="no-org-back-link"]')
    expect(backLink.exists()).toBe(true)
    expect(backLink.attributes('href')).toBe('/')
    expect(wrapper.text()).not.toContain('Name:')
    expect(wrapper.find('[data-test="edit-org"]').exists()).toBe(false)
  })
})
