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

// Sidebar dropdown open-state (app switcher at the top, account menu at the
// bottom). Only one is open at a time.
const switcherOpen = ref(false)
const accountOpen = ref(false)

// Environment filter — lifted into the app store (`app.environment`) so list
// pages can scope their fetches by it. `null` = "All environments". The
// per-app record exposes `environments` as name→color.
const selectedEnv = computed(() => app.environment)
const environments = computed(() => Object.entries(app.current?.environments ?? {}))
// Reset the filter whenever we switch into a different app.
watch(appId, () => { app.setEnvironment(null) })

function openSwitcher() {
  accountOpen.value = false
  switcherOpen.value = !switcherOpen.value
}
function openAccount() {
  switcherOpen.value = false
  accountOpen.value = !accountOpen.value
}
function selectEnv(name) {
  app.setEnvironment(name)
  switcherOpen.value = false
}

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
// Account-menu theme item: flip straight between light and dark.
const themeToggleLabel = computed(() => (theme.isDark ? 'Light mode' : 'Dark mode'))
function toggleLightDark() {
  theme.setMode(theme.isDark ? 'light' : 'dark')
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
  accountOpen.value = false
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
      <div class="relative border-b border-gray-200 dark:border-gray-800">
        <button
          type="button"
          data-testid="app-switcher-trigger"
          class="flex w-full items-center gap-2 px-3 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800"
          @click="openSwitcher"
        >
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-500 text-sm font-bold text-white">
            {{ (app.current?.name ?? 'N').charAt(0).toUpperCase() }}
          </span>
          <span v-if="!collapsed" class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold">{{ app.current?.name ?? 'Loading…' }}</span>
            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ selectedEnv ?? 'All environments' }}</span>
          </span>
          <span v-if="!collapsed" class="text-gray-400">⌄</span>
        </button>

        <!-- Switcher dropdown -->
        <template v-if="switcherOpen">
          <div class="fixed inset-0 z-10" @click="switcherOpen = false"></div>
          <div
            data-testid="app-switcher-menu"
            class="absolute left-2 right-2 top-full z-20 mt-1 max-h-[70vh] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
          >
            <!-- Environment filter -->
            <div class="px-3 pb-1 pt-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
              Environment
            </div>
            <button
              type="button"
              data-testid="env-option"
              class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
              @click="selectEnv(null)"
            >
              <span class="w-4 text-primary-600 dark:text-primary-400">{{ selectedEnv === null ? '✓' : '' }}</span>
              <span class="flex-1 truncate">All environments</span>
            </button>
            <button
              v-for="[name, color] in environments"
              :key="name"
              type="button"
              data-testid="env-option"
              class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
              @click="selectEnv(name)"
            >
              <span class="w-4 text-primary-600 dark:text-primary-400">{{ selectedEnv === name ? '✓' : '' }}</span>
              <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-black/10" :style="{ backgroundColor: color }"></span>
              <span class="flex-1 truncate">{{ name }}</span>
            </button>

            <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>

            <!-- Sibling apps, grouped by team -->
            <div v-for="team in app.teams" :key="team.id" class="pb-0.5">
              <div class="px-3 pb-0.5 pt-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ team.name }}
              </div>
              <RouterLink
                v-for="sibling in team.apps"
                :key="sibling.app_id"
                data-testid="switcher-app"
                :to="`/dashboard/${sibling.app_id}`"
                class="flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                :class="sibling.app_id === appId ? 'font-semibold text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                @click="switcherOpen = false"
              >
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-gray-200 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                  {{ (sibling.name ?? '?').charAt(0).toUpperCase() }}
                </span>
                <span class="flex-1 truncate">{{ sibling.name }}</span>
              </RouterLink>
            </div>

            <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>

            <!-- Shortcut to the org-dashboard Apps grid -->
            <RouterLink
              to="/"
              class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
              @click="switcherOpen = false"
            >
              <span class="w-5 text-center text-gray-400">▦</span>
              <span class="flex-1">Apps</span>
            </RouterLink>
          </div>
        </template>
      </div>

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

      <!-- Account menu footer -->
      <div class="relative border-t border-gray-200 p-2 dark:border-gray-800">
        <button
          type="button"
          data-testid="account-trigger"
          class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
          @click="openAccount"
        >
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-200 text-xs font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
            {{ (app.org?.name ?? 'O').charAt(0).toUpperCase() }}
          </span>
          <span v-if="!collapsed" class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ app.org?.name ?? 'Organization' }}</span>
            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ app.org?.account_email ?? auth.user?.email }}</span>
          </span>
          <span v-if="!collapsed" class="text-gray-400">⌄</span>
        </button>

        <!-- Account dropdown -->
        <template v-if="accountOpen">
          <div class="fixed inset-0 z-10" @click="accountOpen = false"></div>
          <div
            data-testid="account-menu"
            class="absolute bottom-full left-2 right-2 z-20 mb-1 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
              @click="accountOpen = false"
            >
              Account
            </button>
            <button
              type="button"
              class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
              @click="accountOpen = false"
            >
              Team
            </button>
            <button
              type="button"
              data-testid="theme-toggle"
              class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
              @click="toggleLightDark"
            >
              <span>{{ themeToggleLabel }}</span>
              <span class="text-gray-400">{{ theme.isDark ? '☀' : '☾' }}</span>
            </button>
            <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
            <button
              type="button"
              data-testid="account-logout"
              class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
              @click="signOut"
            >
              Log out
            </button>
          </div>
        </template>
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
