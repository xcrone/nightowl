import { defineStore } from 'pinia'
import api, { csrfCookie } from '../services/api'
import { useOrgStore } from './org'

// Non-sensitive "have we logged in on this browser before" signal. Lets the
// bootstrap check skip GET /api/user entirely for a fresh/logged-out browser,
// avoiding a console-logged 401 that application code can't suppress.
const AUTHENTICATED_FLAG_KEY = 'nightowl:authenticated'

function rememberAuthenticated() {
  localStorage.setItem(AUTHENTICATED_FLAG_KEY, '1')
}

function forgetAuthenticated() {
  localStorage.removeItem(AUTHENTICATED_FLAG_KEY)
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    checked: false,
  }),

  actions: {
    async login(email, password) {
      await csrfCookie()
      await api.post('/login', { email, password })
      rememberAuthenticated()
      await this.fetchUser()
    },

    async register(name, email, password, passwordConfirmation, orgName) {
      await csrfCookie()
      await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        org_name: orgName,
      })
      rememberAuthenticated()
      await this.fetchUser()
    },

    async logout() {
      await api.post('/logout')
      this.user = null
      forgetAuthenticated()
      useOrgStore().reset()
    },

    async fetchUser() {
      if (!localStorage.getItem(AUTHENTICATED_FLAG_KEY)) {
        this.user = null
        this.checked = true
        return
      }

      try {
        const { data } = await api.get('/api/user')
        this.user = data.user
      } catch {
        this.user = null
        forgetAuthenticated()
      } finally {
        this.checked = true
      }
    },
  },
})
