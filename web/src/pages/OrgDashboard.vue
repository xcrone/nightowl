<script setup>
import { reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOrgStore } from '../store/org'
import { useAuthStore } from '../store/auth'
import StatusDot from '../components/StatusDot.vue'
import { relativeTime } from '../utils/format'
import { BADGE } from '../resourceConfig'

// The landing page ("Your Apps"): every app the org can see, grouped by team,
// with a live health summary per app. Client-side search over team/app names
// and a Teams/Apps view toggle.
const org = useOrgStore()
const auth = useAuthStore()
const router = useRouter()

const ui = reactive({ search: '', view: 'teams' })

onMounted(() => {
  org.fetchOrg().catch(() => {})
})

const query = computed(() => ui.search.trim().toLowerCase())

// Teams (and their apps) filtered by the search box. A team matches if its own
// name matches, otherwise only its matching apps are kept.
const filteredTeams = computed(() => {
  const q = query.value
  if (!q) return org.teams
  return org.teams
    .map((team) => {
      if (team.name?.toLowerCase().includes(q)) return team
      const apps = (team.apps ?? []).filter((a) => a.name?.toLowerCase().includes(q))
      return apps.length ? { ...team, apps } : null
    })
    .filter(Boolean)
})

const flatApps = computed(() => filteredTeams.value.flatMap((t) => t.apps ?? []))

function errorRateBadge(rate) {
  const n = Number(rate ?? 0)
  if (n >= 5) return BADGE.red
  if (n >= 1) return BADGE.yellow
  return BADGE.green
}

function openApp(appId) {
  router.push(`/dashboard/${appId}`)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto max-w-6xl">
      <!-- Header -->
      <div class="mb-6 flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500 text-lg font-bold text-white">🦉</span>
          <div>
            <h1 class="text-xl font-semibold">Your Apps</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Welcome back, {{ org.org?.name ?? '…' }}</p>
          </div>
        </div>
        <button
          type="button"
          class="rounded border border-gray-300 px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
          title="Account"
        >
          {{ auth.user?.email ?? 'Account' }}
        </button>
      </div>

      <!-- Toolbar -->
      <div class="mb-6 flex flex-wrap items-center gap-3">
        <input
          v-model="ui.search"
          type="text"
          placeholder="Search clients and apps"
          class="min-w-64 flex-1 rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <div class="inline-flex items-center rounded-md border border-gray-200 bg-white p-0.5 dark:border-gray-700 dark:bg-gray-800">
          <button
            v-for="opt in [{ value: 'teams', label: 'Teams' }, { value: 'apps', label: 'Apps' }]"
            :key="opt.value"
            type="button"
            class="rounded px-3 py-1 text-sm font-medium transition-colors"
            :class="
              ui.view === opt.value
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400'
                : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
            "
            @click="ui.view = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Teams view -->
      <div v-if="ui.view === 'teams'" class="space-y-8">
        <section v-for="team in filteredTeams" :key="team.id">
          <div class="mb-3 flex items-center gap-2">
            <span class="text-gray-400">👥</span>
            <h2 class="text-base font-semibold">{{ team.name }}</h2>
            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              {{ team.apps_count ?? team.apps?.length ?? 0 }}
            </span>
          </div>
          <div class="rounded-xl border-2 border-dashed border-green-400/40 p-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <button
                v-for="appItem in team.apps"
                :key="appItem.app_id"
                type="button"
                class="text-left"
                @click="openApp(appItem.app_id)"
              >
                <div
                  class="h-full rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                  <div class="mb-2 flex items-start justify-between gap-2">
                    <h3 class="truncate font-semibold">{{ appItem.name }}</h3>
                    <span
                      v-if="appItem.alerts > 0"
                      class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium"
                      :class="BADGE.yellow"
                      title="Active alerts"
                    >⚠ {{ appItem.alerts }}</span>
                  </div>
                  <p class="mb-3 truncate text-xs text-gray-400 dark:text-gray-500" :title="appItem.db_connection">
                    {{ appItem.db_connection }}
                  </p>
                  <div class="mb-3 flex flex-wrap gap-1.5">
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="errorRateBadge(appItem.error_rate)">
                      {{ Number(appItem.error_rate ?? 0).toFixed(2) }}% err
                    </span>
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.count_5xx > 0 ? BADGE.red : BADGE.gray">
                      {{ appItem.count_5xx ?? 0 }} 5xx
                    </span>
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.exceptions > 0 ? BADGE.yellow : BADGE.gray">
                      {{ appItem.exceptions ?? 0 }} exc
                    </span>
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BADGE.gray">
                      {{ appItem.open_issues ?? 0 }} issues
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <StatusDot :status="appItem.monitoring" />
                    <span>monitoring: {{ appItem.monitoring }}</span>
                    <span class="ml-auto">{{ relativeTime(appItem.last_report_at) }}</span>
                  </div>
                </div>
              </button>
            </div>
          </div>
        </section>
        <p v-if="!filteredTeams.length" class="text-sm text-gray-500 dark:text-gray-400">No clients or apps match your search.</p>
      </div>

      <!-- Apps (flat) view -->
      <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="appItem in flatApps"
          :key="appItem.app_id"
          type="button"
          class="text-left"
          @click="openApp(appItem.app_id)"
        >
          <div class="h-full rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 flex items-start justify-between gap-2">
              <h3 class="truncate font-semibold">{{ appItem.name }}</h3>
              <span v-if="appItem.alerts > 0" class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium" :class="BADGE.yellow">⚠ {{ appItem.alerts }}</span>
            </div>
            <p class="mb-3 truncate text-xs text-gray-400 dark:text-gray-500" :title="appItem.db_connection">{{ appItem.db_connection }}</p>
            <div class="mb-3 flex flex-wrap gap-1.5">
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="errorRateBadge(appItem.error_rate)">{{ Number(appItem.error_rate ?? 0).toFixed(2) }}% err</span>
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.count_5xx > 0 ? BADGE.red : BADGE.gray">{{ appItem.count_5xx ?? 0 }} 5xx</span>
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.exceptions > 0 ? BADGE.yellow : BADGE.gray">{{ appItem.exceptions ?? 0 }} exc</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
              <StatusDot :status="appItem.monitoring" />
              <span>monitoring: {{ appItem.monitoring }}</span>
              <span class="ml-auto">{{ relativeTime(appItem.last_report_at) }}</span>
            </div>
          </div>
        </button>
        <p v-if="!flatApps.length" class="text-sm text-gray-500 dark:text-gray-400">No apps match your search.</p>
      </div>
    </div>
  </div>
</template>
