<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { formatDuration } from '../../utils/format'
import { methodColor } from '../../resourceConfig'
import StatPanel from '../../components/StatPanel.vue'
import BarChartPanel from '../../components/BarChartPanel.vue'
import JsonViewer from '../../components/JsonViewer.vue'

// Single-user drill-down: raw record (collapsible JSON), request status mix,
// and this user's top/slowest routes + top queued jobs. Period-reactive.
// GET /api/apps/{appId}/users/{userId}?period=
const route = useRoute()
const app = useAppStore()

const appId = computed(() => route.params.appId)
const userId = computed(() => route.params.userId)

const state = reactive({
  loading: false,
  user: {},
  requests: {},
  topRoutes: [],
  slowestRoutes: [],
  topJobs: [],
})

const userOpen = ref(true)

const COLOR = { green: '#10b981', amber: '#f59e0b', red: '#ef4444' }

async function load() {
  if (!appId.value || !userId.value) return
  state.loading = true
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/users/${userId.value}`, {
      params: { period: app.period },
    })
    state.user = data.user ?? {}
    state.requests = data.requests ?? {}
    state.topRoutes = data.top_routes ?? []
    state.slowestRoutes = data.slowest_routes ?? []
    state.topJobs = data.top_jobs ?? []
  } catch {
    state.user = {}
    state.requests = {}
    state.topRoutes = []
    state.slowestRoutes = []
    state.topJobs = []
  } finally {
    state.loading = false
  }
}

const requestsLabels = computed(() => ['1/2/3XX', '4XX', '5XX'])
const requestsDatasets = computed(() => [
  {
    label: 'Requests',
    data: [state.requests.c2xx ?? 0, state.requests.c4xx ?? 0, state.requests.c5xx ?? 0],
    backgroundColor: [COLOR.green, COLOR.amber, COLOR.red],
  },
])

watch([userId, () => app.period], load, { immediate: true })
</script>

<template>
  <div class="space-y-4">
    <!-- User (collapsible raw JSON) -->
    <StatPanel>
      <template #actions>
        <button type="button" class="text-primary-600 dark:text-primary-400" @click="userOpen = !userOpen">
          {{ userOpen ? '▾' : '▸' }}
        </button>
      </template>
      <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
        {{ state.user.name ?? state.user.email ?? userId }}
      </h3>
      <div v-if="userOpen" class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs dark:bg-gray-800">
        <JsonViewer :data="state.user" />
      </div>
    </StatPanel>

    <!-- Requests stat -->
    <BarChartPanel title="Requests" :labels="requestsLabels" :datasets="requestsDatasets" height-class="h-40">
      <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ state.requests.total ?? 0 }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">1/2/3XX {{ state.requests.c2xx ?? 0 }}</span>
        <span class="text-xs text-amber-600 dark:text-amber-400">4XX {{ state.requests.c4xx ?? 0 }}</span>
        <span class="text-xs text-red-600 dark:text-red-400">5XX {{ state.requests.c5xx ?? 0 }}</span>
      </div>
    </BarChartPanel>

    <!-- Three side-by-side panels -->
    <div class="grid gap-4 lg:grid-cols-3">
      <StatPanel title="Top Routes">
        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          <li v-if="!state.topRoutes.length" class="py-2 text-gray-400 dark:text-gray-500">No data.</li>
          <li v-for="(r, i) in state.topRoutes" :key="i" class="flex items-center justify-between gap-2 py-1.5">
            <span class="flex min-w-0 items-center gap-2">
              <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="methodColor(r.method)">{{ r.method }}</span>
              <span class="truncate text-gray-700 dark:text-gray-300">{{ r.route_path }}</span>
            </span>
            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ r.count }}</span>
          </li>
        </ul>
      </StatPanel>

      <StatPanel title="Slowest Routes">
        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          <li v-if="!state.slowestRoutes.length" class="py-2 text-gray-400 dark:text-gray-500">No data.</li>
          <li v-for="(r, i) in state.slowestRoutes" :key="i" class="flex items-center justify-between gap-2 py-1.5">
            <span class="flex min-w-0 items-center gap-2">
              <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="methodColor(r.method)">{{ r.method }}</span>
              <span class="truncate text-gray-700 dark:text-gray-300">{{ r.route_path }}</span>
            </span>
            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ formatDuration(r.p95) }}</span>
          </li>
        </ul>
      </StatPanel>

      <StatPanel title="Top Queued Jobs">
        <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          <li v-if="!state.topJobs.length" class="py-2 text-gray-400 dark:text-gray-500">No data.</li>
          <li v-for="(j, i) in state.topJobs" :key="i" class="flex items-center justify-between gap-2 py-1.5">
            <span class="truncate text-gray-700 dark:text-gray-300">{{ j.job_class }}</span>
            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ j.count }}</span>
          </li>
        </ul>
      </StatPanel>
    </div>
  </div>
</template>
