<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
} from 'chart.js'
import StatPanel from './StatPanel.vue'
import { useThemeStore } from '../store/theme'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const props = defineProps({
  title: { type: String, default: '' },
  labels: { type: Array, default: () => [] },
  // [{ label, data:[], backgroundColor? }]
  datasets: { type: Array, default: () => [] },
  stacked: { type: Boolean, default: false },
  heightClass: { type: String, default: 'h-44' },
  showLegend: { type: Boolean, default: true },
})

const theme = useThemeStore()

const PALETTE = ['#f59e0b', '#3b82f6', '#ef4444', '#10b981', '#8b5cf6']

const data = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map((ds, i) => ({
    backgroundColor: PALETTE[i % PALETTE.length],
    borderRadius: 3,
    ...ds,
  })),
}))

const options = computed(() => {
  const tick = theme.isDark ? '#9ca3af' : '#6b7280'
  const grid = theme.isDark ? '#374151' : '#e5e7eb'
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: props.showLegend && props.datasets.length > 1, labels: { color: tick, boxWidth: 12 } },
    },
    scales: {
      x: { stacked: props.stacked, ticks: { color: tick }, grid: { display: false } },
      y: { stacked: props.stacked, ticks: { color: tick }, grid: { color: grid }, beginAtZero: true },
    },
  }
})
</script>

<template>
  <StatPanel :title="title">
    <template v-if="$slots.actions" #actions><slot name="actions" /></template>
    <div :class="heightClass">
      <Bar :data="data" :options="options" />
    </div>
    <slot />
  </StatPanel>
</template>
