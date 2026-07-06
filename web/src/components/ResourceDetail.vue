<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../services/api'
import { resources, singularLabels, summarize } from '../resourceConfig'
import { formatValue } from '../utils/format'
import JsonViewer from './JsonViewer.vue'

const props = defineProps({
  resource: { type: String, required: true },
  id: { type: [String, Number], required: true },
})

const config = computed(() => resources[props.resource])

const state = reactive({
  record: null,
  loading: false,
  error: null,
  related: null,
})

const originLabel = computed(() => {
  const origin = state.related?.origin
  if (!origin) return null
  const label = singularLabels[origin.resource] ?? origin.resource
  const summary = summarize(origin.resource, origin.record)
  return summary ? `${label} — ${summary}` : label
})

const relatedChildren = computed(() => {
  if (!state.related?.children) return []
  return Object.entries(state.related.children).map(([key, count]) => ({
    key,
    count,
    label: resources[key]?.label ?? key,
  }))
})

function relatedChildQuery() {
  const filter = state.related?.children_filter
  if (!filter) return {}
  return { execution_source: filter.execution_source, execution_id: filter.execution_id }
}

// Long text fields (stack traces, SQL, headers/payload JSON) render as
// collapsible blocks below the main grid instead of needing a per-resource
// "sections" config — same information as the old Filament infolists,
// derived from the data instead of hand-maintained per resource.
const LONG_VALUE_THRESHOLD = 120

const shortFields = computed(() => fieldEntries(false))
const longFields = computed(() =>
  fieldEntries(true).map(([key, value]) => {
    const json = parseJson(value)
    return { key, value, json, raw: json ? JSON.stringify(json, null, 2) : value }
  }),
)

// Per-field UI state, keyed by field name — kept outside the `longFields`
// computed so toggling one field's view doesn't get reset by re-derivation.
const rawView = reactive({})
const copiedKey = ref(null)

function toggleRaw(key) {
  rawView[key] = !rawView[key]
}

async function copyField(field) {
  await navigator.clipboard.writeText(field.raw)
  copiedKey.value = field.key
  setTimeout(() => {
    if (copiedKey.value === field.key) copiedKey.value = null
  }, 1500)
}

function fieldEntries(long) {
  if (!state.record) return []
  return Object.entries(state.record).filter(([key, value]) => {
    if (key === 'id') return false
    const isLong = typeof value === 'string' && value.length > LONG_VALUE_THRESHOLD
    return long ? isLong : !isLong
  })
}

// Telemetry fields like `headers`/`payload`/`context` are stored as JSON
// text columns and come back from the API as raw JSON strings — parse them
// so they can render as a collapsible tree instead of a single unbroken line.
function parseJson(value) {
  const trimmed = value.trim()
  if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) return null

  try {
    const parsed = JSON.parse(trimmed)
    return parsed !== null && typeof parsed === 'object' ? parsed : null
  } catch {
    return null
  }
}

async function load() {
  state.loading = true
  state.error = null
  state.related = null

  try {
    const { data } = await api.get(`/api/${props.resource}/${props.id}`)
    state.record = data
  } catch (e) {
    state.error = e.response?.data?.message ?? 'Failed to load record.'
    return
  } finally {
    state.loading = false
  }

  // Best-effort: the "Related" panel is a nice-to-have alongside the
  // record itself, so a failure here shouldn't surface as a page error.
  try {
    const { data } = await api.get(`/api/${props.resource}/${props.id}/related`)
    state.related = data
  } catch {
    state.related = null
  }
}

watch(() => [props.resource, props.id], load, { immediate: true })

defineExpose({ state })
</script>

<template>
  <div>
    <h1 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ config.label }} #{{ id }}</h1>

    <div v-if="state.loading" class="text-gray-400 dark:text-gray-500">Loading…</div>
    <div
      v-else-if="state.error"
      class="rounded border border-red-300 bg-red-50 p-3 text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400"
    >
      {{ state.error }}
    </div>

    <div v-else-if="state.record">
      <slot name="before" :record="state.record" />

      <dl class="grid grid-cols-1 gap-x-6 gap-y-3 rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
        <div v-for="[key, value] in shortFields" :key="key">
          <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ key }}</dt>
          <dd class="text-sm text-gray-900 dark:text-gray-100">{{ formatValue(value) }}</dd>
        </div>
      </dl>

      <div
        v-if="originLabel || relatedChildren.length"
        class="mt-3 rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
      >
        <h2 class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Related</h2>

        <RouterLink
          v-if="state.related.origin"
          :to="`/${state.related.origin.resource}/${state.related.origin.record.id}`"
          class="mt-2 block truncate text-sm text-primary-600 hover:underline dark:text-primary-400"
        >
          ↳ Part of {{ originLabel }}
        </RouterLink>

        <div v-if="relatedChildren.length" class="mt-2 flex flex-wrap gap-2">
          <RouterLink
            v-for="child in relatedChildren"
            :key="child.key"
            :to="{ path: `/${child.key}`, query: relatedChildQuery() }"
            class="rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:border-primary-500 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:text-primary-400"
          >
            {{ child.count }} {{ child.label }}
          </RouterLink>
        </div>
      </div>

      <details v-for="field in longFields" :key="field.key" class="mt-3 rounded border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <summary class="flex items-center justify-between gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          <span class="cursor-pointer">{{ field.key }}</span>
          <span class="flex items-center gap-3 text-xs font-normal">
            <button
              v-if="field.json"
              type="button"
              class="text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
              @click.prevent="toggleRaw(field.key)"
            >
              {{ rawView[field.key] ? 'Tree' : 'Raw' }}
            </button>
            <button
              type="button"
              class="text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
              @click.prevent="copyField(field)"
            >
              {{ copiedKey === field.key ? 'Copied' : 'Copy' }}
            </button>
          </span>
        </summary>
        <blockquote class="m-0 border-t border-gray-100 dark:border-gray-800">
          <pre
            v-if="!field.json || rawView[field.key]"
            class="max-h-96 overflow-x-auto overflow-y-auto whitespace-pre-wrap break-words p-4 text-xs text-gray-900 dark:text-gray-100"
          >{{ field.raw }}</pre>
          <pre v-else class="max-h-96 overflow-x-auto overflow-y-auto p-4 font-mono text-xs text-gray-900 dark:text-gray-100"><JsonViewer :data="field.json" /></pre>
        </blockquote>
      </details>

      <slot name="after" :record="state.record" />
    </div>
  </div>
</template>
