<script setup>
// Titled card wrapper used across the dashboard and list pages. The default
// slot is the body; an optional `actions` slot sits on the right of the header
// (e.g. a "View" link), and a `footer` slot for the resolved-window caption.
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div v-if="title || $slots.actions" class="mb-3 flex items-start justify-between gap-2">
      <div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ title }}</h3>
        <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="shrink-0 text-sm">
        <slot name="actions" />
      </div>
    </div>

    <slot />

    <div v-if="$slots.footer" class="mt-3 text-xs text-gray-400 dark:text-gray-500">
      <slot name="footer" />
    </div>
  </div>
</template>
