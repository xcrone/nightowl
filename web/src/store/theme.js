import { defineStore } from 'pinia'
import { applyTheme, resolveIsDark, THEME_STORAGE_KEY } from '../utils/theme'

let mediaListenerAttached = false

export const useThemeStore = defineStore('theme', {
  state: () => {
    const mode = localStorage.getItem(THEME_STORAGE_KEY) ?? 'system'
    return { mode, isDark: resolveIsDark(mode) }
  },

  actions: {
    setMode(mode) {
      this.mode = mode
      localStorage.setItem(THEME_STORAGE_KEY, mode)
      this.isDark = resolveIsDark(mode)
      applyTheme(this.isDark)
    },

    // Keeps the resolved theme in sync with the OS while mode === 'system'.
    init() {
      if (mediaListenerAttached) return
      mediaListenerAttached = true

      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (this.mode !== 'system') return
        this.isDark = resolveIsDark(this.mode)
        applyTheme(this.isDark)
      })
    },
  },
})
