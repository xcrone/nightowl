<script setup>
import { reactive, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOrgStore } from '../store/org'
import api from '../services/api'
import StatPanel from '../components/StatPanel.vue'

// Org details (rename/change contact email) + membership management — split
// out from OrgDashboard.vue (which owns team/app management) since this is
// used far less often than adding/editing teams and apps, and was taking up
// a lot of space on the main "Your Apps" screen.
const org = useOrgStore()
const router = useRouter()

const ui = reactive({ loading: true })

onMounted(async () => {
  try {
    if (!org.org) await org.fetchOrg().catch(() => {})
    await fetchMembers().catch(() => {})
  } finally {
    ui.loading = false
  }
})

function backToDashboard() {
  router.push('/')
}

// --- Org details (name / account_email) ---------------------------------
const orgForm = reactive({ name: '', account_email: '' })
const editingOrg = ref(false)
const savingOrg = ref(false)
const orgError = ref('')

watch(
  () => org.org,
  (value) => {
    if (value && !editingOrg.value) {
      orgForm.name = value.name ?? ''
      orgForm.account_email = value.account_email ?? ''
    }
  },
  { immediate: true },
)

function startEditOrg() {
  orgError.value = ''
  editingOrg.value = true
}

function cancelEditOrg() {
  editingOrg.value = false
  orgForm.name = org.org?.name ?? ''
  orgForm.account_email = org.org?.account_email ?? ''
}

async function saveOrg() {
  if (!org.org?.uuid) return
  savingOrg.value = true
  orgError.value = ''
  try {
    const { data } = await api.put(`/api/orgs/${org.org.uuid}`, {
      name: orgForm.name,
      account_email: orgForm.account_email,
    })
    org.setOrgDetails(data)
    editingOrg.value = false
  } catch (e) {
    orgError.value = e?.response?.data?.message ?? 'Could not save organization details.'
  } finally {
    savingOrg.value = false
  }
}

// --- Members --------------------------------------------------------------
const members = ref([])
const newMemberEmail = ref('')
const memberError = ref('')
const addingMember = ref(false)

async function fetchMembers() {
  if (!org.org?.uuid) return
  const { data } = await api.get(`/api/orgs/${org.org.uuid}/members`)
  members.value = data.data
}

async function addMember() {
  if (!org.org?.uuid || !newMemberEmail.value.trim()) return
  memberError.value = ''
  addingMember.value = true
  try {
    const { data } = await api.post(`/api/orgs/${org.org.uuid}/members`, {
      email: newMemberEmail.value.trim(),
    })
    members.value.push(data)
    newMemberEmail.value = ''
  } catch (e) {
    memberError.value =
      e?.response?.data?.errors?.email?.[0] ??
      e?.response?.data?.message ??
      "That email hasn't signed up for NightOwl yet."
  } finally {
    addingMember.value = false
  }
}

async function removeMember(member) {
  if (!org.org?.uuid) return
  if (!window.confirm(`Remove ${member.email} from this organization?`)) return
  try {
    await api.delete(`/api/orgs/${org.org.uuid}/members/${member.uuid}`)
    members.value = members.value.filter((m) => m.uuid !== member.uuid)
  } catch {
    /* best-effort — member stays listed if the request failed */
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto max-w-2xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">Organization</h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">Organization details and members.</p>
        </div>
        <button
          type="button"
          data-test="back-to-dashboard"
          class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          @click="backToDashboard"
        >
          Back to dashboard
        </button>
      </div>

      <!-- No-org empty state -->
      <div
        v-if="!ui.loading && !org.org"
        class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center dark:border-gray-700"
      >
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">You don't have an organization yet.</p>
        <router-link
          to="/"
          data-test="no-org-back-link"
          class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
        >
          Go back to create one
        </router-link>
      </div>

      <template v-else-if="!ui.loading && org.org">
      <!-- Org details -->
      <StatPanel title="Organization details">
        <template #actions>
          <button
            v-if="!editingOrg"
            type="button"
            data-test="edit-org"
            class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
            @click="startEditOrg"
          >
            Edit
          </button>
        </template>

        <div v-if="!editingOrg" class="space-y-1 text-sm">
          <p><span class="text-gray-500 dark:text-gray-400">Name:</span> {{ org.org?.name ?? '—' }}</p>
          <p><span class="text-gray-500 dark:text-gray-400">Account email:</span> {{ org.org?.account_email ?? '—' }}</p>
        </div>
        <div v-else class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Name</label>
            <input
              v-model="orgForm.name"
              type="text"
              data-test="org-name-input"
              class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Account email</label>
            <input
              v-model="orgForm.account_email"
              type="email"
              data-test="org-email-input"
              class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
          </div>
          <p v-if="orgError" class="text-xs text-red-600 dark:text-red-400">{{ orgError }}</p>
          <div class="flex gap-2">
            <button
              type="button"
              data-test="save-org"
              class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              :disabled="savingOrg"
              @click="saveOrg"
            >
              {{ savingOrg ? 'Saving…' : 'Save' }}
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="cancelEditOrg"
            >
              Cancel
            </button>
          </div>
        </div>
      </StatPanel>

      <!-- Members -->
      <StatPanel title="Members">
        <ul class="mb-3 divide-y divide-gray-100 dark:divide-gray-800">
          <li v-if="!members.length" class="py-2 text-sm text-gray-400 dark:text-gray-500">No members yet.</li>
          <li v-for="member in members" :key="member.uuid" class="flex items-center justify-between gap-3 py-2 text-sm">
            <span>
              <span class="font-medium text-gray-900 dark:text-gray-100">{{ member.name }}</span>
              <span class="ml-2 text-gray-500 dark:text-gray-400">{{ member.email }}</span>
            </span>
            <button
              type="button"
              class="rounded border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500/40 dark:text-red-400 dark:hover:bg-red-500/10"
              @click="removeMember(member)"
            >
              Remove
            </button>
          </li>
        </ul>

        <div class="flex flex-wrap items-start gap-2">
          <div class="flex-1">
            <input
              v-model="newMemberEmail"
              type="email"
              data-test="new-member-email"
              placeholder="teammate@example.com"
              class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
            <p v-if="memberError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ memberError }}</p>
          </div>
          <button
            type="button"
            data-test="add-member"
            class="rounded bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            :disabled="addingMember"
            @click="addMember"
          >
            {{ addingMember ? 'Adding…' : 'Add member' }}
          </button>
        </div>
      </StatPanel>
      </template>
    </div>
  </div>
</template>
