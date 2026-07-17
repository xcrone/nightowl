<script setup>
import { useAppStore } from '../store/app'

// The top bar's Live/pause control, sat next to PeriodSelector because it's the
// same kind of thing: a window control every list page reads. Pages opt in by
// calling useLivePoll; AppShell only renders this on routes with meta.live.
const app = useAppStore()

const INTERVALS = [
  { value: 3000, label: '3s' },
  { value: 10000, label: '10s' },
  { value: 30000, label: '30s' },
]
</script>

<template>
  <div class="inline-flex items-center gap-1.5">
    <button
      type="button"
      :aria-pressed="app.live"
      class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors"
      :class="
        app.live
          ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-600 dark:bg-primary-600/20 dark:text-primary-400'
          : 'border-gray-200 bg-gray-50 text-gray-500 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
      "
      @click="app.setLive(!app.live)"
    >
      <span
        class="inline-block h-1.5 w-1.5 rounded-full"
        :class="app.live ? 'animate-pulse bg-primary-500' : 'bg-gray-400 dark:bg-gray-500'"
      />
      Live
    </button>

    <select
      :value="app.liveInterval"
      aria-label="Live refresh interval"
      class="rounded border border-gray-200 bg-gray-50 px-1.5 py-1 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
      @change="app.setLiveInterval(Number($event.target.value))"
    >
      <option v-for="i in INTERVALS" :key="i.value" :value="i.value">{{ i.label }}</option>
    </select>
  </div>
</template>
