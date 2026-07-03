<script setup>
import { onMounted, reactive } from 'vue'
import api from '../services/api'

const state = reactive({
  channels: [],
  loading: false,
  form: { name: '', type: 'slack', webhook_url: '', url: '', secret: '', recipients: '' },
  error: null,
  submitting: false,
})

async function load() {
  state.loading = true
  const { data } = await api.get('/api/alert-channels')
  state.channels = data
  state.loading = false
}

function configFor(form) {
  if (form.type === 'slack' || form.type === 'discord') return { webhook_url: form.webhook_url }
  if (form.type === 'webhook') return { url: form.url, secret: form.secret || null }
  if (form.type === 'email') return { recipients: form.recipients.split(',').map((s) => s.trim()) }
  return {}
}

async function create() {
  state.error = null
  state.submitting = true
  try {
    await api.post('/api/alert-channels', {
      name: state.form.name,
      type: state.form.type,
      config: configFor(state.form),
    })
    state.form = { name: '', type: 'slack', webhook_url: '', url: '', secret: '', recipients: '' }
    await load()
  } catch (e) {
    state.error = e.response?.data?.message ?? 'Failed to save channel.'
  } finally {
    state.submitting = false
  }
}

async function toggle(channel) {
  const { data } = await api.post(`/api/alert-channels/${channel.id}/toggle`)
  channel.enabled = data.enabled
}

async function remove(channel) {
  await api.delete(`/api/alert-channels/${channel.id}`)
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Alert Channels</h1>

    <div class="mb-6 overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Enabled</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <tr v-if="!state.loading && !state.channels.length">
            <td colspan="4" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">No alert channels configured.</td>
          </tr>
          <tr v-for="channel in state.channels" :key="channel.id">
            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ channel.name }}</td>
            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ channel.type }}</td>
            <td class="px-3 py-2">
              <button
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="
                  channel.enabled
                    ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-400'
                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                "
                @click="toggle(channel)"
              >
                {{ channel.enabled ? 'Enabled' : 'Disabled' }}
              </button>
            </td>
            <td class="px-3 py-2 text-right">
              <button class="text-sm text-red-600 hover:underline dark:text-red-400" @click="remove(channel)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <form class="max-w-md rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" @submit.prevent="create">
      <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">New channel</h2>

      <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">Name</label>
      <input
        v-model="state.form.name"
        required
        class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
      />

      <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">Type</label>
      <select
        v-model="state.form.type"
        class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
      >
        <option value="slack">Slack</option>
        <option value="discord">Discord</option>
        <option value="webhook">Webhook</option>
        <option value="email">Email</option>
      </select>

      <template v-if="state.form.type === 'slack' || state.form.type === 'discord'">
        <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">Webhook URL</label>
        <input
          v-model="state.form.webhook_url"
          required
          class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
      </template>

      <template v-else-if="state.form.type === 'webhook'">
        <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">URL</label>
        <input
          v-model="state.form.url"
          required
          class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">Secret (optional)</label>
        <input
          v-model="state.form.secret"
          class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
      </template>

      <template v-else-if="state.form.type === 'email'">
        <label class="mb-1 block text-sm text-gray-700 dark:text-gray-300">Recipients (comma-separated)</label>
        <input
          v-model="state.form.recipients"
          required
          class="mb-3 w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
      </template>

      <p v-if="state.error" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ state.error }}</p>

      <button
        type="submit"
        :disabled="state.submitting"
        class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
      >
        Add channel
      </button>
    </form>
  </div>
</template>
