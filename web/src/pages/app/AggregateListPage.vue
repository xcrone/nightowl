<script setup>
import { reactive, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import AggregateTable from '../../components/AggregateTable.vue'
import BarChartPanel from '../../components/BarChartPanel.vue'
import StatPanel from '../../components/StatPanel.vue'
import { aggregateConfig } from '../../aggregateConfig'

// Reusable data host for every aggregated resource list. The dedicated route
// components (RequestsPage, JobsPage, …) render this with a `resource` prop;
// the column set / panels / scope filter all come from aggregateConfig. This
// component owns fetch + sort/search/scope state and is period-reactive.
const props = defineProps({
  resource: { type: String, required: true },
})

const route = useRoute()
const router = useRouter()
const app = useAppStore()

const cfg = aggregateConfig[props.resource]

const state = reactive({
  rows: [],
  panels: {},
  loading: false,
  sort: cfg?.defaultSort ?? '',
  search: '',
  scopeValue: '',
  scopeOptions: [],
  handledFilter: 'all',
})

const appId = computed(() => route.params.appId)

async function load() {
  if (!cfg) return
  state.loading = true
  const params = { period: app.period }
  if (app.environment) params.environment = app.environment
  if (state.sort) params.sort = state.sort
  if (state.search) params.q = state.search
  if (cfg.scope && state.scopeValue) params[cfg.scope.param] = state.scopeValue
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/aggregate/${cfg.resource}`, { params })
    state.rows = data.data ?? []
    state.panels = data.panels ?? {}
  } catch {
    state.rows = []
    state.panels = {}
  } finally {
    state.loading = false
  }
  buildScopeOptions()
}

// Populate the scope dropdown: fetch the users aggregate for user_id scopes,
// or derive distinct values from the loaded rows (e.g. queries → connection).
async function buildScopeOptions() {
  if (!cfg?.scope) return
  const { source } = cfg.scope
  if (source === 'users') {
    if (state.scopeOptions.length) return
    try {
      const { data } = await api.get(`/api/apps/${appId.value}/aggregate/users`, { params: { period: app.period } })
      state.scopeOptions = (data.data ?? []).map((u) => ({ value: u.user_id, label: u.email ?? u.user_id }))
    } catch {
      state.scopeOptions = []
    }
  } else if (source?.startsWith('rows:') && !state.scopeValue) {
    const field = source.slice(5)
    const seen = new Set()
    state.scopeOptions = state.rows
      .map((r) => r[field])
      .filter((v) => v && !seen.has(v) && seen.add(v))
      .map((v) => ({ value: v, label: v }))
  }
}

const panelDescriptors = computed(() => (cfg?.panels ? cfg.panels(state.panels) : []))

// Client-side Handled / Unhandled / View-all segmenting for exceptions.
const displayRows = computed(() => {
  if (cfg?.handledFilter && state.handledFilter !== 'all') {
    const want = state.handledFilter === 'handled'
    return state.rows.filter((r) => Boolean(r.handled) === want)
  }
  return state.rows
})

const unhandledCount = computed(() => state.rows.filter((r) => !r.handled).length)

function onSort(sort) {
  state.sort = sort
  load()
}
function onSearch(q) {
  state.search = q
  load()
}
function onScope(value) {
  state.scopeValue = value
  load()
}
function onRowClick(row) {
  if (!cfg?.rowLink) return
  // rowLink returns null for a row with a null/empty group key (broken URL) —
  // no-op, matching how non-clickable resources behave.
  const to = cfg.rowLink(row, appId.value)
  if (to) router.push(to)
}

watch([() => app.period, () => app.environment], load, { immediate: true })
</script>

<template>
  <div v-if="!cfg" class="text-sm text-gray-500 dark:text-gray-400">Unknown resource.</div>

  <AggregateTable
    v-else
    :resource="cfg.resource"
    :columns="cfg.columns"
    :rows="displayRows"
    :loading="state.loading"
    :sort="state.sort"
    :search="state.search"
    :searchable="cfg.searchable"
    :search-placeholder="cfg.searchPlaceholder"
    :row-key="cfg.rowKey"
    empty-text="No records found. Try adjusting any filters or time range."
    @sort="onSort"
    @search="onSearch"
    @row-click="onRowClick"
  >
    <template v-if="panelDescriptors.length" #panels>
      <template v-for="(panel, i) in panelDescriptors" :key="i">
        <BarChartPanel
          v-if="panel.kind === 'bar'"
          :title="panel.title"
          :labels="panel.labels"
          :datasets="panel.datasets"
          :stacked="panel.stacked"
          height-class="h-36"
        >
          <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ panel.total }}</span>
            <span
              v-for="b in panel.breakdown"
              :key="b.label"
              class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
            >
              <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: b.color }" />
              {{ b.label }} {{ b.value }}
            </span>
          </div>
        </BarChartPanel>

        <StatPanel v-else :title="panel.title">
          <div class="grid grid-cols-2 gap-3">
            <div v-for="s in panel.stats" :key="s.label">
              <dt class="text-xs text-gray-500 dark:text-gray-400">{{ s.label }}</dt>
              <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100" :class="s.class">{{ s.value }}</dd>
            </div>
          </div>
          <template v-if="panel.caption" #footer>{{ panel.caption }}</template>
        </StatPanel>
      </template>
    </template>

    <template #filters>
      <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
        {{ displayRows.length }} {{ cfg.countLabel }}
      </span>

      <select
        v-if="cfg.scope"
        :value="state.scopeValue"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        @change="onScope($event.target.value)"
      >
        <option value="">{{ cfg.scope.label }}</option>
        <option v-for="opt in state.scopeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <div
        v-if="cfg.handledFilter"
        class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800"
      >
        <button
          v-for="opt in [
            { value: 'all', label: 'View all' },
            { value: 'handled', label: 'Handled' },
            { value: 'unhandled', label: `Unhandled${unhandledCount ? ` (${unhandledCount})` : ''}` },
          ]"
          :key="opt.value"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            state.handledFilter === opt.value
              ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-400'
              : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
          "
          @click="state.handledFilter = opt.value"
        >
          {{ opt.label }}
        </button>
      </div>
    </template>
  </AggregateTable>
</template>
