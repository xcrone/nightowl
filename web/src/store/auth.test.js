import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn() },
  csrfCookie: vi.fn(),
}))
import api, { csrfCookie } from '../services/api'
import { useAuthStore } from './auth'

const FLAG_KEY = 'nightowl:authenticated'

beforeEach(() => {
  localStorage.clear()
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('fetchUser', () => {
  it('skips GET /api/user when there is no previously-authenticated flag', async () => {
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(api.get).not.toHaveBeenCalled()
    expect(auth.user).toBeNull()
    expect(auth.checked).toBe(true)
  })

  it('calls GET /api/user when the previously-authenticated flag is set', async () => {
    localStorage.setItem(FLAG_KEY, '1')
    api.get.mockResolvedValue({ data: { user: { email: 'admin@example.com' } } })
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(api.get).toHaveBeenCalledWith('/api/user')
    expect(auth.user).toEqual({ email: 'admin@example.com' })
    expect(auth.checked).toBe(true)
  })

  it('clears the flag and user when a stale session 401s', async () => {
    localStorage.setItem(FLAG_KEY, '1')
    api.get.mockRejectedValue({ response: { status: 401 } })
    const auth = useAuthStore()

    await auth.fetchUser()

    expect(auth.user).toBeNull()
    expect(auth.checked).toBe(true)
    expect(localStorage.getItem(FLAG_KEY)).toBeNull()
  })
})

describe('login', () => {
  it('sets the flag and fetches the user', async () => {
    csrfCookie.mockResolvedValue()
    api.post.mockResolvedValue()
    api.get.mockResolvedValue({ data: { user: { email: 'admin@example.com' } } })
    const auth = useAuthStore()

    await auth.login('admin@example.com', 'password')

    expect(localStorage.getItem(FLAG_KEY)).toBe('1')
    expect(api.get).toHaveBeenCalledWith('/api/user')
    expect(auth.user).toEqual({ email: 'admin@example.com' })
  })
})

describe('register', () => {
  it('sets the flag and fetches the user', async () => {
    csrfCookie.mockResolvedValue()
    api.post.mockResolvedValue()
    api.get.mockResolvedValue({ data: { user: { email: 'new@example.com' } } })
    const auth = useAuthStore()

    await auth.register('New', 'new@example.com', 'password', 'password', 'Org')

    expect(localStorage.getItem(FLAG_KEY)).toBe('1')
    expect(auth.user).toEqual({ email: 'new@example.com' })
  })
})

describe('logout', () => {
  it('clears the flag and the user', async () => {
    localStorage.setItem(FLAG_KEY, '1')
    api.post.mockResolvedValue()
    const auth = useAuthStore()
    auth.user = { email: 'admin@example.com' }

    await auth.logout()

    expect(auth.user).toBeNull()
    expect(localStorage.getItem(FLAG_KEY)).toBeNull()
  })
})
