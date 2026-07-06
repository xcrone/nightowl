<script setup>
import { reactive, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { absoluteTime } from '../../utils/format'
import { levelColor, BADGE } from '../../resourceConfig'
import { debounce } from '../../utils/debounce'

// Logs are a flat, searchable stream (no aggregation, no chart panels) — the
// generic telemetry list envelope { data, last_page }. Level dropdown + search,
// timestamps rendered absolute in the top-bar timezone/format.
const route = useRoute()
const app = useAppStore()

const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']

const state = reactive({
  rows: [],
  loading: false,
  level: '',
  search: '',
  lastPage: 1,
})

const appId = computed(() => route.params.appId)

async function load() {
  state.loading = true
  const params = { period: app.period }
  if (state.level) params.level = state.level
  if (state.search) params.q = state.search
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/logs`, { params })
    state.rows = data.data ?? []
    state.lastPage = data.last_page ?? 1
  } catch {
    state.rows = []
  } finally {
    state.loading = false
  }
}

const emitSearch = debounce(load, 300)
function onSearchInput(value) {
  state.search = value
  emitSearch()
}
function onLevel(value) {
  state.level = value
  load()
}

function when(iso) {
  return absoluteTime(iso, { timezone: app.timezone, format: app.timeFormat })
}

watch(() => app.period, load, { immediate: true })
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ state.rows.length }} Logs</span>

      <input
        type="text"
        :value="state.search"
        placeholder="Search logs…"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        @input="onSearchInput($event.target.value)"
      />

      <select
        :value="state.level"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        @change="onLevel($event.target.value)"
      >
        <option value="">All Levels</option>
        <option v-for="lvl in LEVELS" :key="lvl" :value="lvl">{{ lvl }}</option>
      </select>
    </div>

    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Date</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Source</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Level</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Message</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <tr v-if="state.loading">
            <td colspan="4" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">Loading…</td>
          </tr>
          <tr v-else-if="!state.rows.length">
            <td colspan="4" class="px-3 py-6 text-center text-gray-400 dark:text-gray-500">
              No logs found — try adjusting any filters or time range.
            </td>
          </tr>
          <tr v-for="(row, i) in state.rows" v-else :key="row.id ?? i" class="hover:bg-gray-50 dark:hover:bg-gray-800">
            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ when(row.created_at) }}</td>
            <td class="px-3 py-2">
              <span v-if="row.source" class="rounded px-2 py-0.5 text-xs font-medium" :class="BADGE.gray">{{ row.source }}</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-3 py-2">
              <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="levelColor(row.level)">{{ row.level }}</span>
            </td>
            <td class="max-w-xl truncate px-3 py-2 text-gray-900 dark:text-gray-100">{{ row.message }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
