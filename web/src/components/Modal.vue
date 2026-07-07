<script setup>
// Small reusable modal dialog: overlay + centered panel. Body is the default
// slot; emits 'close' on backdrop click, Escape, or the × button.
defineProps({
  title: { type: String, default: '' },
})
const emit = defineEmits(['close'])

function onKeydown(e) {
  if (e.key === 'Escape') emit('close')
}
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @keydown="onKeydown"
    @click.self="$emit('close')"
  >
    <div
      role="dialog"
      aria-modal="true"
      class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-5 shadow-lg dark:border-gray-700 dark:bg-gray-900"
    >
      <div class="mb-4 flex items-start justify-between gap-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ title }}</h2>
        <button
          type="button"
          aria-label="Close"
          class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <slot />
    </div>
  </div>
</template>
