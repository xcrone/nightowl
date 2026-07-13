<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { absoluteTime, formatDuration, formatValue } from '../../utils/format'
import { summarize, singularLabels, resources, methodColor, statusCodeColor } from '../../resourceConfig'
import StatPanel from '../../components/StatPanel.vue'
import JsonViewer from '../../components/JsonViewer.vue'

// Telescope-style single-record detail: a generic key/value "Details" panel
// built by iterating whatever the api returns (no per-resource field list —
// see resourceConfig.js's own docblock, this page is what it refers to),
// collapsible JSON/text panels for the known blob columns, and a "Related"
// tab bar of everything else that happened in the same request/job/command/
// scheduled-task (App\Actions\Telemetry\{ShowTelemetryResource,
// RelatedTelemetryResource}). Reachable from AggregateDetailPage's
// Occurrences table and from a "Related" tab row on this same page.
// GET /api/apps/{appId}/{resource}/{id} + /related
const route = useRoute()
const router = useRouter()
const app = useAppStore()

const appId = computed(() => route.params.appId)
const resource = computed(() => route.params.resource)
const id = computed(() => route.params.id)

// Fields rendered in their own JSON/text panel below, so the generic
// "Details" table skips them — otherwise every request/job/etc. would show
// its (often huge) payload/headers/context twice.
const BLOB_FIELDS = ['payload', 'headers', 'context', 'exception_preview', 'sql_query', 'trace', 'message', 'extra']

// Per-blob-field label/subtitle overrides, sourced from how the sensor
// actually populates each column (agent/vendor/laravel/nightwatch's
// RequestSensor) — plain humanize() would leave "Headers"/"Payload"
// ambiguous about which side of the request/response they're from.
const BLOB_FIELD_META = {
  // Built from the incoming request's headers only — the sensor never
  // captures response headers for any resource type.
  headers: { label: 'Request Headers' },
  // Only non-empty on a 500 response (and only when payload capture is
  // enabled) — the sensor deliberately skips storing request bodies on
  // ordinary responses, so an empty Payload panel here is expected, not
  // a bug.
  payload: { label: 'Payload', subtitle: 'Request body — only captured on 500 responses, when payload capture is enabled' },
  // Laravel's Context facade (structured-logging context), not route
  // params/query context.
  context: { label: 'Context', subtitle: "Laravel's Context facade data" },
}
// Internal/plumbing columns never worth showing a user. `timestamp` is a raw
// unix-epoch-float duplicate of `created_at` on every telemetry table (not an
// ISO string, so absoluteTime() can't parse it anyway) — created_at already
// shows the same moment correctly formatted.
const SKIP_FIELDS = ['id', 'app_id', 'group_hash', 'v', 'timestamp']

const state = reactive({
  loading: false,
  notFound: false,
  record: null,
  origin: null,
  children: {},
  childrenFilter: null,
  userEmail: '',
})

const activeTab = ref('')
// Lazy per-tab cache, keyed by child resource key, so revisiting a tab
// doesn't refetch.
const relatedCache = reactive({})
const copiedField = ref('')

// Latest-wins guard: load() fires from the watcher (route params/period), so
// a slow earlier response must not overwrite a newer one. Each call captures
// its sequence number and only applies if it's still the latest.
let requestSeq = 0

function humanize(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function formatDetailValue(key, value) {
  if (key === 'created_at') {
    return absoluteTime(value, { timezone: app.timezone, format: app.timeFormat })
  }
  if (key === 'duration') return formatDuration(value)
  return value
}

// Whether a string value is itself JSON-encoded object/array — such columns
// (e.g. a raw `route_methods` array) don't belong in a flat key/value table.
function parsesToObject(value) {
  if (typeof value !== 'string') return false
  try {
    const parsed = JSON.parse(value)
    return parsed !== null && typeof parsed === 'object'
  } catch {
    return false
  }
}

const title = computed(() => {
  const summary = summarize(resource.value, state.record)
  if (summary) return summary
  return `${singularLabels[resource.value] ?? resource.value}${id.value ? ` #${id.value}` : ''}`
})

const detailFields = computed(() => {
  if (!state.record) return []
  return Object.entries(state.record)
    .filter(([key, value]) => {
      if (SKIP_FIELDS.includes(key) || BLOB_FIELDS.includes(key)) return false
      if (value === null || value === undefined || value === '') return false
      if (typeof value === 'object') return false
      if (parsesToObject(value)) return false
      return true
    })
    .map(([key, value]) => ({ key, label: humanize(key), value: formatDetailValue(key, value) }))
})

const blobPanels = computed(() => {
  if (!state.record) return []
  return BLOB_FIELDS.filter((field) => {
    const value = state.record[field]
    return value !== null && value !== undefined && value !== ''
  }).map((field) => {
    const raw = state.record[field]
    let parsed = null
    try {
      const attempt = JSON.parse(raw)
      if (attempt !== null && typeof attempt === 'object') parsed = attempt
    } catch {
      parsed = null
    }
    const meta = BLOB_FIELD_META[field]
    return { key: field, label: meta?.label ?? humanize(field), subtitle: meta?.subtitle ?? '', raw, parsed }
  })
})

const originLink = computed(() => {
  if (!state.origin?.record?.id) return null
  return `/dashboard/${appId.value}/${state.origin.resource}/record/${state.origin.record.id}`
})

const relatedTabs = computed(() => Object.entries(state.children).map(([res, count]) => ({ resource: res, count })))

function resourceLabel(key) {
  return resources[key]?.label ?? key
}

// One unified tab strip covers everything except Details/Authenticated User:
// the JSON/text blob fields (Headers, Payload, Context, ...) plus every
// correlated resource (Queries, Cache Events, ...) — a blob tab's content is
// already in `blobPanels`, a related tab's rows are fetched lazily on select.
const allTabs = computed(() => [
  ...blobPanels.value.map((p) => ({ key: p.key, kind: 'blob', label: p.label })),
  ...relatedTabs.value.map((t) => ({ key: t.resource, kind: 'related', label: `${resourceLabel(t.resource)} (${t.count})` })),
])
const activePanel = computed(() => blobPanels.value.find((p) => p.key === activeTab.value) ?? null)

const childColumns = computed(() => resources[activeTab.value]?.columns ?? [])
const activeTabState = computed(() => relatedCache[activeTab.value] ?? { loading: false, rows: [] })

async function selectTab(key) {
  activeTab.value = key
  if (BLOB_FIELDS.includes(key)) return
  if (relatedCache[key]) return
  const seq = requestSeq
  relatedCache[key] = { loading: true, rows: [] }
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/${key}`, {
      params: {
        execution_source: state.childrenFilter?.execution_source,
        execution_id: state.childrenFilter?.execution_id,
        period: app.period,
      },
    })
    if (seq === requestSeq) relatedCache[key] = { loading: false, rows: data?.data ?? [] }
  } catch {
    if (seq === requestSeq) relatedCache[key] = { loading: false, rows: [] }
  }
}

function recordLink(res, row) {
  return row?.id ? `/dashboard/${appId.value}/${res}/record/${row.id}` : null
}

async function loadUserEmail(userId, seq) {
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/users/${userId}`, { params: { period: app.period } })
    if (seq === requestSeq) state.userEmail = data?.user?.email ?? ''
  } catch {
    if (seq === requestSeq) state.userEmail = ''
  }
}

async function load() {
  if (!appId.value || !resource.value || !id.value) return
  const seq = ++requestSeq
  state.loading = true
  state.notFound = false
  state.userEmail = ''
  activeTab.value = ''
  Object.keys(relatedCache).forEach((key) => delete relatedCache[key])
  try {
    const [{ data: record }, { data: related }] = await Promise.all([
      api.get(`/api/apps/${appId.value}/${resource.value}/${id.value}`),
      api.get(`/api/apps/${appId.value}/${resource.value}/${id.value}/related`),
    ])
    if (seq !== requestSeq) return
    state.record = record ?? null
    state.origin = related?.origin ?? null
    state.children = related?.children ?? {}
    state.childrenFilter = related?.children_filter ?? null
    if (record?.user_id) loadUserEmail(record.user_id, seq)
    const defaultTab = blobPanels.value[0]?.key ?? Object.keys(state.children)[0]
    if (defaultTab) selectTab(defaultTab)
  } catch (e) {
    if (seq !== requestSeq) return
    state.record = null
    state.origin = null
    state.children = {}
    state.childrenFilter = null
    if (e?.response?.status === 404) state.notFound = true
  } finally {
    if (seq === requestSeq) state.loading = false
  }
}

async function copyField(key, raw) {
  try {
    await navigator.clipboard.writeText(String(raw))
    copiedField.value = key
    setTimeout(() => { if (copiedField.value === key) copiedField.value = '' }, 2000)
  } catch {
    copiedField.value = ''
  }
}

watch([appId, resource, id, () => app.period], load, { immediate: true })
</script>

<template>
  <div v-if="state.notFound" class="flex flex-col items-center justify-center gap-3 py-24 text-center">
    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Record not found</p>
    <p class="text-sm text-gray-500 dark:text-gray-400">It may have been deleted, or the link is incorrect.</p>
    <RouterLink
      :to="`/dashboard/${appId}/${resource}`"
      class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
    >Back to {{ resourceLabel(resource) }}</RouterLink>
  </div>

  <div v-else class="space-y-4">
    <!-- Title bar: summary + method/status badges + origin link -->
    <div class="min-w-0">
      <h1 class="flex flex-wrap items-center gap-2 break-words text-xl font-semibold text-gray-900 dark:text-gray-100">
        <span
          v-if="state.record?.method"
          class="rounded px-2 py-0.5 text-sm font-medium"
          :class="methodColor(state.record.method)"
        >{{ state.record.method }}</span>
        <span
          v-if="state.record?.status_code !== null && state.record?.status_code !== undefined"
          class="rounded px-2 py-0.5 text-sm font-medium"
          :class="statusCodeColor(Number(state.record.status_code))"
        >{{ state.record.status_code }}</span>
        <span class="break-all font-mono">{{ title }}</span>
      </h1>
      <RouterLink
        v-if="originLink"
        :to="originLink"
        class="mt-1 inline-block text-xs text-primary-600 hover:underline dark:text-primary-400"
      >Part of {{ singularLabels[state.origin.resource] ?? state.origin.resource }}</RouterLink>
    </div>

    <!-- Authenticated User -->
    <StatPanel v-if="state.record?.user_id" title="Authenticated User">
      <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
        <div class="min-w-0">
          <dt class="text-xs text-gray-500 dark:text-gray-400">ID</dt>
          <dd class="text-sm font-medium">
            <RouterLink
              :to="`/dashboard/${appId}/users/${state.record.user_id}`"
              class="text-primary-600 hover:underline dark:text-primary-400"
            >{{ state.record.user_id }}</RouterLink>
          </dd>
        </div>
        <div v-if="state.userEmail" class="min-w-0">
          <dt class="text-xs text-gray-500 dark:text-gray-400">Email Address</dt>
          <dd class="break-all text-sm font-medium text-gray-900 dark:text-gray-100">{{ state.userEmail }}</dd>
        </div>
      </div>
    </StatPanel>

    <!-- Details: generic key/value grid over the raw record -->
    <StatPanel title="Details">
      <p v-if="!detailFields.length" class="text-sm text-gray-400 dark:text-gray-500">No fields.</p>
      <div v-else class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
        <div v-for="field in detailFields" :key="field.key" class="min-w-0">
          <dt class="text-xs text-gray-500 dark:text-gray-400">{{ field.label }}</dt>
          <dd class="break-all text-sm font-medium text-gray-900 dark:text-gray-100">{{ field.value }}</dd>
        </div>
      </div>
    </StatPanel>

    <!-- Everything else — Headers/Payload/Context blob fields and every
         correlated resource — behind one tab strip, Telescope's
         Payload/Headers/Queries/Cache tabs collapsed into a single row. -->
    <StatPanel v-if="allTabs.length">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="inline-flex flex-wrap gap-1">
          <button
            v-for="tab in allTabs"
            :key="tab.key"
            type="button"
            class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
            :class="
              activeTab === tab.key
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400'
                : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
            "
            @click="selectTab(tab.key)"
          >{{ tab.label }}</button>
        </div>
        <button
          v-if="activePanel"
          type="button"
          class="shrink-0 text-xs text-primary-600 dark:text-primary-400"
          @click="copyField(activePanel.key, activePanel.raw)"
        >{{ copiedField === activePanel.key ? 'Copied!' : 'Copy' }}</button>
      </div>

      <p v-if="activePanel?.subtitle" class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ activePanel.subtitle }}</p>

      <!-- Blob tab: JSON tree, or a <pre> fallback when it isn't JSON -->
      <template v-if="activePanel">
        <div v-if="activePanel.parsed" class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs dark:bg-gray-800">
          <JsonViewer :data="activePanel.parsed" />
        </div>
        <pre v-else class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ activePanel.raw }}</pre>
      </template>

      <!-- Related tab: that resource's correlated rows -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead>
            <tr>
              <th
                v-for="col in childColumns"
                :key="col.key"
                class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400"
              >{{ col.label }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-if="activeTabState.loading">
              <td :colspan="childColumns.length" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!activeTabState.rows.length">
              <td :colspan="childColumns.length" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">No records found.</td>
            </tr>
            <tr
              v-for="row in activeTabState.rows"
              v-else
              :key="row.id"
              class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
              @click="recordLink(activeTab, row) && router.push(recordLink(activeTab, row))"
            >
              <td v-for="col in childColumns" :key="col.key" class="max-w-xs truncate px-2 py-1.5 text-gray-900 dark:text-gray-100">
                <span
                  v-if="col.badge"
                  class="rounded px-1.5 py-0.5 text-xs font-medium"
                  :class="col.badge(row[col.key])"
                >{{ formatValue(row[col.key], col.format) }}</span>
                <span v-else>{{ formatValue(row[col.key], col.format) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </StatPanel>
  </div>
</template>

