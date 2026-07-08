<script setup>
import { reactive } from 'vue'
import api from '../services/api'
import Modal from './Modal.vue'

// Reusable Add/Edit App modal — the single implementation of "create/edit an
// app" shared by OrgDashboard.vue (✎ icon on an app card, "+ Add app" per
// team) and SettingsPage.vue ("Edit app" button on the app's own settings
// page). Callers drive it imperatively via a template ref
// (`openCreate(team)` / `openEdit(appItem, team?)`) rather than v-model, since
// "which app/team" is per-invocation state, not a prop the parent needs to
// track. `team` is optional for edit — only needed by callers (OrgDashboard)
// that want it echoed back on `saved` to file the update under the right
// team; SettingsPage doesn't have team context and doesn't need it.
const emit = defineEmits(['saved'])

const state = reactive({
  open: false,
  mode: 'create',
  team: null,
  appId: null,
  name: '',
  description: '',
  error: '',
  saving: false,
})

function openCreate(team) {
  state.open = true
  state.mode = 'create'
  state.team = team
  state.appId = null
  state.name = ''
  state.description = ''
  state.error = ''
}

function openEdit(appItem, team = null) {
  state.open = true
  state.mode = 'edit'
  state.team = team
  state.appId = appItem?.app_id ?? null
  state.name = appItem?.name ?? ''
  state.description = appItem?.description ?? ''
  state.error = ''
}

function close() {
  state.open = false
}

async function submit() {
  if (!state.name.trim()) {
    state.error = 'Name is required.'
    return
  }
  state.saving = true
  state.error = ''
  const payload = {
    name: state.name.trim(),
    description: state.description,
  }
  try {
    const { data } =
      state.mode === 'create'
        ? await api.post(`/api/teams/${state.team.uuid}/apps`, payload)
        : await api.put(`/api/apps/${state.appId}`, payload)
    emit('saved', { mode: state.mode, team: state.team, app: data })
    state.open = false
  } catch (e) {
    state.error = e?.response?.data?.message ?? 'Could not save app.'
  } finally {
    state.saving = false
  }
}

defineExpose({ openCreate, openEdit })
</script>

<template>
  <Modal
    v-if="state.open"
    :title="state.mode === 'create' ? `Add app to ${state.team?.name}` : 'Edit app'"
    @close="close"
  >
    <div class="space-y-3">
      <div>
        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
        <input
          v-model="state.name"
          type="text"
          data-test="app-modal-name"
          autofocus
          class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Description</label>
        <input
          v-model="state.description"
          type="text"
          class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
      </div>
      <p v-if="state.error" class="text-xs text-red-600 dark:text-red-400">{{ state.error }}</p>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
          @click="close"
        >
          Cancel
        </button>
        <button
          type="button"
          data-test="app-modal-submit"
          class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          :disabled="state.saving"
          @click="submit"
        >
          {{ state.saving ? 'Saving…' : state.mode === 'create' ? 'Add app' : 'Save' }}
        </button>
      </div>
    </div>
  </Modal>
</template>
