<script setup>
import { computed } from 'vue'

// Small colored connectivity/health dot (org dashboard "connected", agent
// health rows, etc). `status` maps to a color; `color` overrides directly.
const props = defineProps({
  status: { type: String, default: '' },
  color: { type: String, default: '' },
})

const dotClass = computed(() => {
  if (props.color) return props.color
  return {
    connected: 'bg-green-500',
    healthy: 'bg-green-500',
    disconnected: 'bg-gray-400',
    degraded: 'bg-amber-500',
    unhealthy: 'bg-red-500',
  }[props.status] ?? 'bg-gray-400'
})
</script>

<template>
  <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="dotClass" />
</template>
