import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import router from './router'
import './style.css'
import App from './App.vue'
import { useThemeStore } from './stores/theme'

const app = createApp(App)
const pinia = createPinia()

// CRITICAL: Initialize stores BEFORE app mounts
app.use(pinia)
const themeStore = useThemeStore()
themeStore.initTheme()

// Initialize auth from localStorage so new tabs inherit the session
import { useAuthStore } from './stores/auth'
const authStore = useAuthStore()
authStore.initAuth()

// QueryClient — in-memory cache only.
// Persistent localStorage cache removed 2026-05-05 (caused 24h stale window;
// rely on browser HTTP cache + ETag/304 for cheap revalidation instead).
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 10 * 60 * 1000,
      gcTime: 24 * 60 * 60 * 1000,
      refetchOnWindowFocus: false,
      refetchOnMount: false,
      refetchOnReconnect: false,
      retry: 1,
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000)
    }
  }
})

// One-time cleanup of legacy persistent cache key for any users still
// carrying it from before the 2026-05-05 refactor. Safe to remove this
// block after a month of production deploy (May → June 2026).
try {
  if (localStorage.getItem('PORTFOLIO_QUERY_CACHE')) {
    localStorage.removeItem('PORTFOLIO_QUERY_CACHE')
  }
} catch (_) {
  // Non-fatal — localStorage may be disabled in some browsers
}

app.use(router)
app.use(VueQueryPlugin, { queryClient })
app.mount('#app')

// Retire the legacy media-cache service worker (it cached hero videos for the old
// public SPA). public/sw.js is now a self-destructing worker: visiting any SPA page
// lets already-registered browsers pick it up, clear media-cache-* and unregister.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.getRegistrations()
    .then((regs) => regs.forEach((reg) => reg.update()))
    .catch(() => {})
}
