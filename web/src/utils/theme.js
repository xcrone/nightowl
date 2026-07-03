export const THEME_STORAGE_KEY = 'nightowl-theme'

export function resolveIsDark(mode) {
  if (mode === 'dark') return true
  if (mode === 'light') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function applyTheme(isDark) {
  document.documentElement.classList.toggle('dark', isDark)
}
