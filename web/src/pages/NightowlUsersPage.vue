<script setup>
import { onMounted, reactive } from 'vue'
import api from '../services/api'
import { formatDatetime } from '../utils/format'
import { debounce } from '../utils/debounce'

const state = reactive({ users: [], loading: false, search: '' })

async function load() {
  state.loading = true
  const { data } = await api.get('/api/users', { params: { q: state.search || undefined } })
  state.users = data.data
  state.loading = false
}

const debouncedLoad = debounce(load, 300)

function onSearchInput(value) {
  state.search = value
  debouncedLoad()
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Users</h1>

    <input
      type="text"
      :value="state.search"
      @input="onSearchInput($event.target.value)"
      placeholder="Search name, email…"
      class="mb-4 rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
    />

    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
            <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Last seen</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <tr v-if="state.loading">
            <td colspan="3" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">Loading…</td>
          </tr>
          <tr v-else-if="!state.users.length">
            <td colspan="3" class="px-3 py-4 text-center text-gray-400 dark:text-gray-500">
              {{ state.search ? 'No users match your search.' : 'No users seen yet.' }}
            </td>
          </tr>
          <tr v-for="user in state.users" v-else :key="user.user_id">
            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ user.name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ user.email ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ formatDatetime(user.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
