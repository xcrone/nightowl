<script setup>
import { reactive, computed, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import BarChartPanel from '../../components/BarChartPanel.vue'
import LineChartPanel from '../../components/LineChartPanel.vue'
import StatPanel from '../../components/StatPanel.vue'
import { formatDuration } from '../../utils/format'

// The per-app landing dashboard: headline summary (GET .../dashboard) plus the
// four timeseries charts (requests, duration, exceptions, jobs). All four panels
// re-fetch when the app-store period changes.
const route = useRoute()
const app = useAppStore()

const COLOR = { green: '#10b981', amber: '#f59e0b', red: '#ef4444', blue: '#3b82f6', gray: '#9ca3af' }

const appId = computed(() => route.params.appId)

const state = reactive({
  loading: false,
  summary: {},
  ts: { requests: [], duration: [], exceptions: [], jobs: [] },
})

async function load() {
  if (!appId.value) return
  state.loading = true
  const params = { period: app.period }
  try {
    const [dash, reqs, dur, exc, jobs] = await Promise.all([
      api.get(`/api/apps/${appId.value}/dashboard`, { params }),
      api.get(`/api/apps/${appId.value}/timeseries/requests`, { params }),
      api.get(`/api/apps/${appId.value}/timeseries/duration`, { params }),
      api.get(`/api/apps/${appId.value}/timeseries/exceptions`, { params }),
      api.get(`/api/apps/${appId.value}/timeseries/jobs`, { params }),
    ])
    state.summary = dash.data ?? {}
    state.ts.requests = reqs.data?.series ?? []
    state.ts.duration = dur.data?.series ?? []
    state.ts.exceptions = exc.data?.series ?? []
    state.ts.jobs = jobs.data?.series ?? []
  } catch {
    state.summary = {}
    state.ts = { requests: [], duration: [], exceptions: [], jobs: [] }
  } finally {
    state.loading = false
  }
}

function labelsFor(series) {
  return series.map((pt) => {
    const d = new Date(pt.t)
    if (Number.isNaN(d.getTime())) return ''
    return d.toLocaleTimeString(undefined, {
      hour: '2-digit',
      minute: '2-digit',
      hour12: app.timeFormat === '12h',
      timeZone: app.timezone === 'UTC' ? 'UTC' : undefined,
    })
  })
}
const pick = (series, key) => series.map((pt) => Number(pt.values?.[key] ?? 0))

// --- panel data ---
const requests = computed(() => state.summary.requests ?? {})
const duration = computed(() => state.summary.duration ?? {})
const exceptions = computed(() => state.summary.exceptions ?? {})
const jobs = computed(() => state.summary.jobs ?? {})
const jobDuration = computed(() => state.summary.job_duration ?? {})
const users = computed(() => state.summary.users ?? {})

const requestsLabels = computed(() => labelsFor(state.ts.requests))
const requestsDatasets = computed(() => [
  { label: '1/2/3XX', data: pick(state.ts.requests, 'c2xx'), backgroundColor: COLOR.green },
  { label: '4XX', data: pick(state.ts.requests, 'c4xx'), backgroundColor: COLOR.amber },
  { label: '5XX', data: pick(state.ts.requests, 'c5xx'), backgroundColor: COLOR.red },
])

const durationLabels = computed(() => labelsFor(state.ts.duration))
const durationSeries = computed(() => [
  { label: 'Avg', data: pick(state.ts.duration, 'avg'), color: COLOR.amber },
  { label: 'P95', data: pick(state.ts.duration, 'p95'), color: COLOR.blue },
])

const exceptionsLabels = computed(() => labelsFor(state.ts.exceptions))
const exceptionsDatasets = computed(() => [
  { label: 'Handled', data: pick(state.ts.exceptions, 'handled'), backgroundColor: COLOR.amber },
  { label: 'Unhandled', data: pick(state.ts.exceptions, 'unhandled'), backgroundColor: COLOR.red },
])

const jobsLabels = computed(() => labelsFor(state.ts.jobs))
const jobsDatasets = computed(() => [
  { label: 'Processed', data: pick(state.ts.jobs, 'processed'), backgroundColor: COLOR.green },
  { label: 'Released', data: pick(state.ts.jobs, 'released'), backgroundColor: COLOR.amber },
  { label: 'Failed', data: pick(state.ts.jobs, 'failed'), backgroundColor: COLOR.red },
])

const jobDurationSeries = computed(() => [
  { label: 'Avg', data: pick(state.ts.jobs, 'avg'), color: COLOR.amber },
  { label: 'P95', data: pick(state.ts.jobs, 'p95'), color: COLOR.blue },
])

function userLink(userId) {
  return `/dashboard/${appId.value}/users/${userId}`
}

watch(() => app.period, load, { immediate: true })
</script>

<template>
  <div class="space-y-8">
    <!-- Activity -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Activity</h2>
      <div class="grid gap-4 lg:grid-cols-2">
        <BarChartPanel title="Requests" :labels="requestsLabels" :datasets="requestsDatasets" stacked>
          <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ requests.total ?? 0 }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">1/2/3XX {{ requests.c2xx ?? 0 }}</span>
            <span class="text-xs text-amber-600 dark:text-amber-400">4XX {{ requests.c4xx ?? 0 }}</span>
            <span class="text-xs text-red-600 dark:text-red-400">5XX {{ requests.c5xx ?? 0 }}</span>
          </div>
        </BarChartPanel>

        <LineChartPanel title="Duration" :labels="durationLabels" :series="durationSeries">
          <template #footer>
            {{ formatDuration(duration.min) }} – {{ formatDuration(duration.max) }} ·
            Avg {{ formatDuration(duration.avg) }} · P95 {{ formatDuration(duration.p95) }}
          </template>
        </LineChartPanel>
      </div>
    </section>

    <!-- Application -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Application</h2>
      <div class="grid gap-4 lg:grid-cols-3">
        <BarChartPanel title="Exceptions" :labels="exceptionsLabels" :datasets="exceptionsDatasets" stacked>
          <template #actions>
            <RouterLink :to="`/dashboard/${appId}/exceptions`" class="text-primary-600 hover:underline dark:text-primary-400">View</RouterLink>
          </template>
          <div class="mt-3">
            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ exceptions.count ?? 0 }}</span>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ exceptions.users_impacted ?? 0 }} users impacted</p>
            <div class="mt-1 flex gap-3 text-xs text-gray-500 dark:text-gray-400">
              <span>Handled {{ exceptions.handled ?? 0 }}</span>
              <span class="text-red-600 dark:text-red-400">Unhandled {{ exceptions.unhandled ?? 0 }}</span>
            </div>
          </div>
        </BarChartPanel>

        <BarChartPanel title="Jobs" :labels="jobsLabels" :datasets="jobsDatasets" stacked>
          <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ jobs.total ?? 0 }}</span>
            <span class="text-xs text-red-600 dark:text-red-400">Failed {{ jobs.failed ?? 0 }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Processed {{ jobs.processed ?? 0 }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Released {{ jobs.released ?? 0 }}</span>
          </div>
        </BarChartPanel>

        <LineChartPanel title="Job Duration" :labels="jobsLabels" :series="jobDurationSeries">
          <template #footer>
            {{ formatDuration(jobDuration.min) }} – {{ formatDuration(jobDuration.max) }} ·
            Avg {{ formatDuration(jobDuration.avg) }} · P95 {{ formatDuration(jobDuration.p95) }}
          </template>
        </LineChartPanel>
      </div>
    </section>

    <!-- Users -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Users</h2>
      <div class="grid gap-4 lg:grid-cols-2">
        <StatPanel title="Authenticated Users">
          <div class="grid grid-cols-3 gap-3">
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400">Authenticated</dt>
              <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ users.authenticated_total ?? 0 }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400">Auth requests</dt>
              <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ users.requests_split?.authenticated ?? 0 }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400">Guest requests</dt>
              <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ users.requests_split?.guest ?? 0 }}</dd>
            </div>
          </div>
        </StatPanel>

        <StatPanel title="Most active">
          <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <li v-if="!(users.most_active?.length)" class="py-2 text-gray-400 dark:text-gray-500">No data.</li>
            <li v-for="u in users.most_active" :key="u.user_id" class="flex items-center justify-between py-1.5">
              <RouterLink :to="userLink(u.user_id)" class="truncate text-primary-600 hover:underline dark:text-primary-400">
                {{ u.email ?? u.user_id }}
              </RouterLink>
              <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ u.count }}</span>
            </li>
          </ul>
        </StatPanel>

        <StatPanel title="Impacted by exceptions">
          <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <li v-if="!(users.impacted_by_exceptions?.length)" class="py-2 text-gray-400 dark:text-gray-500">No data.</li>
            <li v-for="u in users.impacted_by_exceptions" :key="u.user_id" class="flex items-center justify-between py-1.5">
              <RouterLink :to="userLink(u.user_id)" class="truncate text-primary-600 hover:underline dark:text-primary-400">
                {{ u.email ?? u.user_id }}
              </RouterLink>
              <span class="shrink-0 text-red-600 dark:text-red-400">{{ u.count }}</span>
            </li>
          </ul>
        </StatPanel>
      </div>
    </section>
  </div>
</template>
