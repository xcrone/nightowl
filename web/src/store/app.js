import { defineStore } from 'pinia'
import api from '../services/api'

const PERIOD_KEY = 'nightowl-period'
const TIMEZONE_KEY = 'nightowl-timezone'
const TIME_FORMAT_KEY = 'nightowl-time-format'

// The per-app store: holds the currently-selected app plus the top-bar
// window controls (period/timezone/format) that every feature page reads.
export const useAppStore = defineStore('app', {
  state: () => ({
    current: null,
    apps: [],
    teams: [],
    org: null,
    period: localStorage.getItem(PERIOD_KEY) ?? '1h',
    timezone: localStorage.getItem(TIMEZONE_KEY) ?? 'Local',
    timeFormat: localStorage.getItem(TIME_FORMAT_KEY) ?? '24h',
    // App-switcher "All environments" filter. `null` = all; not persisted —
    // it's app-scoped and reset when the current app changes.
    environment: null,
  }),

  getters: {
    // Open-issue count for the sidebar Issues badge. The single-app record
    // may not carry it, so fall back to the health summary in the apps list.
    openIssues: (state) => {
      const id = state.current?.app_id
      const listed = state.apps.find((a) => a.app_id === id)
      return listed?.open_issues ?? state.current?.open_issues ?? 0
    },
  },

  actions: {
    async fetchApps() {
      const { data } = await api.get('/api/apps')
      this.org = data.org ?? this.org
      this.teams = data.teams ?? []
      this.apps = (data.teams ?? []).flatMap((team) => team.apps ?? [])
      return this.apps
    },

    async setCurrentApp(appId) {
      const { data } = await api.get(`/api/apps/${appId}`)
      this.current = data.app ?? data
      return this.current
    },

    setPeriod(period) {
      this.period = period
      localStorage.setItem(PERIOD_KEY, period)
    },

    setTimezone(timezone) {
      this.timezone = timezone
      localStorage.setItem(TIMEZONE_KEY, timezone)
    },

    setTimeFormat(timeFormat) {
      this.timeFormat = timeFormat
      localStorage.setItem(TIME_FORMAT_KEY, timeFormat)
    },

    // `null` clears the filter ("All environments"). List pages read this and
    // pass it as `?environment=` on their fetches.
    setEnvironment(environment) {
      this.environment = environment
    },

    // Targeted update after an app edit (e.g. SettingsPage's "Edit app"
    // modal) — cheaper than refetching /api/apps, and keeps `current` plus
    // any cached listing (`apps`/`teams`, used by the app switcher) in sync
    // with the PUT response instead of going stale until the next reload.
    patchApp(patch) {
      if (!patch?.app_id) return
      if (this.current?.app_id === patch.app_id) Object.assign(this.current, patch)
      const listed = this.apps.find((a) => a.app_id === patch.app_id)
      if (listed) Object.assign(listed, patch)
      for (const team of this.teams) {
        const inTeam = (team.apps ?? []).find((a) => a.app_id === patch.app_id)
        if (inTeam) Object.assign(inTeam, patch)
      }
    },
  },
})
