import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import router from './router'
import './style.css'
import App from './App.vue'
import api from './services/api'

const app = createApp(App)
const pinia = createPinia()

// Configure QueryClient with cache policies
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes default
      gcTime: 30 * 60 * 1000, // 30 minutes default (renamed from cacheTime in v5)
      refetchOnWindowFocus: false,
      refetchOnMount: false, // Don't refetch on mount if data exists (prevent background refetch)
      refetchOnReconnect: false, // Don't refetch on reconnect
      retry: 1,
      retryDelay: (attemptIndex) => Math.min(1000 * 2 ** attemptIndex, 30000)
    }
  }
})

// Prefetch critical homepage data on app load for instant first render
;(async () => {
  try {
    console.log('[App] 🚀 Prefetching critical data for instant homepage...')
    await queryClient.prefetchQuery({
      queryKey: ['about-settings'],
      queryFn: async () => {
        const response = await api.get('/settings/about')
        return response.data.success ? response.data.data : null
      },
      staleTime: 15 * 60 * 1000
    })
    console.log('[App] ✅ About settings prefetched - homepage will render instantly!')
  } catch (error) {
    console.log('[App] ⚠️ Prefetch failed (non-critical):', error.message)
  }
})()

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })
app.mount('#app')
