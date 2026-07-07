<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { relativeTime, formatBytes } from '../../utils/format'
import StatPanel from '../../components/StatPanel.vue'
import AppFormModal from '../../components/AppFormModal.vue'
import Modal from '../../components/Modal.vue'

// Per-app configuration hub: App ID, onboarding template (sync/apply),
// and tabbed sections (Environments default). Thresholds/Issues/Environments
// writes persist for real; only Danger Zone's destructive actions (transfer/
// delete the app) are disabled — see that tab's own notice.
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

// Resource types that support a response-time (duration) threshold. Each maps to
// a settings key `threshold.<slug>.duration_ms` persisted via PUT settings/{key}.
const RESOURCE_TYPES = [
  { label: 'Routes', slug: 'requests' },
  { label: 'Jobs', slug: 'jobs' },
  { label: 'Commands', slug: 'commands' },
  { label: 'Scheduled Tasks', slug: 'schedule' },
  { label: 'Queries', slug: 'queries' },
  { label: 'Outgoing Requests', slug: 'outgoing_requests' },
  { label: 'Mail', slug: 'mail' },
  { label: 'Notifications', slug: 'notifications' },
  { label: 'Cache', slug: 'cache' },
]
const thresholdKey = (slug) => `threshold.${slug}.duration_ms`
const DEFAULT_THRESHOLD_MS = 1000

// slug -> { value: string, active: boolean }
const thresholds = reactive({})

// slug -> save error string (cleared on each attempt).
const thresholdErrors = reactive({})

// slug -> brief "Saved!" confirmation flag, cleared after 2s (same ephemeral
// pattern as `copied` above).
const thresholdSaved = reactive({})

const AUTO_RESOLVE_OPTIONS = [3, 7, 14, 30, 60, 90]
const AUTO_RESOLVE_KEY = 'issues.auto_resolve_days'
const autoResolveDays = ref('14')
const issuesSaving = ref(false)
const issuesError = ref('')

const storage = reactive({ loading: false, loaded: false, tables: [], totalBytes: 0 })
const formatRows = (rows) => Number(rows ?? 0).toLocaleString('en-US')

const appName = computed(() => state.settings.name ?? app.current?.name ?? 'App')
const appIdValue = computed(() => state.settings.app_id ?? appId.value)

// Other apps (for "Apply a template") — everything but the current one.
const otherApps = computed(() => (app.apps ?? []).filter((a) => a.app_id !== appId.value))

const alertChannels = reactive({ list: [] })

const ALERT_CHANNEL_TYPES = [
  { label: 'Slack', value: 'slack' },
  { label: 'Discord', value: 'discord' },
  { label: 'Webhook', value: 'webhook' },
  { label: 'Email', value: 'email' },
]

// Add-channel modal — single-use here, so kept inline rather than extracted
// (see AppFormModal.vue for the shared shape this mirrors: a reactive state
// object with open/error/saving + form fields, and an imperative submit()).
const alertChannelModal = reactive({
  open: false,
  saving: false,
  error: '',
  name: '',
  type: 'slack',
  webhook_url: '',
  url: '',
  secret: '',
  recipients: '',
})

function openAlertChannelModal() {
  alertChannelModal.open = true
  alertChannelModal.saving = false
  alertChannelModal.error = ''
  alertChannelModal.name = ''
  alertChannelModal.type = 'slack'
  alertChannelModal.webhook_url = ''
  alertChannelModal.url = ''
  alertChannelModal.secret = ''
  alertChannelModal.recipients = ''
}

function closeAlertChannelModal() {
  alertChannelModal.open = false
}

async function submitAlertChannel() {
  const name = alertChannelModal.name.trim()
  if (!name) {
    alertChannelModal.error = 'Name is required.'
    return
  }

  const type = alertChannelModal.type
  let config = {}
  if (type === 'slack' || type === 'discord') {
    const webhookUrl = alertChannelModal.webhook_url.trim()
    if (!webhookUrl) {
      alertChannelModal.error = 'Webhook URL is required.'
      return
    }
    config = { webhook_url: webhookUrl }
  } else if (type === 'webhook') {
    const url = alertChannelModal.url.trim()
    if (!url) {
      alertChannelModal.error = 'URL is required.'
      return
    }
    config = { url }
    const secret = alertChannelModal.secret.trim()
    if (secret) config.secret = secret
  } else if (type === 'email') {
    const recipients = alertChannelModal.recipients
      .split(',')
      .map((r) => r.trim())
      .filter(Boolean)
    if (!recipients.length) {
      alertChannelModal.error = 'At least one recipient is required.'
      return
    }
    config = { recipients }
  }

  alertChannelModal.error = ''
  alertChannelModal.saving = true
  try {
    await api.post(`/api/apps/${appId.value}/alert-channels`, { name, type, config })
    closeAlertChannelModal()
    await loadAlertChannels()
  } catch (e) {
    alertChannelModal.error = e?.response?.data?.message ?? 'Could not save alert channel.'
  } finally {
    alertChannelModal.saving = false
  }
}

// "Edit app" (header, top-right) — the same shared AppFormModal used by
// OrgDashboard.vue's ✎ icon, not a separate re-implementation. Prefilled
// from `app.current` (the full app record already loaded by AppShell),
// since /settings only carries a subset of app fields (no db_connection).
const appFormModal = ref(null)

function openEditApp() {
  appFormModal.value.openEdit(app.current)
}

async function onAppSaved({ app: updatedApp }) {
  app.patchApp(updatedApp)
  await load()
}

async function load() {
  if (!appId.value) return
  state.loading = true
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/settings`)
    const s = data.settings ?? data
    state.settings = s
    state.template = s.template ?? data.template ?? {}
    state.agentTokenMasked = s.agent_token ?? data.agent_token ?? ''
    state.environments = normalizeEnvs(s.environments ?? data.environments)
    hydrateThresholds(s)
    autoResolveDays.value = String(s[AUTO_RESOLVE_KEY] ?? 14)
  } catch {
    state.settings = {}
    state.template = {}
    state.environments = []
    hydrateThresholds({})
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

// Populate the threshold rows from the flat settings map: a key present ⇒ an
// active (already-configured) row, otherwise an "Add threshold" affordance.
function hydrateThresholds(s) {
  for (const t of RESOURCE_TYPES) {
    const existing = s?.[thresholdKey(t.slug)]
    thresholds[t.slug] = {
      value: existing != null ? String(existing) : '',
      active: existing != null,
    }
  }
}

function addThreshold(slug) {
  thresholds[slug].active = true
  if (!thresholds[slug].value) thresholds[slug].value = String(DEFAULT_THRESHOLD_MS)
}

async function saveThreshold(slug) {
  const value = Number(thresholds[slug].value)
  if (!Number.isFinite(value)) return
  thresholdErrors[slug] = ''
  // UpdateAppSetting requires `value` to be a string (is_string()).
  try {
    await api.put(`/api/apps/${appId.value}/settings/${thresholdKey(slug)}`, { value: String(value) })
    thresholdSaved[slug] = true
    setTimeout(() => { thresholdSaved[slug] = false }, 2000)
  } catch (e) {
    thresholdErrors[slug] = e?.response?.data?.message ?? 'Could not save threshold.'
  }
}

async function saveIssues() {
  issuesSaving.value = true
  issuesError.value = ''
  try {
    await api.put(`/api/apps/${appId.value}/settings/${AUTO_RESOLVE_KEY}`, { value: String(Number(autoResolveDays.value)) })
  } catch (e) {
    issuesError.value = e?.response?.data?.message ?? 'Could not save the auto-resolve window.'
  } finally {
    issuesSaving.value = false
  }
}

async function loadStorage() {
  storage.loading = true
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/settings/storage`)
    storage.tables = data.tables ?? []
    storage.totalBytes = data.total_bytes ?? 0
    storage.loaded = true
  } catch {
    storage.tables = []
    storage.totalBytes = 0
  } finally {
    storage.loading = false
  }
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
  if (tab === 'storage' && !storage.loaded) loadStorage()
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
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
        @click="openEditApp"
      >
        Edit app
      </button>
    </div>

    <AppFormModal ref="appFormModal" @saved="onAppSaved" />

    <Modal v-if="alertChannelModal.open" title="Add alert channel" @close="closeAlertChannelModal">
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
          <input
            v-model="alertChannelModal.name"
            type="text"
            data-test="alert-channel-modal-name"
            autofocus
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Type</label>
          <select
            v-model="alertChannelModal.type"
            data-test="alert-channel-modal-type"
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option v-for="t in ALERT_CHANNEL_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <div v-if="alertChannelModal.type === 'slack' || alertChannelModal.type === 'discord'">
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Webhook URL</label>
          <input
            v-model="alertChannelModal.webhook_url"
            type="text"
            data-test="alert-channel-modal-webhook-url"
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>

        <template v-else-if="alertChannelModal.type === 'webhook'">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">URL</label>
            <input
              v-model="alertChannelModal.url"
              type="text"
              data-test="alert-channel-modal-url"
              class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Secret (optional)</label>
            <input
              v-model="alertChannelModal.secret"
              type="text"
              data-test="alert-channel-modal-secret"
              class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
          </div>
        </template>

        <div v-else-if="alertChannelModal.type === 'email'">
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Recipients (comma-separated)</label>
          <input
            v-model="alertChannelModal.recipients"
            type="text"
            data-test="alert-channel-modal-recipients"
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>

        <p v-if="alertChannelModal.error" class="text-xs text-red-600 dark:text-red-400">{{ alertChannelModal.error }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            @click="closeAlertChannelModal"
          >Cancel</button>
          <button
            type="button"
            data-test="alert-channel-modal-submit"
            class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="alertChannelModal.saving"
            @click="submitAlertChannel"
          >{{ alertChannelModal.saving ? 'Saving…' : 'Add channel' }}</button>
        </div>
      </div>
    </Modal>

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

        <!-- Thresholds -->
        <StatPanel v-else-if="activeTab === 'thresholds'" title="Setting up thresholds">
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Events that run longer than the response-time threshold you set here trigger an
            automatic issue and notification. Add a threshold per resource type.
          </p>
          <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
            <li v-for="t in RESOURCE_TYPES" :key="t.slug" class="py-2">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ t.label }}</span>
                <div v-if="thresholds[t.slug]?.active" class="flex items-center gap-2">
                  <input
                    v-model="thresholds[t.slug].value"
                    type="number"
                    min="0"
                    step="50"
                    class="w-24 rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                  />
                  <span class="text-xs text-gray-400 dark:text-gray-500">ms</span>
                  <button
                    type="button"
                    class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                    @click="saveThreshold(t.slug)"
                  >Save</button>
                  <span v-if="thresholdSaved[t.slug]" class="text-xs text-green-600 dark:text-green-400">Saved!</span>
                </div>
                <button
                  v-else
                  type="button"
                  class="rounded border border-dashed border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                  @click="addThreshold(t.slug)"
                >Add threshold</button>
              </div>
              <p v-if="thresholdErrors[t.slug]" class="mt-1 text-right text-xs text-red-600 dark:text-red-400">{{ thresholdErrors[t.slug] }}</p>
            </li>
          </ul>
        </StatPanel>

        <!-- Issues -->
        <StatPanel v-else-if="activeTab === 'issues'" title="Auto-resolve issues">
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Automatically resolve an issue once it stops reoccurring within this window.
          </p>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <select
              v-model="autoResolveDays"
              class="rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
              <option v-for="d in AUTO_RESOLVE_OPTIONS" :key="d" :value="String(d)">{{ d }} days</option>
            </select>
            <button
              type="button"
              class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              :disabled="issuesSaving"
              @click="saveIssues"
            >{{ issuesSaving ? 'Saving…' : 'Save' }}</button>
          </div>
          <p v-if="issuesError" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ issuesError }}</p>
        </StatPanel>

        <!-- Storage -->
        <StatPanel v-else-if="activeTab === 'storage'" title="Storage Usage">
          <p v-if="storage.loading" class="text-sm text-gray-400 dark:text-gray-500">Reading storage footprint…</p>
          <template v-else>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Total telemetry footprint:
              <span class="font-medium">{{ formatBytes(storage.totalBytes) }}</span>
              across {{ storage.tables.length }} tables, including indexes.
            </p>
            <div v-if="storage.tables.length" class="mt-3 overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-400 dark:border-gray-700 dark:text-gray-500">
                    <th class="py-2 pr-4 font-medium">Table</th>
                    <th class="py-2 pr-4 text-right font-medium">Rows</th>
                    <th class="py-2 text-right font-medium">Size</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                  <tr v-for="t in storage.tables" :key="t.name">
                    <td class="py-2 pr-4 font-mono text-gray-700 dark:text-gray-300">{{ t.name }}</td>
                    <td class="py-2 pr-4 text-right text-gray-600 dark:text-gray-400">{{ formatRows(t.rows) }}</td>
                    <td class="py-2 text-right text-gray-700 dark:text-gray-300">{{ formatBytes(t.bytes) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="mt-2 text-sm text-gray-400 dark:text-gray-500">No storage data available.</p>
          </template>
        </StatPanel>

        <!-- Alerts -->
        <StatPanel v-else-if="activeTab === 'alerts'" title="Alert Channels">
          <div class="mb-3 flex justify-end">
            <button
              type="button"
              class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="openAlertChannelModal"
            >+ Add channel</button>
          </div>
          <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            <li v-if="!alertChannels.list.length" class="py-2 text-sm text-gray-400 dark:text-gray-500">No alert channels configured.</li>
            <li v-for="ch in alertChannels.list" :key="ch.uuid ?? ch.id" class="flex items-center justify-between py-2 text-sm">
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
      </div>
    </div>
  </div>
</template>
