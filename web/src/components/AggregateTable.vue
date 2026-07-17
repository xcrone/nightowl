<script setup>
import { ref, watch } from 'vue'
import { formatValue } from '../utils/format'
import { debounce } from '../utils/debounce'
import { useAppStore } from '../store/app'

// Presentational workhorse for the aggregated list pages. The parent fetches
// data (period/scope aware) and passes it in; this component only renders the
// optional panel area (via the `panels` slot), a debounced search box, and a
// sortable, badge-aware table. It stays stateless about data — sort/search
// changes are emitted for the parent to apply and re-fetch.
//
// Column shape:
//   { key, label,
//     format?: string | (value, row, opts) => string, // display; opts carries the
//                                                     // top-bar { timezone, format }
//     badge?: (value, row) => tailwindClassString, // pill background
//     cellClass?: (value, row) => tailwindClassString, // e.g. slow=red text
//     sortable?: boolean (default true),
//     align?: 'left' | 'right' (default 'left') }
const props = defineProps({
  resource: { type: String, default: '' },
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  search: { type: String, default: '' },
  // Current sort key, e.g. 'total' (asc) or '-total' (desc).
  sort: { type: String, default: '' },
  searchable: { type: Boolean, default: true },
  searchPlaceholder: { type: String, default: 'Search…' },
  rowKey: { type: String, default: 'id' },
  emptyText: { type: String, default: 'No records.' },
  // Row keys to flash as new/changed on a live tick — see useRowHighlight.
  // Empty (the default) highlights nothing, so non-live pages are unaffected.
  highlightKeys: { type: Array, default: () => [] },
})

const emit = defineEmits(['search', 'sort', 'row-click'])

const app = useAppStore()

const searchText = ref(props.search)
watch(() => props.search, (v) => { searchText.value = v })

const emitSearch = debounce((value) => emit('search', value), 300)
function onSearchInput(value) {
  searchText.value = value
  emitSearch(value)
}

function fieldOf(sortKey) {
  return sortKey?.startsWith('-') ? sortKey.slice(1) : sortKey
}

function directionOf(col) {
  if (fieldOf(props.sort) !== col.key) return null
  return props.sort.startsWith('-') ? 'desc' : 'asc'
}

function onSort(col) {
  if (col.sortable === false) return
  const current = directionOf(col)
  // asc -> desc -> asc; first click on a new column defaults to descending
  // (aggregate pages want "biggest first").
  const next = current === 'desc' ? col.key : `-${col.key}`
  emit('sort', next)
}

function display(col, row) {
  const value = row[col.key]
  const opts = { timezone: app.timezone, format: app.timeFormat }
  if (typeof col.format === 'function') return col.format(value, row, opts)
  return formatValue(value, col.format, opts)
}

function cellClasses(col, row) {
  const base = col.align === 'right' ? 'text-right' : 'text-left'
  const extra = typeof col.cellClass === 'function' ? col.cellClass(row[col.key], row) : ''
  return [base, extra]
}
</script>

<template>
  <div>
    <div v-if="$slots.panels" class="mb-4 grid gap-4 sm:grid-cols-2">
      <slot name="panels" />
    </div>

    <div v-if="searchable || $slots.filters" class="mb-4 flex flex-wrap items-center gap-2">
      <input
        v-if="searchable"
        type="text"
        :value="searchText"
        :placeholder="searchPlaceholder"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        @input="onSearchInput($event.target.value)"
      />
      <slot name="filters" />
    </div>

    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400"
              :class="[
                col.align === 'right' ? 'text-right' : 'text-left',
                col.sortable === false ? '' : 'cursor-pointer select-none hover:text-gray-800 dark:hover:text-gray-200',
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
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">Loading…</td>
          </tr>
          <tr v-else-if="!rows.length">
            <td :colspan="columns.length" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">{{ emptyText }}</td>
          </tr>
          <tr
            v-for="(row, i) in rows"
            v-else
            :key="row[rowKey] ?? i"
            class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
            :class="highlightKeys.includes(row[rowKey]) ? 'bg-primary-50 dark:bg-primary-600/20' : ''"
            @click="emit('row-click', row)"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="max-w-xs truncate px-3 py-2 text-gray-900 dark:text-gray-100"
              :class="cellClasses(col, row)"
            >
              <span
                v-if="col.badge"
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="col.badge(row[col.key], row)"
              >
                {{ display(col, row) }}
              </span>
              <span v-else>{{ display(col, row) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
