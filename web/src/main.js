import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { applyTheme, resolveIsDark, THEME_STORAGE_KEY } from './utils/theme'

// Must run before mount to avoid a flash of the wrong theme.
applyTheme(resolveIsDark(localStorage.getItem(THEME_STORAGE_KEY) ?? 'system'))

createApp(App).use(createPinia()).use(router).mount('#app')
