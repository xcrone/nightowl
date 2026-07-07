<script setup>
import { reactive, computed, onMounted, watch, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOrgStore } from '../store/org'
import { useAuthStore } from '../store/auth'
import api from '../services/api'
import StatusDot from '../components/StatusDot.vue'
import Modal from '../components/Modal.vue'
import AppFormModal from '../components/AppFormModal.vue'
import { relativeTime } from '../utils/format'
import { BADGE } from '../resourceConfig'

// The landing page ("Your Apps") — also where team/app management lives
// (add/rename/delete team, add/edit/delete app). Org details and member
// management are a separate, less-frequently-used page (OrganizationPage.vue,
// linked via the gear icon below) so they don't crowd this screen. Per-app
// pages (/dashboard/:appId/*) only ever show that one app's own data, never
// org/team management.
const org = useOrgStore()
const auth = useAuthStore()
const router = useRouter()

const ui = reactive({ search: '', view: 'teams', loading: true })

onMounted(async () => {
  await Promise.allSettled([org.fetchOrgs(), org.fetchOrg()])
  ui.loading = false
})

async function onSwitchOrg(event) {
  await org.switchOrg(event.target.value).catch(() => {})
}

const query = computed(() => ui.search.trim().toLowerCase())
// Whether the user has actually typed a search — distinguishes a genuine
// "you have nothing yet" empty state from a "your search matched nothing"
// empty state (they read very differently).
const hasSearch = computed(() => query.value.length > 0)

// Teams (and their apps) filtered by the search box. A team matches if its own
// name matches, otherwise only its matching apps are kept.
const filteredTeams = computed(() => {
  const q = query.value
  if (!q) return org.teams
  return org.teams
    .map((team) => {
      if (team.name?.toLowerCase().includes(q)) return team
      const apps = (team.apps ?? []).filter((a) => a.name?.toLowerCase().includes(q))
      return apps.length ? { ...team, apps } : null
    })
    .filter(Boolean)
})

const flatApps = computed(() => filteredTeams.value.flatMap((t) => t.apps ?? []))

// Minimum requests in the 1h health window before the "% err" badge is
// allowed to alarm (yellow/red) — mirrors
// ListApps::MIN_SAMPLE_FOR_ERROR_BADGE. Below this, error_rate (a 5xx-only
// rate, see the api) is too noisy a sample to color-code — e.g. a single
// request that happens to error would otherwise read as "100% err" for an
// app that's actually healthy.
const MIN_SAMPLE_FOR_ERROR_BADGE = 20

function errorRateBadge(appItem) {
  if (Number(appItem.request_count ?? 0) < MIN_SAMPLE_FOR_ERROR_BADGE) return BADGE.gray
  const n = Number(appItem.error_rate ?? 0)
  if (n >= 5) return BADGE.red
  if (n >= 1) return BADGE.yellow
  return BADGE.green
}

function openApp(appId) {
  router.push(`/dashboard/${appId}`)
}

// Distinct from the per-app "Agent Health" page (which simulates ingest-pipeline
// health, always "healthy" — see ShowAgentHealth's docblock). This label reflects
// whether the app has sent any real telemetry recently, so the wording is kept
// deliberately different ("telemetry" vs. "Agent Health") to avoid reading as a
// contradiction of that page.
function telemetryLabel(appItem) {
  return appItem.monitoring === 'connected' ? 'telemetry: active' : 'telemetry: no recent data'
}

async function signOut() {
  await auth.logout()
  router.push('/login')
}

// --- Teams (rename / delete) -----------------------------------------------
// Per-team UI state (editing/saving/error), keyed by uuid so re-renders
// after a store update don't clobber an in-progress edit.
const teamUi = reactive({})

watch(
  () => org.teams,
  (teams) => {
    for (const team of teams ?? []) {
      if (!teamUi[team.uuid]) {
        teamUi[team.uuid] = { editing: false, name: team.name, saving: false, deleteError: '', appError: '' }
      } else if (!teamUi[team.uuid].editing) {
        teamUi[team.uuid].name = team.name
      }
    }
  },
  { immediate: true, deep: true },
)

function startEditTeam(team) {
  teamUi[team.uuid].deleteError = ''
  teamUi[team.uuid].editing = true
}

function cancelEditTeam(team) {
  teamUi[team.uuid].editing = false
  teamUi[team.uuid].name = team.name
}

async function saveTeam(team) {
  const uiState = teamUi[team.uuid]
  if (!org.org?.uuid) {
    uiState.deleteError = 'No organization selected.'
    return
  }
  if (!uiState.name.trim()) return
  uiState.saving = true
  try {
    const { data } = await api.put(`/api/orgs/${org.org.uuid}/teams/${team.uuid}`, { name: uiState.name.trim() })
    org.upsertTeam(data)
    uiState.editing = false
  } catch (e) {
    uiState.deleteError = e?.response?.data?.message ?? 'Could not rename team.'
  } finally {
    uiState.saving = false
  }
}

async function deleteTeam(team) {
  const uiState = teamUi[team.uuid]
  uiState.deleteError = ''
  if (!org.org?.uuid) {
    uiState.deleteError = 'No organization selected.'
    return
  }
  if (!window.confirm(`Delete team "${team.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/api/orgs/${org.org.uuid}/teams/${team.uuid}`)
    org.removeTeam(team.uuid)
  } catch (e) {
    uiState.deleteError = e?.response?.data?.message ?? 'Could not delete team.'
  }
}

// --- Add team modal ---------------------------------------------------
const teamModal = reactive({ open: false, name: '', error: '', saving: false })

function openAddTeamModal() {
  teamModal.open = true
  teamModal.name = ''
  teamModal.error = ''
}

async function submitAddTeam() {
  if (!org.org?.uuid) {
    teamModal.error = 'No organization selected.'
    return
  }
  if (!teamModal.name.trim()) {
    teamModal.error = 'Team name is required.'
    return
  }
  teamModal.saving = true
  teamModal.error = ''
  try {
    const { data } = await api.post(`/api/orgs/${org.org.uuid}/teams`, { name: teamModal.name.trim() })
    org.upsertTeam(data)
    teamModal.open = false
  } catch (e) {
    teamModal.error = e?.response?.data?.message ?? 'Could not add team.'
  } finally {
    teamModal.saving = false
  }
}

// --- Create organization modal (zero-org empty state) ---------------------
const createOrgModal = reactive({ open: false, name: '', account_email: '', error: '', saving: false })

function openCreateOrgModal() {
  createOrgModal.open = true
  createOrgModal.name = ''
  createOrgModal.account_email = ''
  createOrgModal.error = ''
}

async function submitCreateOrg() {
  if (!createOrgModal.name.trim()) {
    createOrgModal.error = 'Name is required.'
    return
  }
  if (!createOrgModal.account_email.trim()) {
    createOrgModal.error = 'Account email is required.'
    return
  }
  createOrgModal.saving = true
  createOrgModal.error = ''
  try {
    await org.createOrg({ name: createOrgModal.name.trim(), account_email: createOrgModal.account_email.trim() })
    createOrgModal.open = false
  } catch (e) {
    createOrgModal.error = e?.response?.data?.message ?? 'Could not create organization.'
  } finally {
    createOrgModal.saving = false
  }
}

// --- Add/edit app modal --------------------------------------------------
// Both flows are the single shared AppFormModal component (also used by
// SettingsPage.vue's "Edit app" button) so there's one implementation of
// "create/edit an app", not two.
const appFormModal = ref(null)

function openAddAppModal(team) {
  appFormModal.value.openCreate(team)
}

function openEditAppModal(team, appItem) {
  appFormModal.value.openEdit(appItem, team)
}

function onAppSaved({ team, app }) {
  if (team) org.upsertApp(team.uuid, app)
}

async function deleteApp(team, appItem) {
  if (!window.confirm(`Delete app "${appItem.name}"? This cannot be undone.`)) return
  try {
    await api.delete(`/api/apps/${appItem.app_id}`)
    org.removeApp(team.uuid, appItem.app_id)
  } catch (e) {
    teamUi[team.uuid].appError = e?.response?.data?.message ?? 'Could not delete app.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto max-w-6xl space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500 text-lg font-bold text-white">🦉</span>
          <div>
            <h1 class="text-xl font-semibold">Your Apps</h1>
            <p v-if="org.orgs.length <= 1" class="text-sm text-gray-500 dark:text-gray-400">
              Welcome back, {{ auth.user?.name ?? '…' }}
            </p>
            <select
              v-else
              data-test="org-switcher"
              :value="org.currentOrgUuid ?? org.org?.uuid"
              aria-label="Switch organization"
              class="mt-0.5 rounded border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              @change="onSwitchOrg"
            >
              <option v-for="o in org.orgs" :key="o.uuid" :value="o.uuid">{{ o.name }}</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <router-link
            to="/organization"
            aria-label="Organization settings"
            title="Organization settings"
            class="flex items-center gap-2 rounded border border-gray-300 px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
          >
            <span class="hidden sm:inline">Organization settings</span>
            <span aria-hidden="true">⚙</span>
          </router-link>
          <button
            type="button"
            aria-label="Log out"
            class="flex items-center gap-2 rounded border border-gray-300 px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-red-600 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-red-400"
            :title="`Log out ${auth.user?.email ?? ''}`.trim()"
            @click="signOut"
          >
            <span class="hidden sm:inline">{{ auth.user?.email ?? 'Account' }}</span>
            <span aria-hidden="true">⎋</span>
          </button>
        </div>
      </div>

      <!-- Toolbar -->
      <div v-if="!ui.loading && org.org" class="flex flex-wrap items-center gap-3">
        <input
          v-model="ui.search"
          type="text"
          placeholder="Search clients and apps"
          class="min-w-64 flex-1 rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <div class="inline-flex items-center rounded-md border border-gray-200 bg-white p-0.5 dark:border-gray-700 dark:bg-gray-800">
          <button
            v-for="opt in [{ value: 'teams', label: 'Teams' }, { value: 'apps', label: 'Apps' }]"
            :key="opt.value"
            type="button"
            class="rounded px-3 py-1 text-sm font-medium transition-colors"
            :class="
              ui.view === opt.value
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400'
                : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
            "
            @click="ui.view = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
        <button
          type="button"
          data-test="add-team"
          class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
          @click="openAddTeamModal"
        >
          + Add team
        </button>
      </div>

      <!-- Teams view -->
      <div v-if="!ui.loading && org.org && ui.view === 'teams'" class="space-y-8">
        <section v-for="team in filteredTeams" :key="team.id">
          <div class="mb-3 flex items-center justify-between gap-3">
            <div v-if="!teamUi[team.uuid]?.editing" class="flex items-center gap-2">
              <span class="text-gray-400">👥</span>
              <h2 class="text-base font-semibold">{{ team.name }}</h2>
              <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                {{ team.apps_count ?? team.apps?.length ?? 0 }}
              </span>
            </div>
            <div v-else class="flex flex-1 items-center gap-2">
              <input
                v-model="teamUi[team.uuid].name"
                type="text"
                class="max-w-xs rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <button
                type="button"
                class="rounded bg-primary-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                :disabled="teamUi[team.uuid].saving"
                @click="saveTeam(team)"
              >
                Save
              </button>
              <button
                type="button"
                class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="cancelEditTeam(team)"
              >
                Cancel
              </button>
            </div>

            <div class="flex shrink-0 items-center gap-2">
              <button
                type="button"
                data-test="add-app"
                class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="openAddAppModal(team)"
              >
                + Add app
              </button>
              <button
                v-if="!teamUi[team.uuid]?.editing"
                type="button"
                class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                @click="startEditTeam(team)"
              >
                Rename
              </button>
              <button
                type="button"
                data-test="delete-team"
                class="rounded border border-red-300 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-500/10"
                @click="deleteTeam(team)"
              >
                Delete
              </button>
            </div>
          </div>

          <p v-if="teamUi[team.uuid]?.deleteError" class="mb-2 text-xs text-red-600 dark:text-red-400">
            {{ teamUi[team.uuid].deleteError }}
          </p>
          <p v-if="teamUi[team.uuid]?.appError" class="mb-2 text-xs text-red-600 dark:text-red-400">
            {{ teamUi[team.uuid].appError }}
          </p>

          <div class="rounded-xl border-2 border-dashed border-green-400/40 p-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div v-for="appItem in team.apps" :key="appItem.app_id" class="relative">
                <div class="absolute right-2 top-2 z-10 flex gap-1">
                  <button
                    type="button"
                    aria-label="Edit app"
                    class="rounded border border-gray-300 bg-white px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click.stop="openEditAppModal(team, appItem)"
                  >
                    ✎
                  </button>
                  <button
                    type="button"
                    aria-label="Delete app"
                    class="rounded border border-red-300 bg-white px-1.5 py-0.5 text-xs text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click.stop="deleteApp(team, appItem)"
                  >
                    🗑
                  </button>
                </div>
                <button type="button" class="w-full text-left" @click="openApp(appItem.app_id)">
                  <div
                    class="h-full rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900"
                  >
                    <div class="mb-2 flex items-start justify-between gap-2 pr-12">
                      <h3 class="truncate font-semibold">{{ appItem.name }}</h3>
                      <span
                        v-if="appItem.alerts > 0"
                        class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium"
                        :class="BADGE.yellow"
                        title="Active alerts"
                      >⚠ {{ appItem.alerts }}</span>
                    </div>
                    <p
                      class="mb-3 truncate text-xs text-gray-400 dark:text-gray-500"
                      :title="appItem.description || appItem.db_connection"
                    >
                      {{ appItem.description || appItem.db_connection }}
                    </p>
                    <div class="mb-3 flex flex-wrap gap-1.5">
                      <span
                        class="rounded px-2 py-0.5 text-xs font-medium"
                        :class="errorRateBadge(appItem)"
                        title="Server error rate (5xx), last 1h"
                      >
                        {{ Number(appItem.error_rate ?? 0).toFixed(2) }}% err (1h)
                      </span>
                      <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.count_5xx > 0 ? BADGE.red : BADGE.gray">
                        {{ appItem.count_5xx ?? 0 }} 5xx
                      </span>
                      <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.exceptions > 0 ? BADGE.yellow : BADGE.gray">
                        {{ appItem.exceptions ?? 0 }} exc
                      </span>
                      <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BADGE.gray">
                        {{ appItem.open_issues ?? 0 }} issues
                      </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                      <StatusDot :status="appItem.monitoring" />
                      <span>{{ telemetryLabel(appItem) }}</span>
                      <span class="ml-auto">{{ relativeTime(appItem.last_report_at) }}</span>
                    </div>
                  </div>
                </button>
              </div>
            </div>
            <p v-if="!team.apps?.length" class="text-sm text-gray-400 dark:text-gray-500">No apps in this team yet.</p>
          </div>
        </section>
        <p v-if="!filteredTeams.length" class="text-sm text-gray-500 dark:text-gray-400">
          {{ hasSearch ? 'No clients or apps match your search.' : "You don't have any teams yet." }}
        </p>
      </div>

      <!-- Apps (flat) view -->
      <div v-else-if="!ui.loading && org.org" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="appItem in flatApps"
          :key="appItem.app_id"
          type="button"
          class="text-left"
          @click="openApp(appItem.app_id)"
        >
          <div class="h-full rounded-lg border border-gray-200 bg-white p-4 transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-2 flex items-start justify-between gap-2">
              <h3 class="truncate font-semibold">{{ appItem.name }}</h3>
              <span v-if="appItem.alerts > 0" class="shrink-0 rounded px-1.5 py-0.5 text-xs font-medium" :class="BADGE.yellow">⚠ {{ appItem.alerts }}</span>
            </div>
            <p
              class="mb-3 truncate text-xs text-gray-400 dark:text-gray-500"
              :title="appItem.description || appItem.db_connection"
            >{{ appItem.description || appItem.db_connection }}</p>
            <div class="mb-3 flex flex-wrap gap-1.5">
              <span
                class="rounded px-2 py-0.5 text-xs font-medium"
                :class="errorRateBadge(appItem)"
                title="Server error rate (5xx), last 1h"
                >{{ Number(appItem.error_rate ?? 0).toFixed(2) }}% err (1h)</span
              >
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.count_5xx > 0 ? BADGE.red : BADGE.gray">{{ appItem.count_5xx ?? 0 }} 5xx</span>
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="appItem.exceptions > 0 ? BADGE.yellow : BADGE.gray">{{ appItem.exceptions ?? 0 }} exc</span>
              <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BADGE.gray">{{ appItem.open_issues ?? 0 }} issues</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
              <StatusDot :status="appItem.monitoring" />
              <span>{{ telemetryLabel(appItem) }}</span>
              <span class="ml-auto">{{ relativeTime(appItem.last_report_at) }}</span>
            </div>
          </div>
        </button>
        <p v-if="!flatApps.length" class="text-sm text-gray-500 dark:text-gray-400">
          {{ hasSearch ? 'No apps match your search.' : "You don't have any apps yet." }}
        </p>
      </div>

      <!-- Zero-org empty state -->
      <div
        v-if="!ui.loading && !org.org"
        class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center dark:border-gray-700"
      >
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
          You don't have an organization yet. Create one to start adding teams and apps.
        </p>
        <button
          type="button"
          data-test="create-org"
          class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
          @click="openCreateOrgModal"
        >
          Create organization
        </button>
      </div>
    </div>

    <!-- Add team modal -->
    <Modal v-if="teamModal.open" title="Add team" @close="teamModal.open = false">
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Team name</label>
          <input
            v-model="teamModal.name"
            type="text"
            data-test="team-modal-name"
            autofocus
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            @keyup.enter="submitAddTeam"
          />
        </div>
        <p v-if="teamModal.error" class="text-xs text-red-600 dark:text-red-400">{{ teamModal.error }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            @click="teamModal.open = false"
          >
            Cancel
          </button>
          <button
            type="button"
            data-test="team-modal-submit"
            class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="teamModal.saving"
            @click="submitAddTeam"
          >
            {{ teamModal.saving ? 'Adding…' : 'Add team' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Add/edit app modal -->
    <AppFormModal ref="appFormModal" @saved="onAppSaved" />

    <!-- Create organization modal -->
    <Modal v-if="createOrgModal.open" title="Create organization" @close="createOrgModal.open = false">
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
          <input
            v-model="createOrgModal.name"
            type="text"
            data-test="create-org-modal-name"
            autofocus
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Account email</label>
          <input
            v-model="createOrgModal.account_email"
            type="email"
            data-test="create-org-modal-email"
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
        <p v-if="createOrgModal.error" class="text-xs text-red-600 dark:text-red-400">{{ createOrgModal.error }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            @click="createOrgModal.open = false"
          >
            Cancel
          </button>
          <button
            type="button"
            data-test="create-org-modal-submit"
            class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="createOrgModal.saving"
            @click="submitCreateOrg"
          >
            {{ createOrgModal.saving ? 'Creating…' : 'Create organization' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>
