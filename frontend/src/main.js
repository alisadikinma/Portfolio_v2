import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import router from './router'
import './style.css'
import App from './App.vue'
import api from './services/api'
import i18n from './i18n'
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

// Prefetch critical homepage data on app load for instant first render
;(async () => {
  try {
    console.log('[App] 🚀 Checking cache...')
    
    // Cek cache dulu
    const cachedAbout = queryClient.getQueryData(['about-settings'])
    if (cachedAbout) {
      console.log('[App] ⚡ CACHE HIT - about settings already loaded!')
    } else {
      console.log('[App] 📡 CACHE MISS - prefetching about settings...')
      await queryClient.prefetchQuery({
        queryKey: ['about-settings'],
        queryFn: async () => {
          const response = await api.get('/settings/about')
          return response.data.success ? response.data.data : null
        },
        staleTime: 30 * 60 * 1000
      })
      console.log('[App] ✅ About settings prefetched!')
    }
  } catch (error) {
    console.log('[App] ⚠️ Prefetch failed (non-critical):', error.message)
  }
})()

app.use(router)
app.use(i18n)
app.use(VueQueryPlugin, { queryClient })
app.mount('#app')

// Register Service Worker for media caching (videos + images)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register('/sw.js')
    .then((reg) => console.log('[SW] Media cache active'))
    .catch((err) => console.warn('[SW] Registration failed:', err))
}
