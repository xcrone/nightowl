<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAppStore } from '../../store/app'
import { useAuthStore } from '../../store/auth'
import api from '../../services/api'
import { relativeTime } from '../../utils/format'
import { BADGE } from '../../resourceConfig'
import StatPanel from '../../components/StatPanel.vue'

// Full drill-down for one deduplicated issue: stack trace, occurrences,
// per-environment breakdown, and an activity/comment thread.
// GET /api/apps/{appId}/issues/{id}.
const route = useRoute()
const app = useAppStore()
const auth = useAuthStore()

const appId = computed(() => route.params.appId)
const issueId = computed(() => route.params.id)

const state = reactive({
  loading: false,
  notFound: false,
  issue: {},
  stackFrames: [],
  occurrences: [],
  occurrencesByEnv: [],
  activity: [],
  comments: [],
})

const stackOpen = ref(true)
const commentTab = ref('write')
const commentBody = ref('')
const copied = ref('')
const posting = ref(false)
const assigneeInput = ref('')

async function load() {
  if (!appId.value || !issueId.value) return
  state.loading = true
  state.notFound = false
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/issues/${issueId.value}`)
    state.issue = data.issue ?? {}
    state.stackFrames = data.stack_frames ?? []
    state.occurrences = data.occurrences ?? []
    state.occurrencesByEnv = data.occurrences_by_environment ?? []
    state.activity = data.activity ?? []
    assigneeInput.value = state.issue.assigned_to ?? ''
  } catch (e) {
    state.issue = {}
    state.stackFrames = []
    state.occurrences = []
    state.occurrencesByEnv = []
    state.activity = []
    if (e?.response?.status === 404) state.notFound = true
  } finally {
    state.loading = false
  }
  if (!state.notFound) loadComments()
}

async function loadComments() {
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/issues/${issueId.value}/comments`)
    state.comments = data.data ?? data.comments ?? []
  } catch {
    state.comments = []
  }
}

const handled = computed(() => Boolean(state.issue.handled))
const origin = computed(() => {
  if (!state.issue.file) return ''
  return state.issue.line ? `${state.issue.file}:${state.issue.line}` : state.issue.file
})

function markdown() {
  const i = state.issue
  const frames = state.stackFrames
    .map((f) => `- ${f.file}:${f.line}${f.function ? ` — ${f.function}` : ''}`)
    .join('\n')
  return [
    `# ${i.exception_class ?? 'Issue'}`,
    '',
    i.exception_message ?? '',
    '',
    `- **Status:** ${handled.value ? 'Handled' : 'Unhandled'}`,
    `- **Origin:** ${origin.value || '—'}`,
    `- **Laravel:** ${i.laravel_version ?? '—'} · **PHP:** ${i.php_version ?? '—'}`,
    `- **Occurrences:** ${i.occurrences_count ?? 0} · **Users:** ${i.users_count ?? 0}`,
    `- **First seen:** ${relativeTime(i.first_seen_at)} · **Last seen:** ${relativeTime(i.last_seen_at)}`,
    '',
    '## Stack trace',
    frames || '_No frames_',
  ].join('\n')
}

function aiPrompt() {
  const i = state.issue
  const frames = state.stackFrames
    .map((f) => `${f.file}:${f.line}${f.function ? ` (${f.function})` : ''}`)
    .join('\n')
  return [
    'You are helping debug a Laravel application exception. Explain the likely',
    'root cause and suggest a fix.',
    '',
    `Exception: ${i.exception_class ?? ''}`,
    `Message: ${i.exception_message ?? ''}`,
    `Origin: ${origin.value || 'unknown'}`,
    `Runtime: Laravel ${i.laravel_version ?? '?'}, PHP ${i.php_version ?? '?'}`,
    '',
    'Stack trace:',
    frames || '(none)',
  ].join('\n')
}

async function copy(kind) {
  const text = kind === 'markdown' ? markdown() : aiPrompt()
  try {
    await navigator.clipboard.writeText(text)
    copied.value = kind
    setTimeout(() => { if (copied.value === kind) copied.value = '' }, 2000)
  } catch {
    copied.value = ''
  }
}

async function submitComment() {
  if (!commentBody.value.trim() || posting.value) return
  posting.value = true
  try {
    await api.post(`/api/apps/${appId.value}/issues/${issueId.value}/comments`, { body: commentBody.value })
    commentBody.value = ''
    commentTab.value = 'write'
    await loadComments()
  } catch {
    // read-only demo backends may reject — keep the composed text
  } finally {
    posting.value = false
  }
}

async function statusAction(action) {
  try {
    await api.post(`/api/apps/${appId.value}/issues/${issueId.value}/${action}`)
    await load()
  } catch { /* demo no-op */ }
}

async function setPriority(priority) {
  try {
    await api.post(`/api/apps/${appId.value}/issues/${issueId.value}/priority`, { priority })
    state.issue.priority = priority
  } catch { /* demo no-op */ }
}

async function assign(assignedTo) {
  try {
    await api.post(`/api/apps/${appId.value}/issues/${issueId.value}/assign`, { assigned_to: assignedTo })
    state.issue.assigned_to = assignedTo
    assigneeInput.value = assignedTo ?? ''
  } catch { /* demo no-op */ }
}

function saveAssignee() {
  const value = assigneeInput.value.trim() || null
  if (value === (state.issue.assigned_to ?? null)) return
  assign(value)
}

function occurrenceUserLink(userId) {
  return `/dashboard/${appId.value}/users/${userId}`
}

watch(issueId, load, { immediate: true })
</script>

<template>
  <div v-if="state.notFound" class="flex flex-col items-center justify-center gap-3 py-24 text-center">
    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Issue not found</p>
    <p class="text-sm text-gray-500 dark:text-gray-400">It may have been deleted, or the link is incorrect.</p>
    <RouterLink
      :to="`/dashboard/${appId}/issues`"
      class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
    >Back to Issues</RouterLink>
  </div>
  <div v-else class="space-y-4">
    <!-- Title -->
    <div>
      <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ state.issue.exception_class ?? 'Issue' }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ state.issue.exception_message }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Main column -->
      <div class="space-y-4 lg:col-span-2">
        <!-- Badges + actions -->
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="rounded px-2 py-0.5 text-xs font-semibold uppercase"
            :class="handled ? BADGE.yellow : BADGE.red"
          >{{ handled ? 'Handled' : 'Unhandled' }}</span>

          <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="BADGE.primary">
            Laravel {{ state.issue.laravel_version ?? '—' }}
          </span>
          <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="BADGE.blue">
            PHP {{ state.issue.php_version ?? '—' }}
          </span>

          <div class="ml-auto flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="copy('markdown')"
            >{{ copied === 'markdown' ? 'Copied!' : 'Copy as Markdown' }}</button>
            <button
              type="button"
              class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              @click="copy('ai')"
            >{{ copied === 'ai' ? 'Copied!' : 'Copy AI prompt' }}</button>
          </div>
        </div>

        <!-- Origin -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
          <p class="font-mono text-sm font-semibold text-red-600 dark:text-red-400">{{ state.issue.exception_class }}</p>
          <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ state.issue.exception_message }}</p>
          <p v-if="origin" class="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ origin }}</p>

          <!-- Status actions -->
          <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
            <button type="button" class="rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700" @click="statusAction('resolve')">Resolve</button>
            <button type="button" class="rounded bg-gray-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-600" @click="statusAction('ignore')">Ignore</button>
            <button type="button" class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800" @click="statusAction('reopen')">Reopen</button>
            <select
              :value="state.issue.priority ?? ''"
              class="rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              @change="setPriority($event.target.value)"
            >
              <option value="">No priority</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>

        <!-- Stack Trace -->
        <StatPanel>
          <template #actions>
            <button type="button" class="text-primary-600 dark:text-primary-400" @click="stackOpen = !stackOpen">
              {{ stackOpen ? 'Collapse' : 'Expand' }}
            </button>
          </template>
          <div class="mb-2 flex items-center gap-2">
            <button type="button" class="text-gray-400 dark:text-gray-500" @click="stackOpen = !stackOpen">
              {{ stackOpen ? '▾' : '▸' }}
            </button>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Stack Trace</h3>
          </div>
          <ol v-if="stackOpen" class="space-y-1 font-mono text-xs">
            <li v-if="!state.stackFrames.length" class="text-gray-400 dark:text-gray-500">No stack frames.</li>
            <li v-for="(frame, i) in state.stackFrames" :key="i" class="flex flex-col border-l-2 border-gray-200 pl-2 dark:border-gray-700">
              <span class="text-gray-700 dark:text-gray-300">{{ frame.file }}:{{ frame.line }}</span>
              <span v-if="frame.function" class="text-gray-400 dark:text-gray-500">{{ frame.function }}</span>
            </li>
          </ol>
        </StatPanel>

        <!-- Occurrences -->
        <StatPanel :title="`${state.occurrences.length} occurrence${state.occurrences.length === 1 ? '' : 's'}`">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
              <thead>
                <tr>
                  <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
                  <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Source</th>
                  <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Message</th>
                  <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-if="!state.occurrences.length">
                  <td colspan="4" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">No occurrences.</td>
                </tr>
                <tr v-for="occ in state.occurrences" :key="occ.id">
                  <td class="whitespace-nowrap px-2 py-1.5 text-gray-500 dark:text-gray-400">{{ relativeTime(occ.created_at) }}</td>
                  <td class="px-2 py-1.5">
                    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="BADGE.gray">{{ occ.source_label ?? occ.source ?? '—' }}</span>
                  </td>
                  <td class="max-w-xs truncate px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ occ.message }}</td>
                  <td class="px-2 py-1.5">
                    <RouterLink
                      v-if="occ.user_id"
                      :to="occurrenceUserLink(occ.user_id)"
                      class="text-primary-600 hover:underline dark:text-primary-400"
                    >{{ occ.user_id }}</RouterLink>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </StatPanel>

        <!-- Activity -->
        <StatPanel title="Activity">
          <ul class="space-y-2 text-sm">
            <li v-for="a in state.activity" :key="a.id" class="flex items-baseline gap-2">
              <span class="text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ a.actor_name ?? a.actor_type ?? 'System' }}</span>
                {{ a.action }}
                <span v-if="a.new_value" class="text-gray-500 dark:text-gray-400">→ {{ a.new_value }}</span>
              </span>
              <span class="text-xs text-gray-400 dark:text-gray-500">· {{ relativeTime(a.created_at) }}</span>
            </li>
            <li v-for="c in state.comments" :key="`c-${c.id}`" class="rounded border border-gray-100 bg-gray-50 p-2 dark:border-gray-800 dark:bg-gray-800/50">
              <div class="flex items-baseline justify-between">
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ c.author_name ?? c.actor_name ?? 'You' }}</span>
                <span class="text-xs text-gray-400 dark:text-gray-500">{{ relativeTime(c.created_at) }}</span>
              </div>
              <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ c.body }}</p>
            </li>
          </ul>

          <!-- Composer -->
          <div class="mt-4 rounded border border-gray-200 dark:border-gray-700">
            <div class="flex gap-1 border-b border-gray-200 px-2 pt-2 dark:border-gray-700">
              <button
                type="button"
                class="-mb-px border-b-2 px-2 py-1 text-xs font-medium"
                :class="commentTab === 'write' ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400'"
                @click="commentTab = 'write'"
              >Write</button>
              <button
                type="button"
                class="-mb-px border-b-2 px-2 py-1 text-xs font-medium"
                :class="commentTab === 'preview' ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400'"
                @click="commentTab = 'preview'"
              >Preview</button>
            </div>
            <div class="p-2">
              <textarea
                v-if="commentTab === 'write'"
                v-model="commentBody"
                rows="3"
                placeholder="Leave a comment (markdown supported)…"
                class="w-full resize-y rounded border border-gray-200 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <div v-else class="min-h-[4rem] whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ commentBody || 'Nothing to preview.' }}
              </div>
              <div class="mt-2 text-right">
                <button
                  type="button"
                  class="rounded bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                  :disabled="!commentBody.trim() || posting"
                  @click="submitComment"
                >Comment</button>
              </div>
            </div>
          </div>
        </StatPanel>
      </div>

      <!-- Sidebar -->
      <div class="space-y-4">
        <StatPanel title="Details">
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">First seen</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ relativeTime(state.issue.first_seen_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Last seen</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ relativeTime(state.issue.last_seen_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Occurrences</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ state.issue.occurrences_count ?? 0 }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Users</dt>
              <dd class="text-gray-900 dark:text-gray-100">{{ state.issue.users_count ?? 0 }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Assigned</dt>
              <dd class="flex items-center gap-1.5">
                <span
                  v-if="state.issue.assigned_to"
                  class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-400"
                  :title="state.issue.assigned_to"
                >{{ String(state.issue.assigned_to).slice(0, 1).toUpperCase() }}</span>
                <span v-else class="inline-block h-6 w-6 rounded-full border border-dashed border-gray-300 dark:border-gray-600" />
              </dd>
            </div>
            <div class="flex items-center gap-1.5 pt-1">
              <input
                v-model="assigneeInput"
                type="text"
                placeholder="Unassigned"
                class="min-w-0 flex-1 rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                @keyup.enter="saveAssignee"
                @blur="saveAssignee"
              />
              <button
                v-if="state.issue.assigned_to !== auth.user?.email"
                type="button"
                class="whitespace-nowrap text-xs text-primary-600 hover:underline dark:text-primary-400"
                @click="assign(auth.user?.email)"
              >Assign to me</button>
              <button
                v-else
                type="button"
                class="whitespace-nowrap text-xs text-gray-500 hover:underline dark:text-gray-400"
                @click="assign(null)"
              >Unassign</button>
            </div>
          </dl>
        </StatPanel>

        <StatPanel title="Occurrences by environment">
          <ul class="space-y-1.5 text-sm">
            <li v-if="!state.occurrencesByEnv.length" class="text-gray-400 dark:text-gray-500">No data.</li>
            <li v-for="env in state.occurrencesByEnv" :key="env.environment" class="flex items-center justify-between">
              <span class="text-gray-700 dark:text-gray-300">{{ env.environment }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ env.count }}</span>
            </li>
          </ul>
        </StatPanel>
      </div>
    </div>
  </div>
</template>
