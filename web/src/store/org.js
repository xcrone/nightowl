import { defineStore } from 'pinia'
import api from '../services/api'

const CURRENT_ORG_KEY = 'nightowl:currentOrgUuid'

// Drives the Org Dashboard ("Your Apps"): the org record plus its teams,
// each team carrying its apps + live health summary (GET /api/apps).
export const useOrgStore = defineStore('org', {
  state: () => ({
    org: null,
    orgs: [],
    teams: [],
    currentOrgUuid: localStorage.getItem(CURRENT_ORG_KEY) || null,
  }),

  actions: {
    // Every org the user belongs to — drives the org switcher. Call once;
    // fetchOrg() (below) is what re-fetches per-org teams/apps.
    async fetchOrgs() {
      const { data } = await api.get('/api/orgs')
      this.orgs = data.data ?? []
    },

    // Creates a brand-new org (used by the zero-org empty-state prompt) and
    // immediately switches the dashboard over to it.
    async createOrg({ name, account_email }) {
      const { data } = await api.post('/api/orgs', { name, account_email })
      this.orgs.push(data)
      await this.switchOrg(data.uuid)
      return data
    },

    async fetchOrg() {
      let apps
      try {
        apps = await api.get('/api/apps', {
          params: this.currentOrgUuid ? { org: this.currentOrgUuid } : {},
        })
      } catch (e) {
        // A stale currentOrgUuid (left over from a previous session/user on
        // this browser — cleared on logout, but nothing guards a browser
        // that never went through a clean logout) can point at a real org
        // the current user isn't a member of; the server 404s deliberately
        // rather than substituting silently (see ListApps). Self-heal the
        // same way an unknown/removed org UUID already does below: drop it
        // and retry without `org`, so the user falls back to their own org
        // instead of the page reading this as "you have zero orgs".
        if (e?.response?.status === 404 && this.currentOrgUuid) {
          this.currentOrgUuid = null
          localStorage.removeItem(CURRENT_ORG_KEY)
          apps = await api.get('/api/apps')
        } else {
          throw e
        }
      }
      this.org = apps.data.org ?? null
      this.teams = apps.data.teams ?? []
      // The server may fall back to a different org than requested (e.g. a
      // stale/removed membership) — keep the switcher's selection in sync
      // with whichever org actually came back.
      if (this.org?.uuid && this.org.uuid !== this.currentOrgUuid) {
        this.currentOrgUuid = this.org.uuid
        localStorage.setItem(CURRENT_ORG_KEY, this.org.uuid)
      }
    },

    async switchOrg(orgUuid) {
      this.currentOrgUuid = orgUuid
      localStorage.setItem(CURRENT_ORG_KEY, orgUuid)
      await this.fetchOrg()
    },

    // Targeted updates from a mutation's own response — cheaper than a full
    // fetchOrg() refetch, which re-runs a live health summary for every app
    // in the org just to redraw one edited row.
    setOrgDetails(patch) {
      if (this.org) Object.assign(this.org, patch)
    },

    upsertTeam(team) {
      const existing = this.teams.find((t) => t.uuid === team.uuid)
      if (existing) {
        Object.assign(existing, team)
      } else {
        this.teams.push({ ...team, apps_count: 0, apps: [] })
      }
    },

    removeTeam(teamUuid) {
      this.teams = this.teams.filter((t) => t.uuid !== teamUuid)
    },

    upsertApp(teamUuid, appItem) {
      const team = this.teams.find((t) => t.uuid === teamUuid)
      if (!team) return
      team.apps ??= []
      const existing = team.apps.find((a) => a.app_id === appItem.app_id)
      if (existing) {
        Object.assign(existing, appItem)
      } else {
        team.apps.push(appItem)
        team.apps_count = team.apps.length
      }
    },

    removeApp(teamUuid, appId) {
      const team = this.teams.find((t) => t.uuid === teamUuid)
      if (!team) return
      team.apps = (team.apps ?? []).filter((a) => a.app_id !== appId)
      team.apps_count = team.apps.length
    },

    // Clears all org-store state and its persisted org-switcher selection —
    // called on logout so a subsequent login (possibly a different user, same
    // browser) doesn't re-request a stale org that 404s for the new session.
    reset() {
      this.org = null
      this.orgs = []
      this.teams = []
      this.currentOrgUuid = null
      localStorage.removeItem(CURRENT_ORG_KEY)
    },
  },
})
