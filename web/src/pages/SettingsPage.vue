<script setup>
import { onMounted, reactive } from 'vue'
import api from '../services/api'

const state = reactive({ settings: {}, loading: false, saving: null })

async function load() {
  state.loading = true
  const { data } = await api.get('/api/settings')
  state.settings = data
  state.loading = false
}

async function save(key) {
  state.saving = key
  try {
    await api.put(`/api/settings/${key}`, { value: state.settings[key] })
  } finally {
    state.saving = null
  }
}

function addSetting() {
  const key = prompt('Setting key (e.g. slow_request_threshold_ms):')
  if (key) state.settings[key] = ''
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Settings</h1>
      <button
        class="rounded border border-gray-300 px-3 py-1 text-sm dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
        @click="addSetting"
      >
        Add setting
      </button>
    </div>

    <div class="max-w-lg space-y-3">
      <div v-if="!state.loading && !Object.keys(state.settings).length" class="text-gray-400 dark:text-gray-500">
        No settings configured yet.
      </div>

      <div v-for="(value, key) in state.settings" :key="key" class="flex items-center gap-2">
        <label class="w-56 shrink-0 text-sm text-gray-700 dark:text-gray-300">{{ key }}</label>
        <input
          v-model="state.settings[key]"
          class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <button
          class="rounded bg-primary-600 px-3 py-1 text-sm font-medium text-white disabled:opacity-50"
          :disabled="state.saving === key"
          @click="save(key)"
        >
          Save
        </button>
      </div>
    </div>
  </div>
</template>
