<script setup>
import { computed, reactive, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { resources } from '../resourceConfig'
import { formatValue } from '../utils/format'
import { debounce } from '../utils/debounce'

const props = defineProps({
  resource: { type: String, required: true },
})

const route = useRoute()
const router = useRouter()

const config = computed(() => resources[props.resource])

const state = reactive({
  rows: [],
  loading: false,
  error: null,
  page: 1,
  lastPage: 1,
  activeFilters: {},
  search: '',
})

// Structural correlation filter arriving from ResourceDetail's "Related"
// panel (e.g. /queries?execution_source=request&execution_id=...) — not a
// per-resource business filter, so it's read straight from the URL rather
// than config.filters.
const relatedFilter = computed(() => {
  const { execution_source: source, execution_id: id } = route.query
  return source && id ? { execution_source: source, execution_id: id } : null
})

function clearRelatedFilter() {
  router.replace({ path: route.path })
}

async function load() {
  state.loading = true
  state.error = null

  try {
    const params = {
      page: state.page,
      sort: config.value.defaultSort,
      ...state.activeFilters,
      ...relatedFilter.value,
      q: state.search || undefined,
    }
    const { data } = await api.get(`/api/${props.resource}`, { params })
    state.rows = data.data
    state.lastPage = data.last_page
  } catch (e) {
    state.error = e.response?.data?.message ?? 'Failed to load records.'
  } finally {
    state.loading = false
  }
}

function toggleFlagFilter(key) {
  if (state.activeFilters[key]) {
    delete state.activeFilters[key]
  } else {
    state.activeFilters[key] = 1
  }
  state.page = 1
}

function setSelectFilter(key, value) {
  if (value === '') {
    delete state.activeFilters[key]
  } else {
    state.activeFilters[key] = value
  }
  state.page = 1
}

// Same "narrowing change -> re-page to 1" convention as toggleFlagFilter/
// setSelectFilter above, debounced so each keystroke doesn't fire a request.
const debouncedSearch = debounce(() => {
  state.page = 1
  load()
}, 300)

function onSearchInput(value) {
  state.search = value
  debouncedSearch()
}

watch(() => [state.page, state.activeFilters], load, { deep: true, immediate: false })
watch(
  () => [props.resource, relatedFilter.value],
  () => {
    state.page = 1
    state.activeFilters = {}
    state.search = ''
    load()
  },
  { deep: true, immediate: true },
)
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ config.label }}</h1>
    </div>

    <div
      v-if="relatedFilter"
      class="mb-4 flex items-center justify-between rounded border border-primary-200 bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-400"
    >
      <span>Showing entries linked to this execution</span>
      <button type="button" class="font-medium hover:underline" @click="clearRelatedFilter">Clear</button>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <input
        type="text"
        :value="state.search"
        @input="onSearchInput($event.target.value)"
        :placeholder="config.searchPlaceholder ?? 'Search…'"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
      />
      <template v-for="filter in config.filters" :key="filter.key">
        <button
          v-if="filter.flag"
          type="button"
          class="rounded-full border px-3 py-1 text-sm"
          :class="
            state.activeFilters[filter.key]
              ? 'border-primary-500 bg-primary-100 text-primary-700 dark:border-primary-500 dark:bg-primary-500/15 dark:text-primary-400'
              : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300'
          "
          @click="toggleFlagFilter(filter.key)"
        >
          {{ filter.label }}
        </button>
        <select
          v-else
          class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          :value="state.activeFilters[filter.key] ?? ''"
          @change="setSelectFilter(filter.key, $event.target.value)"
        >
          <option value="">{{ filter.label }}: all</option>
          <option v-for="option in filter.options" :key="option" :value="option">{{ option }}</option>
        </select>
      </template>
    </div>

    <div v-if="state.error" class="rounded border border-red-300 bg-red-50 p-3 text-red-700 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400">
      {{ state.error }}
    </div>

    <div v-else class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th v-for="col in config.columns" :key="col.key" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <tr v-if="state.loading">
            <td :colspan="config.columns.length" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">Loading…</td>
          </tr>
          <tr v-else-if="!state.rows.length">
            <td :colspan="config.columns.length" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">No records.</td>
          </tr>
          <RouterLink
            v-for="row in state.rows"
            v-else
            :key="row.id"
            :to="`/${resource}/${row.id}`"
            custom
            v-slot="{ navigate }"
          >
            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" @click="navigate">
              <td v-for="col in config.columns" :key="col.key" class="max-w-xs truncate px-3 py-2 text-gray-900 dark:text-gray-100">
                <span v-if="col.badge" class="rounded px-2 py-0.5 text-xs font-medium" :class="col.badge(row[col.key])">
                  {{ formatValue(row[col.key], col.format) }}
                </span>
                <span v-else>{{ formatValue(row[col.key], col.format) }}</span>
              </td>
            </tr>
          </RouterLink>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-40 dark:border-gray-700"
        :disabled="state.page <= 1"
        @click="state.page--"
      >
        Previous
      </button>
      <span>Page {{ state.page }} of {{ state.lastPage }}</span>
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-40 dark:border-gray-700"
        :disabled="state.page >= state.lastPage"
        @click="state.page++"
      >
        Next
      </button>
    </div>
  </div>
</template>
