<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { relativeTime } from '../../utils/format'
import StatPanel from '../../components/StatPanel.vue'

// Per-app configuration hub: App ID, onboarding template (sync/apply),
// and tabbed sections (Environments default). Read-only demo — most writes
// are disabled but the controls are present.
// GET /api/apps/{appId}/settings
const route = useRoute()
const app = useAppStore()

const appId = computed(() => route.params.appId)

const state = reactive({
  loading: false,
  settings: {},
  template: {},
  environments: [],
  agentTokenMasked: '',
  revealedToken: '',
})

const activeTab = ref('environments')
const copied = ref(false)
const applyFrom = ref('')
const syncing = ref(false)

const TABS = ['Environments', 'Thresholds', 'Issues', 'Alerts', 'Storage', 'Danger Zone']
const tabKey = (t) => t.toLowerCase().replace(/\s+/g, '-')

const appName = computed(() => state.settings.name ?? app.current?.name ?? 'App')
const appIdValue = computed(() => state.settings.app_id ?? appId.value)

// Other apps (for "Apply a template") — everything but the current one.
const otherApps = computed(() => (app.apps ?? []).filter((a) => a.app_id !== appId.value))

const alertChannels = reactive({ list: [] })

async function load() {
  if (!appId.value) return
  state.loading = true
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/settings`)
    const s = data.settings ?? data
    state.settings = s
    state.template = s.template ?? data.template ?? {}
    state.agentTokenMasked = s.agent_token_masked ?? s.agent_token ?? data.agent_token ?? ''
    state.environments = normalizeEnvs(s.environments ?? data.environments)
  } catch {
    state.settings = {}
    state.template = {}
    state.environments = []
  } finally {
    state.loading = false
  }
}

// environments may arrive as { name: color } or [{ name, color }].
function normalizeEnvs(envs) {
  if (!envs) return []
  if (Array.isArray(envs)) return envs.map((e) => ({ name: e.name, color: e.color ?? '#6b7280' }))
  return Object.entries(envs).map(([name, color]) => ({ name, color }))
}

async function loadAlertChannels() {
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/alert-channels`)
    alertChannels.list = data.data ?? data ?? []
  } catch {
    alertChannels.list = []
  }
}

async function copyAppId() {
  try {
    await navigator.clipboard.writeText(appIdValue.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch { /* ignore */ }
}

async function syncTemplate() {
  syncing.value = true
  try {
    const { data } = await api.post(`/api/apps/${appId.value}/templates/sync`)
    if (data?.template) state.template = data.template
  } catch { /* demo no-op */ } finally {
    syncing.value = false
  }
}

async function applyTemplate() {
  if (!applyFrom.value) return
  try {
    await api.post(`/api/apps/${appId.value}/templates/apply`, { from_app_id: applyFrom.value })
    await load()
  } catch { /* demo no-op */ }
}

async function saveEnvColor(env) {
  try {
    await api.put(`/api/apps/${appId.value}/environments/${env.name}`, { color: env.color })
  } catch { /* demo no-op */ }
}

async function regenerateToken() {
  try {
    const { data } = await api.post(`/api/apps/${appId.value}/token/regenerate`)
    state.revealedToken = data?.agent_token ?? data?.token ?? ''
  } catch { /* demo no-op */ }
}

watch(activeTab, (tab) => {
  if (tab === 'alerts' && !alertChannels.list.length) loadAlertChannels()
})

watch(appId, load, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ appName }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Per-app configuration and integrations.</p>
      </div>
      <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
        Edit app
      </button>
    </div>

    <!-- Read-only banner -->
    <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
      Read-only demo — explore every setting; changes are disabled.
    </div>

    <!-- App ID -->
    <StatPanel title="App ID">
      <div class="flex items-center gap-2">
        <code class="flex-1 truncate rounded bg-gray-50 px-2 py-1 font-mono text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ appIdValue }}</code>
        <button
          type="button"
          class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
          @click="copyAppId"
        >{{ copied ? 'Copied!' : 'Copy' }}</button>
      </div>
    </StatPanel>

    <!-- Onboarding template -->
    <StatPanel title="Onboarding template">
      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
          <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">This app's template</h4>
          <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ state.template.name ?? 'No template' }}</p>
          <p v-if="state.template.synced_at" class="text-xs text-gray-400 dark:text-gray-500">Last synced {{ relativeTime(state.template.synced_at) }}</p>
          <button
            type="button"
            class="mt-2 rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            :disabled="syncing"
            @click="syncTemplate"
          >{{ syncing ? 'Syncing…' : 'Sync now' }}</button>
        </div>

        <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
          <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Apply a template</h4>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Clones another app's setup onto this one. Secrets are never copied.</p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <select
              v-model="applyFrom"
              class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
              <option value="">Select an app…</option>
              <option v-for="a in otherApps" :key="a.app_id" :value="a.app_id">{{ a.name }}</option>
            </select>
            <button
              type="button"
              class="rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="!applyFrom"
              @click="applyTemplate"
            >Apply &amp; override</button>
          </div>
        </div>
      </div>
    </StatPanel>

    <!-- Sub-nav tabs -->
    <div>
      <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
        <button
          v-for="tab in TABS"
          :key="tab"
          type="button"
          class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
          :class="
            activeTab === tabKey(tab)
              ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
          "
          @click="activeTab = tabKey(tab)"
        >{{ tab }}</button>
      </div>

      <div class="pt-4">
        <!-- Environments -->
        <div v-if="activeTab === 'environments'" class="space-y-4">
          <StatPanel title="Detected Environments">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
              <li v-if="!state.environments.length" class="py-2 text-sm text-gray-400 dark:text-gray-500">No environments detected.</li>
              <li v-for="env in state.environments" :key="env.name" class="flex items-center justify-between gap-3 py-2">
                <span class="flex items-center gap-2">
                  <span class="inline-block h-4 w-4 rounded-full border border-gray-200 dark:border-gray-700" :style="{ backgroundColor: env.color }" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ env.name }}</span>
                </span>
                <input
                  v-model="env.color"
                  type="color"
                  class="h-7 w-10 cursor-pointer rounded border border-gray-300 dark:border-gray-700"
                  @change="saveEnvColor(env)"
                />
              </li>
            </ul>
          </StatPanel>

          <StatPanel title="Agent Token">
            <p class="text-xs text-gray-500 dark:text-gray-400">The <code class="font-mono">NIGHTOWL_TOKEN</code> the agent uses to authenticate ingest for this app.</p>
            <div class="mt-2 flex items-center gap-2">
              <code class="flex-1 truncate rounded bg-gray-50 px-2 py-1 font-mono text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                {{ state.revealedToken || state.agentTokenMasked || '—' }}
              </code>
              <button
                type="button"
                class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="regenerateToken"
              >Regenerate Token</button>
            </div>
            <p v-if="state.revealedToken" class="mt-2 text-xs text-amber-600 dark:text-amber-400">Copy this token now — it won't be shown again.</p>
          </StatPanel>
        </div>

        <!-- Alerts -->
        <StatPanel v-else-if="activeTab === 'alerts'" title="Alert Channels">
          <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            <li v-if="!alertChannels.list.length" class="py-2 text-sm text-gray-400 dark:text-gray-500">No alert channels configured.</li>
            <li v-for="ch in alertChannels.list" :key="ch.id" class="flex items-center justify-between py-2 text-sm">
              <span class="text-gray-700 dark:text-gray-300">{{ ch.name ?? ch.type }}</span>
              <span class="text-xs text-gray-500 dark:text-gray-400">{{ ch.type }}</span>
            </li>
          </ul>
        </StatPanel>

        <!-- Danger Zone -->
        <StatPanel v-else-if="activeTab === 'danger-zone'" title="Danger Zone">
          <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Destructive actions are disabled in this read-only demo.</p>
          <div class="flex flex-wrap gap-2">
            <button type="button" disabled class="cursor-not-allowed rounded border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 opacity-50 dark:border-red-500/40 dark:text-red-400">Transfer app</button>
            <button type="button" disabled class="cursor-not-allowed rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white opacity-50">Delete app</button>
          </div>
        </StatPanel>

        <!-- Other tabs (lighter) -->
        <StatPanel v-else :title="TABS.find((t) => tabKey(t) === activeTab)">
          <p class="text-sm text-gray-500 dark:text-gray-400">Configuration for this section is managed here. Changes are disabled in the read-only demo.</p>
          <dl v-if="Object.keys(state.settings).length" class="mt-3 space-y-1 text-sm">
            <div v-for="(v, k) in state.settings" :key="k" class="flex justify-between gap-4">
              <dt class="text-gray-500 dark:text-gray-400">{{ k }}</dt>
              <dd class="truncate text-gray-700 dark:text-gray-300">{{ typeof v === 'object' ? '…' : v }}</dd>
            </div>
          </dl>
        </StatPanel>
      </div>
    </div>
  </div>
</template>
