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

// Prefetch critical homepage data on app load for instant first render.
// Gated by URL path so admin and auth routes don't pay for a 25KB
// /api/settings/about payload (dominated by experience+gallery hydration —
// see CLAUDE.md May 4 entry on Phase 11) that those pages never consume.
// The router's beforeEach hook still synchronously prefetches on `home` route
// navigation, so first-visit home stays warm via that path.
;(async () => {
  const path = (typeof window !== 'undefined' && window.location?.pathname) || '/'
  if (path.startsWith('/admin') || path.startsWith('/login') || path.startsWith('/auth')) {
    return
  }
  try {
    const cachedAbout = queryClient.getQueryData(['about-settings'])
    if (cachedAbout) return
    await queryClient.prefetchQuery({
      queryKey: ['about-settings'],
      queryFn: async () => {
        const response = await api.get('/settings/about')
        return response.data.success ? response.data.data : null
      },
      staleTime: 30 * 60 * 1000
    })
  } catch (error) {
    // Non-fatal — public pages re-fetch on mount via useAboutSettings
  }
})()

app.use(router)
app.use(i18n)
app.use(VueQueryPlugin, { queryClient })
app.mount('#app')

// GA4 (gtag.js) — gated by VITE_GA4_MEASUREMENT_ID. When the id is empty or
// undefined, NO script loads and NO network request fires (dev-safe). Measures
// human referrals from AI chats (Pillar 5); AI bot crawls are logged
// server-side instead (see backend LogAiCrawler middleware).
;(() => {
  const ga4Id = import.meta.env.VITE_GA4_MEASUREMENT_ID
  if (!ga4Id) return

  const s = document.createElement('script')
  s.async = true
  s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4Id)}`
  document.head.appendChild(s)

  window.dataLayer = window.dataLayer || []
  function gtag() { window.dataLayer.push(arguments) }
  window.gtag = gtag
  gtag('js', new Date())
  gtag('config', ga4Id)
})()

// Register Service Worker for media caching (videos + images)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register('/sw.js')
    .then((reg) => console.log('[SW] Media cache active'))
    .catch((err) => console.warn('[SW] Registration failed:', err))
}
