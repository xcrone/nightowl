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
