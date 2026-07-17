<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { useLivePoll } from '../../composables/useLivePoll'
import { useRowHighlight } from '../../composables/useRowHighlight'
import { aggregateConfig } from '../../aggregateConfig'
import { absoluteTime, formatDuration, formatDurationColor } from '../../utils/format'
import { BADGE, methodColor, statusCodeColor } from '../../resourceConfig'
import StatPanel from '../../components/StatPanel.vue'
import BarChartPanel from '../../components/BarChartPanel.vue'
import JsonViewer from '../../components/JsonViewer.vue'

// Per-item drill-down behind one Activity-aggregate list row (docs/pages/
// aggregate-detail.md). One page serves all 8 clickable resources; the
// per-resource occurrence columns, outcome chips, and noun come from the maps
// below, while the two summary panels reuse aggregateConfig's panel builders so
// they stay visually identical to the parent list page.
// GET /api/apps/{appId}/aggregate/{resource}/{key}?period&bucket&outcome&…
const route = useRoute()
const router = useRouter()
const app = useAppStore()

const appId = computed(() => route.params.appId)
const resource = computed(() => route.params.resource)
const key = computed(() => route.params.key)
const cfg = computed(() => aggregateConfig[resource.value])

// Raw-occurrence table columns per resource. `keys` lists candidate row fields
// (raw model shapes vary); the first defined wins. `kind` drives cell rendering.
const OCCURRENCE = {
  requests: {
    noun: 'Requests',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Method', keys: ['method'], kind: 'method' },
      { label: 'Details', keys: ['route_path', 'uri', 'path'] },
      { label: 'Status', keys: ['status_code', 'response_status', 'status'], kind: 'status', align: 'right' },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  'outgoing-requests': {
    noun: 'Requests',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Method', keys: ['method'], kind: 'method' },
      { label: 'URL', keys: ['url', 'uri'] },
      { label: 'Status', keys: ['status_code', 'response_status', 'status'], kind: 'status', align: 'right' },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  jobs: {
    noun: 'Attempts',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Connection', keys: ['connection'] },
      { label: 'Queue', keys: ['queue'] },
      { label: 'Attempt', keys: ['attempt', 'attempts'], align: 'right' },
      { label: 'Status', keys: ['status'], kind: 'badge' },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  commands: {
    noun: 'Calls',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Exit Code', keys: ['exit_code'], align: 'right' },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  'scheduled-tasks': {
    noun: 'Runs',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Status', keys: ['status'], kind: 'badge' },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  queries: {
    noun: 'Calls',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Source', keys: ['execution_source', 'source', 'source_label'] },
      { label: 'Location', keys: ['location', 'file'] },
      { label: 'Connection', keys: ['connection'] },
      { label: 'Type', keys: ['connection_type', 'rw', 'type'] },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  mail: {
    noun: 'Mails',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Mailer', keys: ['mailer'] },
      { label: 'Subject', keys: ['subject'] },
      { label: 'To', keys: ['to'] },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
  notifications: {
    noun: 'Notifications',
    columns: [
      { label: 'Date', keys: ['created_at', 'timestamp'], kind: 'date' },
      { label: 'Channel', keys: ['channel', 'channels'] },
      { label: 'Duration', keys: ['duration'], kind: 'duration', align: 'right' },
    ],
  },
}

// Resource-specific outcome breakdown (right-hand chip row), aliased to the
// api's `?outcome=` count buckets. `count(panels)` mirrors aggregateConfig's
// panel builders so the chip badges match the summary panel.
const OUTCOME = {
  requests: (p) => outcomesFrom(p.requests, [['1/2/3XX', 'c2xx'], ['4XX', 'c4xx'], ['5XX', 'c5xx']]),
  'outgoing-requests': (p) => outcomesFrom(p.requests, [['1/2/3XX', 'c2xx'], ['4XX', 'c4xx'], ['5XX', 'c5xx']]),
  jobs: (p) => outcomesFrom(p.attempts ?? p.jobs, [['Processed', 'processed'], ['Released', 'released'], ['Failed', 'failed']]),
  commands: (p) => outcomesFrom(p.calls ?? p.commands, [['Successful', 'successful'], ['Unsuccessful', 'failed']]),
  'scheduled-tasks': (p) => outcomesFrom(p.tasks ?? p.scheduled_tasks, [['Processed', 'processed'], ['Skipped', 'skipped'], ['Failed', 'failed']]),
}

function outcomesFrom(bucket = {}, defs) {
  return defs.map(([label, alias]) => ({ label, alias, count: Number(bucket?.[alias] ?? 0) }))
}

const state = reactive({
  loading: false,
  label: '',
  meta: {},
  panels: {},
  percentiles: {},
  info: null,
  sql: '',
  occurrences: [],
  page: 1,
  lastPage: 1,
  total: 0,
})

// Top-right percentile toggle (display-only, default P95): re-renders the
// duration panel's headline against the chosen percentile.
const percentile = ref('p95')
const PERCENTILES = [
  { value: 'avg', label: 'AVG' },
  { value: 'p50', label: 'P50' },
  { value: 'p95', label: 'P95' },
  { value: 'p99', label: 'P99' },
]

// Filter chips (server-side, independent): duration bucket + outcome bucket.
const bucket = ref('') // '' = View all, else avg|p50|p95|p99
const outcome = ref('') // '' = View all, else a resource outcome alias
const copied = ref(false)

// Latest-wins guard: load() fires from the watcher (route/period/environment)
// and from the bucket/outcome/page actions, so a slow earlier response must not
// overwrite a newer one. Each call captures its sequence number and only applies
// if it's still the latest.
let requestSeq = 0

const occurrenceMeta = computed(() => OCCURRENCE[resource.value] ?? OCCURRENCE.requests)
const outcomeChips = computed(() => (OUTCOME[resource.value] ? OUTCOME[resource.value](state.panels) : []))
const isQueries = computed(() => resource.value === 'queries')

// Reuse the parent list page's volume/count bar panel descriptor.
const barPanel = computed(() => {
  const built = cfg.value?.panels ? cfg.value.panels(state.panels) : []
  return built.find((p) => p.kind === 'bar') ?? null
})

const durationValue = computed(() => formatDuration(state.percentiles?.[percentile.value]))
const durationClass = computed(() => formatDurationColor(state.percentiles?.[percentile.value]))

const { highlightKeys, track } = useRowHighlight('id')

// `silent` skips the loading state so a live tick refreshes the occurrence
// table in place rather than blinking it back to a skeleton — see useLivePoll.
async function load({ silent = false } = {}) {
  if (!cfg.value || !appId.value || !key.value) return
  const seq = ++requestSeq
  if (!silent) state.loading = true
  const params = { period: app.period, page: state.page }
  if (app.environment) params.environment = app.environment
  if (bucket.value) params.bucket = bucket.value
  if (outcome.value) params.outcome = outcome.value
  if (route.query.expression) params.expression = route.query.expression
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/aggregate/${resource.value}/${key.value}`, { params })
    if (seq !== requestSeq) return
    state.label = data.label ?? ''
    state.meta = data.meta ?? {}
    state.panels = data.panels ?? {}
    state.percentiles = data.percentiles ?? {}
    state.info = data.info ?? null
    state.sql = data.sql ?? ''
    const occ = data.occurrences ?? {}
    state.occurrences = occ.data ?? []
    state.page = occ.current_page ?? 1
    state.lastPage = occ.last_page ?? 1
    state.total = occ.total ?? state.occurrences.length
    track(state.occurrences, { highlight: silent })
  } catch {
    if (seq !== requestSeq) return
    state.label = ''
    state.meta = {}
    state.panels = {}
    state.percentiles = {}
    state.info = null
    state.sql = ''
    state.occurrences = []
    state.page = 1
    state.lastPage = 1
    state.total = 0
  } finally {
    if (seq === requestSeq) state.loading = false
  }
}

function setBucket(value) {
  bucket.value = bucket.value === value ? '' : value
  state.page = 1
  load()
}
function setOutcome(value) {
  outcome.value = outcome.value === value ? '' : value
  state.page = 1
  load()
}
function goToPage(page) {
  if (page < 1 || page > state.lastPage) return
  state.page = page
  load()
}

async function copySql() {
  try {
    await navigator.clipboard.writeText(state.sql)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    copied.value = false
  }
}

// Resolve a column's raw value (first defined candidate key).
function cellValue(col, row) {
  for (const k of col.keys) {
    if (row[k] !== undefined && row[k] !== null) return row[k]
  }
  return null
}
// Row-level drill-down: each raw occurrence links to its own single-record
// detail page (ResourceDetailPage), the click-through point required to
// reach that page from anywhere in the aggregate views.
function recordLink(row) {
  return row.id ? `/dashboard/${appId.value}/${resource.value}/record/${row.id}` : null
}
function cellDisplay(col, row) {
  const value = cellValue(col, row)
  if (value === null || value === '') return '—'
  if (col.kind === 'date') return absoluteTime(value, { timezone: app.timezone, format: app.timeFormat })
  if (col.kind === 'duration') return formatDuration(value)
  if (Array.isArray(value)) return value.join(', ')
  return value
}

watch(
  [() => route.fullPath, () => app.period, () => app.environment],
  () => {
    bucket.value = ''
    outcome.value = ''
    state.page = 1
    load()
  },
  { immediate: true },
)

// Occurrences paginate newest-first, so polling anything but page 1 would pull
// rows out from under the reader as fresh records push the list down. Past the
// first page the tick is a no-op until they navigate back.
useLivePoll(() => (state.page === 1 ? load({ silent: true }) : Promise.resolve()))
</script>

<template>
  <div v-if="!cfg" class="text-sm text-gray-500 dark:text-gray-400">Unknown resource.</div>

  <div v-else class="space-y-4">
    <!-- Header: aggregate key label + meta, percentile toggle top-right -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
          <span
            v-if="state.meta.method"
            class="rounded px-2 py-0.5 text-sm font-medium"
            :class="methodColor(state.meta.method)"
          >{{ state.meta.method }}</span>
          <span class="break-all font-mono">{{ state.label || '—' }}</span>
        </h1>
        <p v-if="state.meta.connection || state.meta.rw" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          <span v-if="state.meta.rw" class="uppercase">{{ state.meta.rw }}</span>
          <span v-if="state.meta.connection"> · {{ state.meta.connection }}</span>
        </p>
        <p v-if="state.meta.schedule || state.meta.expression" class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
          {{ state.meta.schedule || state.meta.expression }}
        </p>
      </div>

      <div class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
        <button
          v-for="opt in PERCENTILES"
          :key="opt.value"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            percentile === opt.value
              ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-400'
              : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
          "
          @click="percentile = opt.value"
        >{{ opt.label }}</button>
      </div>
    </div>

    <!-- Summary panels: volume/count bar + duration (percentile-driven) -->
    <div class="grid gap-4 sm:grid-cols-2">
      <BarChartPanel
        v-if="barPanel"
        :title="barPanel.title"
        :labels="barPanel.labels"
        :datasets="barPanel.datasets"
        :stacked="barPanel.stacked"
        height-class="h-36"
      >
        <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
          <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ barPanel.total }}</span>
          <span
            v-for="b in barPanel.breakdown"
            :key="b.label"
            class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
          >
            <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: b.color }" />
            {{ b.label }} {{ b.value }}
          </span>
        </div>
      </BarChartPanel>

      <StatPanel title="Duration">
        <div class="flex items-baseline gap-2">
          <span
            data-test="duration-headline"
            class="text-2xl font-semibold"
            :class="durationClass || 'text-gray-900 dark:text-gray-100'"
          >{{ durationValue }}</span>
          <span class="text-xs uppercase text-gray-400 dark:text-gray-500">{{ percentile }}</span>
        </div>
        <div class="mt-3 grid grid-cols-4 gap-2">
          <div v-for="p in PERCENTILES" :key="p.value">
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ p.label }}</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatDuration(state.percentiles[p.value]) }}</dd>
          </div>
        </div>
      </StatPanel>
    </div>

    <!-- Queries only: Info + SQL panels -->
    <div v-if="isQueries && (state.info || state.sql)" class="grid gap-4 lg:grid-cols-2">
      <StatPanel v-if="state.info" title="Info">
        <div class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs dark:bg-gray-800">
          <JsonViewer :data="state.info" />
        </div>
      </StatPanel>
      <StatPanel v-if="state.sql" title="SQL">
        <template #actions>
          <button type="button" class="text-primary-600 dark:text-primary-400" @click="copySql">
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
        </template>
        <pre class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ state.sql }}</pre>
      </StatPanel>
    </div>

    <!-- Filter chip rows: duration bucket (left) + outcome (right) -->
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
      <div class="inline-flex flex-wrap items-center gap-1">
        <button
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="!bucket ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
          @click="setBucket('')"
        >View all</button>
        <button
          v-for="p in PERCENTILES"
          :key="p.value"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="bucket === p.value ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
          @click="setBucket(p.value)"
        >≥ {{ p.label }} <span class="text-gray-400 dark:text-gray-500">{{ formatDuration(state.percentiles[p.value]) }}</span></button>
      </div>

      <div v-if="outcomeChips.length" class="inline-flex flex-wrap items-center gap-1">
        <button
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="!outcome ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
          @click="setOutcome('')"
        >View all</button>
        <button
          v-for="chip in outcomeChips"
          :key="chip.alias"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="outcome === chip.alias ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
          @click="setOutcome(chip.alias)"
        >{{ chip.label }} <span class="text-gray-400 dark:text-gray-500">{{ chip.count }}</span></button>
      </div>
    </div>

    <!-- Occurrences table -->
    <StatPanel :title="`${state.total} ${occurrenceMeta.noun}`">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead>
            <tr>
              <th
                v-for="col in occurrenceMeta.columns"
                :key="col.label"
                class="px-2 py-1 font-medium text-gray-500 dark:text-gray-400"
                :class="col.align === 'right' ? 'text-right' : 'text-left'"
              >{{ col.label }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-if="state.loading">
              <td :colspan="occurrenceMeta.columns.length" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!state.occurrences.length">
              <td :colspan="occurrenceMeta.columns.length" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">
                No records found. Try adjusting any filters or time range.
              </td>
            </tr>
            <tr
              v-for="(row, i) in state.occurrences"
              v-else
              :key="row.id ?? i"
              class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
              :class="highlightKeys.includes(row.id) ? 'bg-primary-50 dark:bg-primary-600/20' : ''"
              @click="recordLink(row) && router.push(recordLink(row))"
            >
              <td
                v-for="col in occurrenceMeta.columns"
                :key="col.label"
                class="max-w-xs truncate px-2 py-1.5 text-gray-900 dark:text-gray-100"
                :class="[col.align === 'right' ? 'text-right' : 'text-left', col.kind === 'duration' ? formatDurationColor(cellValue(col, row)) : '']"
              >
                <span
                  v-if="col.kind === 'method'"
                  class="rounded px-1.5 py-0.5 text-xs font-medium"
                  :class="methodColor(cellValue(col, row))"
                >{{ cellDisplay(col, row) }}</span>
                <span
                  v-else-if="col.kind === 'status'"
                  class="rounded px-1.5 py-0.5 text-xs font-medium"
                  :class="statusCodeColor(Number(cellValue(col, row)))"
                >{{ cellDisplay(col, row) }}</span>
                <span
                  v-else-if="col.kind === 'badge'"
                  class="rounded px-1.5 py-0.5 text-xs font-medium"
                  :class="BADGE.gray"
                >{{ cellDisplay(col, row) }}</span>
                <span v-else>{{ cellDisplay(col, row) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="state.lastPage > 1" class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span>Page {{ state.page }} of {{ state.lastPage }}</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-600"
            :disabled="state.page <= 1"
            @click="goToPage(state.page - 1)"
          >Previous</button>
          <button
            type="button"
            class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-600"
            :disabled="state.page >= state.lastPage"
            @click="goToPage(state.page + 1)"
          >Next</button>
        </div>
      </div>
    </StatPanel>
  </div>
</template>
