<script setup>
import { useAppStore } from '../store/app'
import { periods } from '../nav.js'

// The 1H…30D pill row from the top bar, bound to the app store's period.
const app = useAppStore()
</script>

<template>
  <div class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
    <button
      v-for="p in periods"
      :key="p.value"
      type="button"
      class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
      :class="
        app.period === p.value
          ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-400'
          : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
      "
      @click="app.setPeriod(p.value)"
    >
      {{ p.label }}
    </button>
  </div>
</template>
