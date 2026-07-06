<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  Filler,
} from 'chart.js'
import StatPanel from './StatPanel.vue'
import { useThemeStore } from '../store/theme'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler)

const props = defineProps({
  title: { type: String, default: '' },
  labels: { type: Array, default: () => [] },
  // [{ label, data:[], color? }]
  series: { type: Array, default: () => [] },
  heightClass: { type: String, default: 'h-44' },
  showLegend: { type: Boolean, default: true },
})

const theme = useThemeStore()

const PALETTE = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6']

const data = computed(() => ({
  labels: props.labels,
  datasets: props.series.map((s, i) => {
    const color = s.color ?? PALETTE[i % PALETTE.length]
    return {
      label: s.label,
      data: s.data,
      borderColor: color,
      backgroundColor: color,
      pointRadius: 0,
      borderWidth: 2,
      tension: 0.3,
    }
  }),
}))

const options = computed(() => {
  const tick = theme.isDark ? '#9ca3af' : '#6b7280'
  const grid = theme.isDark ? '#374151' : '#e5e7eb'
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: props.showLegend && props.series.length > 1, labels: { color: tick, boxWidth: 12 } },
    },
    scales: {
      x: { ticks: { color: tick }, grid: { display: false } },
      y: { ticks: { color: tick }, grid: { color: grid }, beginAtZero: true },
    },
  }
})
</script>

<template>
  <StatPanel :title="title">
    <template v-if="$slots.actions" #actions><slot name="actions" /></template>
    <div :class="heightClass">
      <Line :data="data" :options="options" />
    </div>
    <slot />
  </StatPanel>
</template>
