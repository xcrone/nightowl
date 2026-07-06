<script setup>
import { reactive, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { absoluteTime, formatPercent } from '../../utils/format'
import StatusDot from '../../components/StatusDot.vue'
import LineChartPanel from '../../components/LineChartPanel.vue'
import { BADGE } from '../../resourceConfig'

// Monitors the NightOwl ingest pipeline itself (the ReactPHP daemon(s)):
// status banner + health score, per-instance table, and four time-series
// history charts. GET /api/apps/{appId}/health?period=, period-reactive.
const route = useRoute()
const app = useAppStore()

const appId = computed(() => route.params.appId)

const COLOR = { green: '#10b981', amber: '#f59e0b', red: '#ef4444', blue: '#3b82f6' }

const state = reactive({
  loading: false,
  status: '',
  score: 0,
  lastReportAt: null,
  instances: [],
  history: { throughput: [], buffer: [], pg_latency: [], score: [] },
})

async function load() {
  if (!appId.value) return
  state.loading = true
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/health`, { params: { period: app.period } })
    state.status = data.status ?? ''
    state.score = data.score ?? 0
    state.lastReportAt = data.last_report_at ?? null
    state.instances = data.instances ?? []
    state.history = {
      throughput: data.history?.throughput ?? [],
      buffer: data.history?.buffer ?? [],
      pg_latency: data.history?.pg_latency ?? [],
      score: data.history?.score ?? [],
    }
  } catch {
    state.status = ''
    state.score = 0
    state.instances = []
    state.history = { throughput: [], buffer: [], pg_latency: [], score: [] }
  } finally {
    state.loading = false
  }
}

const bannerClass = computed(() => ({
  healthy: 'border-green-200 bg-green-50 dark:border-green-500/30 dark:bg-green-500/10',
  degraded: 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10',
  unhealthy: 'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10',
}[state.status] ?? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'))

function healthBadge(health) {
  return {
    healthy: BADGE.green,
    degraded: BADGE.amber,
    unhealthy: BADGE.red,
  }[health] ?? BADGE.gray
}

function whenAbs(iso) {
  return absoluteTime(iso, { timezone: app.timezone, format: app.timeFormat })
}
function mb(bytes) {
  if (bytes === null || bytes === undefined) return '—'
  return `${(Number(bytes) / 1_000_000).toFixed(1)} MB`
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
const pick = (series, key) => series.map((pt) => Number(pt[key] ?? 0))

const throughputLabels = computed(() => labelsFor(state.history.throughput))
const throughputSeries = computed(() => [
  { label: 'Ingest', data: pick(state.history.throughput, 'ingest'), color: COLOR.blue },
  { label: 'Drain', data: pick(state.history.throughput, 'drain'), color: COLOR.green },
])

const bufferLabels = computed(() => labelsFor(state.history.buffer))
const bufferSeries = computed(() => [
  { label: 'Pending Rows', data: pick(state.history.buffer, 'pending_rows'), color: COLOR.amber },
])

const pgLabels = computed(() => labelsFor(state.history.pg_latency))
const pgSeries = computed(() => [
  { label: 'PG Latency (ms)', data: pick(state.history.pg_latency, 'ms'), color: COLOR.red },
])

const scoreLabels = computed(() => labelsFor(state.history.score))
const scoreSeries = computed(() => [
  { label: 'Health Score', data: pick(state.history.score, 'score'), color: COLOR.green },
])

watch(() => app.period, load, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <!-- Status banner -->
    <div class="flex items-center justify-between rounded-lg border p-5" :class="bannerClass">
      <div>
        <div class="flex items-center gap-2">
          <StatusDot :status="state.status" />
          <span class="text-lg font-semibold uppercase text-gray-900 dark:text-gray-100">{{ state.status || '—' }}</span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Last report: {{ whenAbs(state.lastReportAt) }}</p>
      </div>
      <div class="text-right">
        <div class="text-4xl font-bold text-gray-900 dark:text-gray-100">{{ state.score }}</div>
        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Health Score</p>
      </div>
    </div>

    <!-- Instances -->
    <section>
      <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Instances ({{ state.instances.length }})</h2>
      <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Instance</th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Health</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Ingest/s</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Drain/s</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">PG Latency</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Write Queue</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">CPU</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Memory</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Reject</th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Last Seen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
            <tr v-if="!state.instances.length">
              <td colspan="10" class="px-3 py-6 text-center text-gray-400 dark:text-gray-500">No instances reporting.</td>
            </tr>
            <tr v-for="inst in state.instances" :key="inst.name" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">{{ inst.name }}</td>
              <td class="px-3 py-2">
                <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="healthBadge(inst.health)">{{ inst.health }}</span>
              </td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ Number(inst.ingest_per_s ?? 0).toFixed(1) }}</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ Number(inst.drain_per_s ?? 0).toFixed(1) }}</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ inst.pg_latency_ms ?? 0 }} ms</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatPercent(inst.write_queue_pct) }}</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatPercent(inst.cpu_pct) }}</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ mb(inst.memory_bytes) }}</td>
              <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatPercent(inst.reject_pct) }}</td>
              <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ whenAbs(inst.last_seen_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Performance History -->
    <section>
      <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Performance History</h2>
      <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        <LineChartPanel title="Throughput" :labels="throughputLabels" :series="throughputSeries" />
        <LineChartPanel title="Buffer" :labels="bufferLabels" :series="bufferSeries" />
        <LineChartPanel title="Postgres Latency" :labels="pgLabels" :series="pgSeries" />
        <LineChartPanel title="Health Score" :labels="scoreLabels" :series="scoreSeries" />
      </div>
    </section>
  </div>
</template>
