<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAppStore } from '../../store/app'
import api from '../../services/api'
import { absoluteTime, relativeTime } from '../../utils/format'
import { BADGE } from '../../resourceConfig'
import StatPanel from '../../components/StatPanel.vue'
import BarChartPanel from '../../components/BarChartPanel.vue'
import JsonViewer from '../../components/JsonViewer.vue'

// Row-level drill-down behind one Exceptions-list row (docs/pages/
// exception-detail.md): one exception class's occurrences within the window,
// its stack trace + runtime, an Info block, and a "View issue" cross-link to
// the deduplicated issue. GET /api/apps/{appId}/exception-groups/{key}?period.
const route = useRoute()
const app = useAppStore()

const appId = computed(() => route.params.appId)
const key = computed(() => route.params.key)

const state = reactive({
  loading: false,
  notFound: false,
  class: '',
  message: '',
  handled: false,
  file: '',
  line: null,
  phpVersion: '',
  laravelVersion: '',
  stackFrames: [],
  issue: null,
  panels: {},
  info: null,
  occurrences: [],
  page: 1,
  lastPage: 1,
  total: 0,
})

const stackOpen = ref(true)
const copied = ref('')

// Latest-wins guard: load() fires from the watcher (key/period) and pagination,
// so a slow earlier response must not overwrite a newer one. Each call captures
// its sequence number and only applies if it's still the latest.
let requestSeq = 0

const COLOR = { amber: '#f59e0b', red: '#ef4444' }

const origin = computed(() => {
  if (!state.file) return ''
  return state.line ? `${state.file}:${state.line}` : state.file
})

// "View issue" navigates to the deduplicated issue-detail. The Issue model still
// binds route-model-binding on its integer `id` (uuid is additive but not the
// route key yet), and IssuesPage/IssueDetailPage link by id — so link by id here
// too, otherwise the uuid segment 404s against the id-based issues route.
const issueLink = computed(() => {
  if (!state.issue) return null
  const id = state.issue.id
  return id ? `/dashboard/${appId.value}/issues/${id}` : null
})

const occ = computed(() => state.panels?.occurrences ?? {})
const occLabels = computed(() => ['Occurrences'])
const occDatasets = computed(() => [
  { label: 'Handled', data: [Number(occ.value.handled ?? 0)], backgroundColor: COLOR.amber },
  { label: 'Unhandled', data: [Number(occ.value.unhandled ?? 0)], backgroundColor: COLOR.red },
])

async function load() {
  if (!appId.value || !key.value) return
  const seq = ++requestSeq
  state.loading = true
  state.notFound = false
  const params = { period: app.period, page: state.page }
  try {
    const { data } = await api.get(`/api/apps/${appId.value}/exception-groups/${key.value}`, { params })
    if (seq !== requestSeq) return
    state.class = data.class ?? ''
    state.message = data.message ?? ''
    state.handled = Boolean(data.handled)
    state.file = data.file ?? ''
    state.line = data.line ?? null
    state.phpVersion = data.php_version ?? ''
    state.laravelVersion = data.laravel_version ?? ''
    state.stackFrames = data.stack_frames ?? []
    state.issue = data.issue ?? null
    state.panels = data.panels ?? {}
    state.info = data.info ?? null
    const page = data.occurrences ?? {}
    state.occurrences = page.data ?? []
    state.page = page.current_page ?? 1
    state.lastPage = page.last_page ?? 1
    state.total = page.total ?? state.occurrences.length
  } catch (e) {
    if (seq !== requestSeq) return
    state.class = ''
    state.message = ''
    state.handled = false
    state.file = ''
    state.line = null
    state.phpVersion = ''
    state.laravelVersion = ''
    state.stackFrames = []
    state.issue = null
    state.panels = {}
    state.info = null
    state.occurrences = []
    state.page = 1
    state.lastPage = 1
    state.total = 0
    if (e?.response?.status === 404) state.notFound = true
  } finally {
    if (seq === requestSeq) state.loading = false
  }
}

function goToPage(page) {
  if (page < 1 || page > state.lastPage) return
  state.page = page
  load()
}

function markdown() {
  const frames = state.stackFrames
    .map((f) => `- ${f.file}:${f.line}${f.function ? ` — ${f.function}` : ''}`)
    .join('\n')
  return [
    `# ${state.class || 'Exception'}`,
    '',
    state.message ?? '',
    '',
    `- **Status:** ${state.handled ? 'Handled' : 'Unhandled'}`,
    `- **Origin:** ${origin.value || '—'}`,
    `- **Laravel:** ${state.laravelVersion || '—'} · **PHP:** ${state.phpVersion || '—'}`,
    '',
    '## Stack trace',
    frames || '_No frames_',
  ].join('\n')
}

function aiPrompt() {
  const frames = state.stackFrames
    .map((f) => `${f.file}:${f.line}${f.function ? ` (${f.function})` : ''}`)
    .join('\n')
  return [
    'You are helping debug a Laravel application exception. Explain the likely',
    'root cause and suggest a fix.',
    '',
    `Exception: ${state.class || ''}`,
    `Message: ${state.message ?? ''}`,
    `Origin: ${origin.value || 'unknown'}`,
    `Runtime: Laravel ${state.laravelVersion || '?'}, PHP ${state.phpVersion || '?'}`,
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

watch(
  [key, () => app.period],
  () => { state.page = 1; load() },
  { immediate: true },
)
</script>

<template>
  <div v-if="state.notFound" class="flex flex-col items-center justify-center gap-3 py-24 text-center">
    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Exception not found</p>
    <p class="text-sm text-gray-500 dark:text-gray-400">It may have been deleted, or the link is incorrect.</p>
    <RouterLink
      :to="`/dashboard/${appId}/exceptions`"
      class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
    >Back to Exceptions</RouterLink>
  </div>
  <div v-else class="space-y-4">
    <!-- Title bar: exception message + View issue -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="break-words text-xl font-semibold text-gray-900 dark:text-gray-100">{{ state.message || state.class || 'Exception' }}</h1>
        <p v-if="state.class" class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">{{ state.class }}</p>
      </div>
      <RouterLink
        v-if="issueLink"
        :to="issueLink"
        class="shrink-0 rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
      >View issue</RouterLink>
    </div>

    <!-- Occurrences stat panel -->
    <BarChartPanel title="Occurrences" :labels="occLabels" :datasets="occDatasets" :stacked="true" height-class="h-40">
      <div class="mt-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ occ.total ?? 0 }}</span>
        <span class="text-xs text-amber-600 dark:text-amber-400">Handled {{ occ.handled ?? 0 }}</span>
        <span class="text-xs text-red-600 dark:text-red-400">Unhandled {{ occ.unhandled ?? 0 }}</span>
      </div>
    </BarChartPanel>

    <!-- Exception detail card -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <span
          class="rounded px-2 py-0.5 text-xs font-semibold uppercase"
          :class="state.handled ? BADGE.yellow : BADGE.red"
        >{{ state.handled ? 'Handled' : 'Unhandled' }}</span>
        <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="BADGE.primary">
          Laravel {{ state.laravelVersion || '—' }}
        </span>
        <span class="rounded px-2 py-0.5 text-xs font-medium uppercase" :class="BADGE.blue">
          PHP {{ state.phpVersion || '—' }}
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

      <p class="font-mono text-sm font-semibold text-red-600 dark:text-red-400">{{ state.class }}</p>
      <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ state.message }}</p>
      <p v-if="origin" class="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ origin }}</p>

      <!-- Stack Trace -->
      <div class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
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
      </div>
    </div>

    <!-- Info panel -->
    <StatPanel v-if="state.info" title="Info">
      <div class="overflow-x-auto rounded bg-gray-50 p-3 font-mono text-xs dark:bg-gray-800">
        <JsonViewer :data="state.info" />
      </div>
    </StatPanel>

    <!-- Occurrences table -->
    <StatPanel :title="`${state.total} occurrence${state.total === 1 ? '' : 's'}`">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead>
            <tr>
              <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
              <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
              <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Message</th>
              <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr v-if="state.loading">
              <td colspan="4" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!state.occurrences.length">
              <td colspan="4" class="px-2 py-3 text-center text-gray-400 dark:text-gray-500">No occurrences.</td>
            </tr>
            <tr v-for="(row, i) in state.occurrences" v-else :key="row.id ?? i">
              <td class="whitespace-nowrap px-2 py-1.5 text-gray-500 dark:text-gray-400">
                {{ absoluteTime(row.created_at, { timezone: app.timezone, format: app.timeFormat }) }}
              </td>
              <td class="px-2 py-1.5">
                <span class="rounded px-2 py-0.5 text-xs font-medium" :class="row.handled ? BADGE.yellow : BADGE.red">
                  {{ row.handled ? 'Handled' : 'Unhandled' }}
                </span>
              </td>
              <td class="max-w-xs truncate px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ row.message }}</td>
              <td class="px-2 py-1.5">
                <RouterLink
                  v-if="row.user_id"
                  :to="`/dashboard/${appId}/users/${row.user_id}`"
                  class="text-primary-600 hover:underline dark:text-primary-400"
                >{{ row.user_id }}</RouterLink>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="state.lastPage > 1" class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span>Page {{ state.page }} of {{ state.lastPage }}</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-600"
            :disabled="state.page <= 1"
            @click="goToPage(state.page - 1)"
          >Previous</button>
          <button
            type="button"
            class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40 dark:border-gray-600"
            :disabled="state.page >= state.lastPage"
            @click="goToPage(state.page + 1)"
          >Next</button>
        </div>
      </div>
    </StatPanel>
  </div>
</template>
