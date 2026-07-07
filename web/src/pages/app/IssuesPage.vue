<script setup>
import { reactive, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '../../store/app'
import { useAuthStore } from '../../store/auth'
import api from '../../services/api'
import { relativeTime } from '../../utils/format'
import { BADGE } from '../../resourceConfig'
import { debounce } from '../../utils/debounce'

// Error-tracking inbox: deduplicated issues (exceptions + performance) with
// type tabs, status/assignment pills, free-text search and sortable columns.
// GET /api/apps/{appId}/issues — paginated { data, last_page }, period-reactive.
const route = useRoute()
const router = useRouter()
const app = useAppStore()
const auth = useAuthStore()

const appId = computed(() => route.params.appId)

const state = reactive({
  rows: [],
  loading: false,
  lastPage: 1,
  type: 'exception',
  status: 'open',
  assignment: 'all',
  search: '',
  sort: '-last_seen_at',
})

// Sortable columns — key drives the `?sort=` param the API whitelists.
const COLUMNS = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'priority', label: 'Priority', sortable: true },
  { key: 'exception_class', label: 'Issue', sortable: true },
  { key: 'occurrences_count', label: 'Count', sortable: true, align: 'right' },
  { key: 'users_count', label: 'Users', sortable: true, align: 'right' },
  { key: 'first_seen_at', label: 'First Seen', sortable: true },
  { key: 'last_seen_at', label: 'Last Seen', sortable: true },
  { key: 'assigned_to', label: 'Assigned', sortable: false },
]

async function load() {
  if (!appId.value) return
  state.loading = true
  const params = { period: app.period, type: state.type, sort: state.sort }
  if (app.environment) params.environment = app.environment
  if (state.status) params.status = state.status
  if (state.search) params.q = state.search
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/issues`, { params })
    state.rows = data.data ?? []
    state.lastPage = data.last_page ?? 1
  } catch {
    state.rows = []
  } finally {
    state.loading = false
  }
}

// Assignment is a client-side filter over the loaded rows: Unassigned =
// assigned_to null; Mine = assigned_to matches the logged-in user's email.
const displayRows = computed(() => {
  if (state.assignment === 'unassigned') return state.rows.filter((r) => !r.assigned_to)
  if (state.assignment === 'mine') return state.rows.filter((r) => r.assigned_to === auth.user?.email)
  return state.rows
})

function fieldOf(sort) {
  return sort?.startsWith('-') ? sort.slice(1) : sort
}
function directionOf(col) {
  if (fieldOf(state.sort) !== col.key) return null
  return state.sort.startsWith('-') ? 'desc' : 'asc'
}
function onSort(col) {
  if (!col.sortable) return
  const current = directionOf(col)
  state.sort = current === 'desc' ? col.key : `-${col.key}`
  load()
}

const emitSearch = debounce(load, 300)
function onSearchInput(value) {
  state.search = value
  emitSearch()
}
function setType(type) {
  state.type = type
  load()
}
function setStatus(status) {
  state.status = status
  load()
}

function priorityLabel(row) {
  return row.priority ? row.priority : 'No priority'
}
function rowLink(row) {
  return `/dashboard/${appId.value}/issues/${row.id}`
}
function goto(row) {
  router.push(rowLink(row))
}

const TYPE_TABS = [
  { value: 'exception', label: 'Exceptions' },
  { value: 'performance', label: 'Performance' },
]
const STATUS_PILLS = [
  { value: 'open', label: 'Open' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'ignored', label: 'Ignored' },
]
const ASSIGN_PILLS = [
  { value: 'all', label: 'All' },
  { value: 'unassigned', label: 'Unassigned' },
  { value: 'mine', label: 'Mine' },
]

watch([() => app.period, () => app.environment], load, { immediate: true })
</script>

<template>
  <div class="space-y-4">
    <!-- Type tabs -->
    <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
      <button
        v-for="tab in TYPE_TABS"
        :key="tab.value"
        type="button"
        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="
          state.type === tab.value
            ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-400'
            : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
        "
        @click="setType(tab.value)"
      >
        {{ tab.label }}
        <span
          v-if="state.type === tab.value"
          class="ml-1 rounded-full px-1.5 py-0.5 text-xs font-medium"
          :class="BADGE.gray"
        >{{ displayRows.length }}</span>
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        type="text"
        :value="state.search"
        placeholder="Search"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        @input="onSearchInput($event.target.value)"
      />

      <div class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
        <button
          v-for="pill in STATUS_PILLS"
          :key="pill.value"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            state.status === pill.value
              ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-400'
              : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
          "
          @click="setStatus(pill.value)"
        >
          {{ pill.label }}
        </button>
      </div>

      <div class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
        <button
          v-for="pill in ASSIGN_PILLS"
          :key="pill.value"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="
            state.assignment === pill.value
              ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-400'
              : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
          "
          @click="state.assignment = pill.value"
        >
          {{ pill.label }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th
              v-for="col in COLUMNS"
              :key="col.key"
              class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400"
              :class="[
                col.align === 'right' ? 'text-right' : 'text-left',
                col.sortable ? 'cursor-pointer select-none hover:text-gray-800 dark:hover:text-gray-200' : '',
              ]"
              @click="onSort(col)"
            >
              <span class="inline-flex items-center gap-1">
                {{ col.label }}
                <span v-if="directionOf(col)" class="text-primary-600 dark:text-primary-400">
                  {{ directionOf(col) === 'desc' ? '↓' : '↑' }}
                </span>
              </span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <tr v-if="state.loading">
            <td :colspan="COLUMNS.length" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">Loading…</td>
          </tr>
          <tr v-else-if="!displayRows.length">
            <td :colspan="COLUMNS.length" class="px-3 py-6 text-center text-gray-400 dark:text-gray-500">
              No issues found — try adjusting any filters or time range.
            </td>
          </tr>
          <tr
            v-for="row in displayRows"
            v-else
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
            @click="goto(row)"
          >
            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">#{{ row.id }}</td>
            <!-- Priority is non-interactive (docs/pages/issues-list.md): clicking it must not navigate. -->
            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400" @click.stop>{{ priorityLabel(row) }}</td>
            <td class="max-w-md px-3 py-2">
              <div class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ row.exception_class }}</div>
              <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ row.exception_message }}</div>
            </td>
            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ row.occurrences_count ?? 0 }}</td>
            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ row.users_count ?? 0 }}</td>
            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ relativeTime(row.first_seen_at) }}</td>
            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">{{ relativeTime(row.last_seen_at) }}</td>
            <!-- Assigned is non-interactive (docs/pages/issues-list.md): clicking it must not navigate. -->
            <td class="px-3 py-2" @click.stop>
              <span
                v-if="row.assigned_to"
                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-400"
                :title="row.assigned_to"
              >{{ String(row.assigned_to).slice(0, 1).toUpperCase() }}</span>
              <span v-else class="inline-block h-6 w-6 rounded-full border border-dashed border-gray-300 dark:border-gray-600" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
