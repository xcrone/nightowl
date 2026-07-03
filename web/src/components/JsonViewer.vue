<script setup>
const props = defineProps({
  data: { required: true },
  keyName: { type: [String, Number], default: null },
  depth: { type: Number, default: 0 },
})

function isExpandable(value) {
  return value !== null && typeof value === 'object'
}

function typeLabel(value) {
  if (Array.isArray(value)) return `Array(${value.length})`
  return `Object(${Object.keys(value).length})`
}

function entries(value) {
  if (Array.isArray(value)) return value.map((item, i) => [i, item])
  return Object.entries(value)
}

function primitiveClass(value) {
  if (value === null) return 'text-gray-400 dark:text-gray-500'
  if (typeof value === 'string') return 'text-green-700 dark:text-green-400'
  if (typeof value === 'number') return 'text-blue-700 dark:text-blue-400'
  if (typeof value === 'boolean') return 'text-purple-700 dark:text-purple-400'
  return 'text-gray-900 dark:text-gray-100'
}

function formatPrimitive(value) {
  if (value === null) return 'null'
  if (typeof value === 'string') return JSON.stringify(value)
  return String(value)
}
</script>

<template>
  <div>
    <details v-if="isExpandable(data)" :open="depth < 2" class="leading-relaxed">
      <summary class="cursor-pointer select-none">
        <span v-if="keyName !== null" class="text-primary-700 dark:text-primary-400">{{ keyName }}</span
        ><span v-if="keyName !== null" class="text-gray-500 dark:text-gray-400">: </span
        ><span class="text-gray-400 dark:text-gray-500">{{ typeLabel(data) }}</span>
      </summary>
      <div class="ml-3 border-l border-gray-200 pl-3 dark:border-gray-700">
        <JsonViewer v-for="[k, v] in entries(data)" :key="k" :data="v" :key-name="k" :depth="depth + 1" />
      </div>
    </details>
    <div v-else class="leading-relaxed">
      <span v-if="keyName !== null" class="text-primary-700 dark:text-primary-400">{{ keyName }}</span
      ><span v-if="keyName !== null" class="text-gray-500 dark:text-gray-400">: </span
      ><span :class="primitiveClass(data)">{{ formatPrimitive(data) }}</span>
    </div>
  </div>
</template>
