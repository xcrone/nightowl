<script setup>
import { reactive, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api'
import { dataTypes } from '../../nav'
import StatPanel from '../../components/StatPanel.vue'

// Selective retention tooling: purge specific telemetry categories older than
// 30 days. Preview-only in this read-only demo (delete disabled).
// POST /api/apps/{appId}/data-management/preview { from, to, types }
const route = useRoute()
const appId = computed(() => route.params.appId)

// Slug → human label for the 11 prunable categories (nav.js `dataTypes`).
const LABELS = {
  requests: 'Requests',
  queries: 'Queries',
  exceptions: 'Exceptions',
  commands: 'Commands',
  jobs: 'Jobs',
  'cache-events': 'Cache Events',
  mail: 'Mail',
  notifications: 'Notifications',
  'outgoing-requests': 'Outgoing Requests',
  'scheduled-tasks': 'Scheduled Tasks',
  logs: 'Logs',
}
function labelFor(slug) {
  return LABELS[slug] ?? slug
}

// End of the deletable window: 30 days ago (can't delete anything newer).
const maxEnd = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

const state = reactive({
  from: '',
  to: maxEnd,
  selected: new Set(),
  preview: null,
  loading: false,
})

const validationError = ref('')

const allSelected = computed(() => state.selected.size === dataTypes.length)

function toggleType(slug) {
  if (state.selected.has(slug)) state.selected.delete(slug)
  else state.selected.add(slug)
  // Force reactivity on the Set.
  state.selected = new Set(state.selected)
}
function toggleAll() {
  state.selected = allSelected.value ? new Set() : new Set(dataTypes)
}
function isSelected(slug) {
  return state.selected.has(slug)
}

async function preview() {
  validationError.value = ''
  if (state.selected.size === 0) {
    validationError.value = 'Select at least one data type.'
    return
  }
  state.loading = true
  try {
    const { data } = await api.post(`/api/apps/${appId.value}/data-management/preview`, {
      from: state.from || null,
      to: state.to,
      types: [...state.selected],
    })
    state.preview = data
  } catch {
    state.preview = null
  } finally {
    state.loading = false
  }
}

const previewRows = computed(() => {
  if (!state.preview?.counts) return []
  return Object.entries(state.preview.counts).map(([type, count]) => ({ type, count }))
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Data Management</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Selectively delete monitoring data older than 30 days.</p>
    </div>

    <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
      Read-only demo — preview impact only; the destructive delete is disabled.
    </div>

    <!-- Date range -->
    <StatPanel title="Select Date Range">
      <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Target window is constrained to data older than 30 days (on or before {{ maxEnd }}).</p>
      <div class="flex flex-wrap items-end gap-4">
        <label class="flex flex-col text-xs text-gray-500 dark:text-gray-400">
          From
          <input
            v-model="state.from"
            type="date"
            :max="maxEnd"
            class="mt-1 rounded border border-gray-300 px-2 py-1 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </label>
        <label class="flex flex-col text-xs text-gray-500 dark:text-gray-400">
          To
          <input
            v-model="state.to"
            type="date"
            :max="maxEnd"
            class="mt-1 rounded border border-gray-300 px-2 py-1 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </label>
      </div>
    </StatPanel>

    <!-- Data types -->
    <StatPanel title="Choose Data Types">
      <template #actions>
        <button
          type="button"
          class="text-primary-600 dark:text-primary-400"
          @click="toggleAll"
        >{{ allSelected ? 'Deselect all' : 'Select all' }}</button>
      </template>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="slug in dataTypes"
          :key="slug"
          type="button"
          class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
          :class="
            isSelected(slug)
              ? 'border-primary-600 bg-primary-600 text-white dark:border-primary-500 dark:bg-primary-500'
              : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800'
          "
          @click="toggleType(slug)"
        >{{ labelFor(slug) }}</button>
      </div>

      <p v-if="validationError" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ validationError }}</p>

      <div class="mt-4 flex justify-end">
        <button
          type="button"
          class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          :disabled="state.loading"
          @click="preview"
        >{{ state.loading ? 'Previewing…' : 'Preview Impact' }}</button>
      </div>
    </StatPanel>

    <!-- Preview result -->
    <StatPanel v-if="state.preview" title="Preview Impact">
      <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Data Type</th>
              <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-400">Rows</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-for="row in previewRows" :key="row.type">
              <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ labelFor(row.type) }}</td>
              <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ row.count }}</td>
            </tr>
          </tbody>
          <tfoot class="border-t border-gray-200 dark:border-gray-700">
            <tr>
              <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-100">Total</td>
              <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">{{ state.preview.total ?? 0 }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="mt-4 flex justify-end">
        <button
          type="button"
          class="cursor-not-allowed rounded bg-red-600 px-4 py-2 text-sm font-medium text-white opacity-50"
          disabled
          title="Disabled in the read-only demo"
        >Delete (disabled)</button>
      </div>
    </StatPanel>
  </div>
</template>
