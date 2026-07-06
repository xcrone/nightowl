<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router'
import { useAuthStore } from '../store/auth'
import { useAppStore } from '../store/app'
import { useThemeStore } from '../store/theme'
import { navGroups } from '../nav.js'
import PeriodSelector from '../components/PeriodSelector.vue'
import { BADGE } from '../resourceConfig'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const app = useAppStore()
const theme = useThemeStore()

const appId = computed(() => route.params.appId)
const collapsed = ref(false)

const allItems = navGroups.flatMap((g) => g.items)

// Active nav item -> page title, derived from the child path segment.
const pageTitle = computed(() => {
  const prefix = `/dashboard/${appId.value}`
  const rel = route.path.startsWith(prefix) ? route.path.slice(prefix.length).replace(/^\//, '') : ''
  const base = rel.split('/')[0]
  const item = allItems.find((i) => i.path === base) ?? allItems.find((i) => i.path === '')
  return item?.label ?? 'Dashboard'
})

function linkTo(item) {
  const suffix = item.path ? `/${item.path}` : ''
  return `/dashboard/${appId.value}${suffix}`
}

// The dashboard link ('' path) must not stay active on every child route.
function isExact(item) {
  return item.path === ''
}

const THEME_CYCLE = ['system', 'light', 'dark']
function cycleTheme() {
  theme.setMode(THEME_CYCLE[(THEME_CYCLE.indexOf(theme.mode) + 1) % THEME_CYCLE.length])
}

async function loadApp(id) {
  if (!id) return
  if (!app.apps.length) {
    await app.fetchApps().catch(() => {})
  }
  await app.setCurrentApp(id).catch(() => {})
}

watch(appId, (id) => loadApp(id), { immediate: true })

async function signOut() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <!-- Sidebar -->
    <aside
      class="flex shrink-0 flex-col border-r border-gray-200 bg-white transition-all dark:border-gray-800 dark:bg-gray-900"
      :class="collapsed ? 'w-16' : 'w-60'"
    >
      <!-- App switcher -->
      <RouterLink
        to="/"
        class="flex items-center gap-2 border-b border-gray-200 px-3 py-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
      >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-500 text-sm font-bold text-white">
          {{ (app.current?.name ?? 'N').charAt(0).toUpperCase() }}
        </span>
        <span v-if="!collapsed" class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold">{{ app.current?.name ?? 'Loading…' }}</span>
          <span class="block truncate text-xs text-gray-500 dark:text-gray-400">All environments</span>
        </span>
        <span v-if="!collapsed" class="text-gray-400">⌄</span>
      </RouterLink>

      <!-- Nav groups -->
      <nav class="flex-1 overflow-y-auto px-2 py-3">
        <div v-for="(group, gi) in navGroups" :key="gi" class="mb-4">
          <div
            v-if="group.label && !collapsed"
            class="mb-1 px-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
          >
            {{ group.label }}
          </div>
          <ul class="space-y-0.5">
            <li v-for="item in group.items" :key="item.key">
              <RouterLink
                :to="linkTo(item)"
                :exact-active-class="isExact(item) ? 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400' : ''"
                :active-class="isExact(item) ? '' : 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400'"
                class="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                :title="item.label"
              >
                <span class="flex h-5 w-5 shrink-0 items-center justify-center text-xs font-semibold text-gray-400 dark:text-gray-500">
                  {{ item.label.charAt(0) }}
                </span>
                <span v-if="!collapsed" class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                <span
                  v-if="item.badge && !collapsed && app.openIssues"
                  class="rounded-full px-1.5 text-xs font-medium"
                  :class="BADGE.red"
                >
                  {{ app.openIssues }}
                </span>
              </RouterLink>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Org switcher footer -->
      <div class="border-t border-gray-200 p-2 dark:border-gray-800">
        <div class="flex items-center gap-2 rounded px-2 py-1.5">
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-200 text-xs font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
            {{ (app.org?.name ?? 'O').charAt(0).toUpperCase() }}
          </span>
          <span v-if="!collapsed" class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ app.org?.name ?? 'Organization' }}</span>
            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ app.org?.account_email ?? auth.user?.email }}</span>
          </span>
        </div>
        <button
          v-if="!collapsed"
          type="button"
          class="mt-1 w-full rounded px-2 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
          @click="signOut"
        >
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Top bar -->
      <header class="flex items-center gap-3 border-b border-gray-200 bg-white px-4 py-2.5 dark:border-gray-800 dark:bg-gray-900">
        <button
          type="button"
          class="rounded p-1.5 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
          title="Toggle sidebar"
          @click="collapsed = !collapsed"
        >
          ☰
        </button>
        <h1 class="text-base font-semibold">{{ pageTitle }}</h1>

        <div class="ml-auto flex items-center gap-2">
          <PeriodSelector />

          <select
            :value="app.timezone"
            class="rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            @change="app.setTimezone($event.target.value)"
          >
            <option value="Local">Local</option>
            <option value="UTC">UTC</option>
          </select>

          <select
            :value="app.timeFormat"
            class="rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            @change="app.setTimeFormat($event.target.value)"
          >
            <option value="24h">24h</option>
            <option value="12h">12h</option>
          </select>

          <button
            type="button"
            class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            :title="`Theme: ${theme.mode}`"
            @click="cycleTheme"
          >
            {{ theme.isDark ? '☾' : '☀' }}
          </button>
        </div>
      </header>

      <main class="min-w-0 flex-1 p-4 sm:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
