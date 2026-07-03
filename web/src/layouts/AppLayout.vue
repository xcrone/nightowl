<script setup>
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../store/auth'
import { useThemeStore } from '../store/theme'
import { navGroups } from '../resourceConfig'

const route = useRoute()
const auth = useAuthStore()
const theme = useThemeStore()

const THEME_CYCLE = ['system', 'light', 'dark']

function cycleTheme() {
  const next = THEME_CYCLE[(THEME_CYCLE.indexOf(theme.mode) + 1) % THEME_CYCLE.length]
  theme.setMode(next)
}
</script>

<template>
  <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
    <aside class="w-56 shrink-0 border-r border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
      <div class="mb-6 text-lg font-semibold text-primary-700">NightOwl</div>

      <nav v-for="group in navGroups" :key="group.label" class="mb-5">
        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ group.label }}</div>
        <ul>
          <li v-for="item in group.items" :key="item.key">
            <RouterLink
              :to="item.route ?? `/${item.key}`"
              class="block rounded px-2 py-1 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
              active-class="bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-400"
            >
              {{ item.label }}
            </RouterLink>
          </li>
        </ul>
      </nav>
    </aside>

    <div class="min-w-0 flex-1">
      <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3 dark:border-gray-700 dark:bg-gray-900">
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ route.path }}</span>
        <div class="flex items-center gap-3 text-sm">
          <button
            class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="cycleTheme"
          >
            Theme: {{ theme.mode }}
          </button>
          <span class="text-gray-600 dark:text-gray-300">{{ auth.user?.email }}</span>
          <button class="text-red-600 hover:underline dark:text-red-400" @click="auth.logout().then(() => $router.push('/login'))">
            Sign out
          </button>
        </div>
      </header>

      <main class="p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
