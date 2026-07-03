<script setup>
import { reactive, watch } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js'
import api from '../services/api'
import { formatDuration } from '../utils/format'
import { useThemeStore } from '../store/theme'

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

const TYPES = ['queries', 'requests', 'jobs', 'outgoing-requests', 'cache-events']

const theme = useThemeStore()

const state = reactive({
  type: 'queries',
  rows: [],
  loading: false,
})

async function load() {
  state.loading = true
  const { data } = await api.get(`/api/rollups/${state.type}`)
  state.rows = data.data.slice(0, 15)
  state.loading = false
}

watch(() => state.type, load, { immediate: true })

function chartData() {
  return {
    labels: state.rows.map((r) => r.label ?? r.key ?? r.group_hash?.slice(0, 8) ?? '—'),
    datasets: [
      {
        label: 'Call count (last 24h)',
        backgroundColor: '#f59e0b',
        data: state.rows.map((r) => r.call_count),
      },
    ],
  }
}

function chartOptions() {
  const tickColor = theme.isDark ? '#9ca3af' : '#6b7280'
  const gridColor = theme.isDark ? '#374151' : '#e5e7eb'

  return {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      // x-axis labels are full SQL/route/job strings — unreadable as
      // tick labels at any rotation; the table below already shows
      // them in full, so just drop them from the chart.
      x: { ticks: { display: false }, grid: { color: gridColor } },
      y: { ticks: { color: tickColor }, grid: { color: gridColor } },
    },
  }
}
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Trends</h1>
      <select
        v-model="state.type"
        class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
      >
        <option v-for="type in TYPES" :key="type" :value="type">{{ type }}</option>
      </select>
    </div>

    <div v-if="state.loading" class="text-gray-400 dark:text-gray-500">Loading…</div>

    <template v-else>
      <div class="mb-6 rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <Bar :data="chartData()" :options="chartOptions()" />
      </div>

      <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Group</th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Calls</th>
              <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Avg</th>
              <th v-if="state.rows[0]?.p95 !== undefined" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">p95</th>
              <th v-if="state.rows[0]?.p95 !== undefined" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">p99</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
            <tr v-if="!state.rows.length">
              <td colspan="5" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">No rollup data in this window.</td>
            </tr>
            <tr v-for="(row, i) in state.rows" :key="i">
              <td class="max-w-md truncate px-3 py-2 text-gray-900 dark:text-gray-100">{{ row.label ?? row.key ?? row.group_hash }}</td>
              <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ row.call_count }}</td>
              <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ formatDuration(row.avg_duration) }}</td>
              <td v-if="row.p95 !== undefined" class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ formatDuration(row.p95) }}</td>
              <td v-if="row.p99 !== undefined" class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ formatDuration(row.p99) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
