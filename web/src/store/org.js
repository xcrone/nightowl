import { defineStore } from 'pinia'
import api from '../services/api'

// Drives the Org Dashboard ("Your Apps"): the org record plus its teams,
// each team carrying its apps + live health summary (GET /api/apps).
export const useOrgStore = defineStore('org', {
  state: () => ({
    org: null,
    teams: [],
  }),

  actions: {
    async fetchOrg() {
      const [orgs, apps] = await Promise.all([
        api.get('/api/orgs'),
        api.get('/api/apps'),
      ])
      this.org = apps.data.org ?? orgs.data.data?.[0] ?? null
      this.teams = apps.data.teams ?? []
    },
  },
})
