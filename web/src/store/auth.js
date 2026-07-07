import { defineStore } from 'pinia'
import api, { csrfCookie } from '../services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    checked: false,
  }),

  actions: {
    async login(email, password) {
      await csrfCookie()
      await api.post('/login', { email, password })
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
      await this.fetchUser()
    },

    async logout() {
      await api.post('/logout')
      this.user = null
    },

    async fetchUser() {
      try {
        const { data } = await api.get('/api/user')
        this.user = data.user
      } catch {
        this.user = null
      } finally {
        this.checked = true
      }
    },
  },
})
