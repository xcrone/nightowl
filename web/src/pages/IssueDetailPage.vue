<script setup>
import { reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'
import ResourceDetail from '../components/ResourceDetail.vue'
import { statusColor } from '../resourceConfig'
import { formatDatetime } from '../utils/format'

const route = useRoute()
const issueId = route.params.id

const state = reactive({
  comments: [],
  newComment: '',
  posting: false,
  detailKey: 0,
})

async function loadComments() {
  const { data } = await api.get(`/api/issues/${issueId}/comments`)
  state.comments = data
}

async function transition(action) {
  await api.post(`/api/issues/${issueId}/${action}`)
  state.detailKey++ // remount ResourceDetail to refetch the updated status
}

async function postComment() {
  if (!state.newComment.trim()) return
  state.posting = true
  try {
    await api.post(`/api/issues/${issueId}/comments`, { body: state.newComment })
    state.newComment = ''
    await loadComments()
  } finally {
    state.posting = false
  }
}

onMounted(loadComments)
</script>

<template>
  <ResourceDetail resource="issues" :id="issueId" :key="state.detailKey">
    <template #before="{ record }">
      <div class="mb-4 flex items-center gap-3">
        <span class="rounded px-2 py-1 text-sm font-medium" :class="statusColor(record.status)">{{ record.status }}</span>
        <div class="flex gap-2">
          <button
            v-if="record.status !== 'resolved'"
            class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="transition('resolve')"
          >
            Resolve
          </button>
          <button
            v-if="record.status !== 'ignored'"
            class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="transition('ignore')"
          >
            Ignore
          </button>
          <button
            v-if="record.status !== 'open'"
            class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="transition('reopen')"
          >
            Reopen
          </button>
        </div>
      </div>
    </template>

    <template #after>
      <div class="mt-6 rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Comments</h2>

        <div v-for="comment in state.comments" :key="comment.id" class="mb-3 border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800">
          <div class="text-xs text-gray-500 dark:text-gray-400">{{ comment.user_name }} · {{ formatDatetime(comment.created_at) }}</div>
          <div class="text-sm text-gray-900 dark:text-gray-100">{{ comment.body }}</div>
        </div>
        <p v-if="!state.comments.length" class="text-sm text-gray-400 dark:text-gray-500">No comments yet.</p>

        <div class="mt-3 flex gap-2">
          <input
            v-model="state.newComment"
            type="text"
            placeholder="Add a comment…"
            class="flex-1 rounded border border-gray-300 px-3 py-1 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            @keyup.enter="postComment"
          />
          <button
            class="rounded bg-primary-600 px-3 py-1 text-sm font-medium text-white disabled:opacity-50"
            :disabled="state.posting"
            @click="postComment"
          >
            Post
          </button>
        </div>
      </div>
    </template>
  </ResourceDetail>
</template>
