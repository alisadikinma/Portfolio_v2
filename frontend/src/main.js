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

// CRITICAL: Initialize theme BEFORE app mounts to prevent flash
app.use(pinia)
const themeStore = useThemeStore()
themeStore.initTheme()

// Configure QueryClient with AGGRESSIVE cache policies
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 10 * 60 * 1000, // 10 minutes - data dianggap fresh
      gcTime: 24 * 60 * 60 * 1000, // 24 HOURS - keep in memory cache
      refetchOnWindowFocus: false, // Jangan refetch saat window focus
      refetchOnMount: false, // Jangan refetch on mount if data exists
      refetchOnReconnect: false, // Jangan refetch on reconnect
      retry: 1,
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000)
    }
  }
})

// MANUAL PERSISTENT CACHE IMPLEMENTATION - Simple & Reliable
const CACHE_KEY = 'PORTFOLIO_QUERY_CACHE'
const CACHE_VERSION = 'v1'

// Restore cache from localStorage on app init
try {
  const savedCache = localStorage.getItem(CACHE_KEY)
  if (savedCache) {
    const parsed = JSON.parse(savedCache)
    if (parsed.version === CACHE_VERSION && parsed.timestamp) {
      const age = Date.now() - parsed.timestamp
      const maxAge = 24 * 60 * 60 * 1000 // 24 hours
      
      if (age < maxAge) {
        // Cache masih valid, restore ke TanStack Query
        Object.entries(parsed.queries || {}).forEach(([key, data]) => {
          queryClient.setQueryData(JSON.parse(key), data)
        })
        console.log('[App] ⚡ Cache RESTORED from localStorage (' + Object.keys(parsed.queries || {}).length + ' queries)')
      } else {
        console.log('[App] 🗑️ Cache expired, clearing...')
        localStorage.removeItem(CACHE_KEY)
      }
    }
  }
} catch (error) {
  console.error('[App] ❌ Failed to restore cache:', error)
  localStorage.removeItem(CACHE_KEY)
}

// Save cache to localStorage on window unload (before close/refresh)
const saveCache = () => {
  try {
    const cache = queryClient.getQueryCache().getAll()
    const toSave = {}
    
    cache.forEach(query => {
      if (query.state.data) {
        toSave[JSON.stringify(query.queryKey)] = query.state.data
      }
    })
    
    const cacheData = {
      version: CACHE_VERSION,
      timestamp: Date.now(),
      queries: toSave
    }
    
    localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData))
    console.log('[App] 💾 Cache saved to localStorage (' + Object.keys(toSave).length + ' queries)')
  } catch (error) {
    console.error('[App] ❌ Failed to save cache:', error)
  }
}

// Save cache on page unload
window.addEventListener('beforeunload', saveCache)

// Also save cache periodically (every 5 minutes)
setInterval(saveCache, 5 * 60 * 1000)

console.log('[App] ✅ Persistent cache initialized!')

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
