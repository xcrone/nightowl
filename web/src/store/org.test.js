import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}))
import api from '../services/api'
import { useOrgStore } from './org'

const CURRENT_ORG_KEY = 'nightowl:currentOrgUuid'

beforeEach(() => {
  localStorage.clear()
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('reset', () => {
  it('clears org, orgs, teams, currentOrgUuid and the persisted localStorage key', () => {
    localStorage.setItem(CURRENT_ORG_KEY, 'org-a-uuid')
    const org = useOrgStore()
    org.org = { uuid: 'org-a-uuid', name: 'Org A' }
    org.orgs = [{ uuid: 'org-a-uuid', name: 'Org A' }]
    org.teams = [{ uuid: 'team-1', name: 'Team 1' }]
    org.currentOrgUuid = 'org-a-uuid'

    org.reset()

    expect(org.org).toBeNull()
    expect(org.orgs).toEqual([])
    expect(org.teams).toEqual([])
    expect(org.currentOrgUuid).toBeNull()
    expect(localStorage.getItem(CURRENT_ORG_KEY)).toBeNull()
  })
})

describe('fetchOrg', () => {
  it('self-heals a stale currentOrgUuid that 404s by retrying without org and clearing it', async () => {
    localStorage.setItem(CURRENT_ORG_KEY, 'stale-org-uuid')
    const fallbackOrg = { uuid: 'my-real-org-uuid', name: 'My Org' }
    api.get.mockImplementation((url, config) => {
      if (url === '/api/apps' && config?.params?.org === 'stale-org-uuid') {
        return Promise.reject({ response: { status: 404 } })
      }
      if (url === '/api/apps' && !config?.params?.org) {
        return Promise.resolve({ data: { org: fallbackOrg, teams: [] } })
      }
      return Promise.reject(new Error(`unexpected GET ${url}`))
    })
    const org = useOrgStore()
    expect(org.currentOrgUuid).toBe('stale-org-uuid')

    await org.fetchOrg()

    expect(org.org).toEqual(fallbackOrg)
    expect(org.currentOrgUuid).toBe(fallbackOrg.uuid)
    expect(localStorage.getItem(CURRENT_ORG_KEY)).toBe(fallbackOrg.uuid)
  })

  it('does not swallow non-404 errors', async () => {
    localStorage.setItem(CURRENT_ORG_KEY, 'some-org-uuid')
    api.get.mockRejectedValue({ response: { status: 500 } })
    const org = useOrgStore()

    await expect(org.fetchOrg()).rejects.toEqual({ response: { status: 500 } })
  })
})

describe('createOrg', () => {
  it('posts the new org, appends it to orgs, and switches to it', async () => {
    const newOrg = { uuid: 'org-new-uuid', name: 'New Org', account_email: 'new@example.com' }
    api.post.mockResolvedValue({ data: newOrg })
    api.get.mockImplementation((url) => {
      if (url === '/api/apps') return Promise.resolve({ data: { org: newOrg, teams: [] } })
      return Promise.reject(new Error(`unexpected GET ${url}`))
    })
    const org = useOrgStore()

    const result = await org.createOrg({ name: newOrg.name, account_email: newOrg.account_email })

    expect(api.post).toHaveBeenCalledWith('/api/orgs', { name: newOrg.name, account_email: newOrg.account_email })
    expect(org.orgs).toContainEqual(newOrg)
    expect(org.currentOrgUuid).toBe(newOrg.uuid)
    expect(localStorage.getItem(CURRENT_ORG_KEY)).toBe(newOrg.uuid)
    expect(result).toEqual(newOrg)
  })
})
