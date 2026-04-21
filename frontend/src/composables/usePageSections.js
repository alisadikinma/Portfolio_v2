import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function usePageSections() {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const selectedPage = ref(null)
  const section = ref(null)
  
  // State untuk instant data dari localStorage
  const cachedSections = ref(null)
  
  // Separate state for admin data (no cache)
  const adminSections = ref([])
  const adminLoading = ref(false)
  const adminError = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    if (selectedPage.value) {
      const cacheKey = `page_sections_${selectedPage.value}`
      cachedSections.value = getCache(cacheKey)
      
      if (cachedSections.value) {
        console.log('[usePageSections] âš¡ INSTANT from localStorage')
      }
    }
  })

  // Fetch active sections. Cache aggression deliberately low because this is
  // admin-toggled visibility data — operators toggle a section on/off in
  // /admin/page-sections and expect to see the effect on public pages within
  // the next navigation, not in 10 minutes. refetchOnMount: 'always' overrides
  // the global default (false) so every SPA route change gets fresh data.
  const {
    data: sectionsData,
    isLoading: publicLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['page-sections', selectedPage],
    queryFn: async () => {
      if (!selectedPage.value) return []

      const params = { page: selectedPage.value }
      const response = await api.get('/page-sections', { params })
      console.log('[usePageSections] Background fetch complete')

      const cacheKey = `page_sections_${selectedPage.value}`
      setCache(cacheKey, response.data.data, 30 * 1000) // 30s

      return response.data.data
    },
    enabled: computed(() => !!selectedPage.value),
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
    refetchOnMount: 'always',
    initialData: () => {
      // Show stale localStorage instantly for FCP, then refetch (refetchOnMount)
      if (!selectedPage.value) return []
      const cacheKey = `page_sections_${selectedPage.value}`
      return getCache(cacheKey) || []
    }
  })

  // Computed values - prefer admin data if available, otherwise public data
  const sections = computed(() => {
    // Admin data takes precedence
    if (adminSections.value.length > 0) {
      return adminSections.value
    }
    
    // Otherwise use public data with cache
    if (publicLoading.value && cachedSections.value) {
      return cachedSections.value
    }
    return sectionsData.value || []
  })
  
  const isLoading = computed(() => adminLoading.value || publicLoading.value)
  const error = computed(() => adminError.value || queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch all sections (admin) - NO CACHE (admin data)
  const fetchSections = async (page = null) => {
    adminLoading.value = true
    adminError.value = null
    
    try {
      const params = page ? { page } : {}
      const response = await api.get('/admin/page-sections', { params })
      
      // Update admin sections state
      adminSections.value = response.data.data
      
      return { success: true, data: response.data.data }
    } catch (err) {
      adminError.value = err.response?.data?.message || 'Failed to fetch sections'
      return {
        success: false,
        error: adminError.value
      }
    } finally {
      adminLoading.value = false
    }
  }

  // Fetch active sections (public) - WITH CACHE
  const fetchActiveSections = async (page = null) => {
    selectedPage.value = page
    
    const cacheKey = `page_sections_${page}`
    
    // Check instant localStorage
    cachedSections.value = getCache(cacheKey)
    
    // Check TanStack Query cache
    const queryCache = queryClient.getQueryData(['page-sections', page])
    if (queryCache) {
      console.log('[usePageSections] TanStack Query cache HIT:', page)
      return { success: true, data: queryCache }
    }

    if (!cachedSections.value) {
      console.log('[usePageSections] Cache MISS for:', page, '- fetching...')
    } else {
      console.log('[usePageSections] âš¡ INSTANT from localStorage, background refresh...')
    }
    
    const result = await refetch()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Update section (toggle active/inactive) - INVALIDATE ALL CACHE LAYERS
  const updateSection = async (id, data) => {
    try {
      const response = await api.put(`/admin/page-sections/${id}`, data)

      // Update local admin sections
      const index = adminSections.value.findIndex(s => s.id === id)
      if (index !== -1) {
        adminSections.value[index] = response.data.data
      }

      // Layer 1: Invalidate TanStack Query cache
      queryClient.invalidateQueries({ queryKey: ['page-sections'] })

      // Layer 2: Clear useLocalCache
      if (selectedPage.value) {
        const cacheKey = `page_sections_${selectedPage.value}`
        setCache(cacheKey, null, 0)
      }
      // Also clear common page keys
      setCache('page_sections_homepage', null, 0)

      // Layer 3: Clear persistent 24hr cache in main.js (PORTFOLIO_QUERY_CACHE)
      try {
        const persistentCache = localStorage.getItem('PORTFOLIO_QUERY_CACHE')
        if (persistentCache) {
          const parsed = JSON.parse(persistentCache)
          if (parsed.queries) {
            // Remove all page-sections entries from persistent cache
            const keysToRemove = Object.keys(parsed.queries).filter(k => k.includes('page-sections'))
            keysToRemove.forEach(k => delete parsed.queries[k])
            localStorage.setItem('PORTFOLIO_QUERY_CACHE', JSON.stringify(parsed))
          }
        }
      } catch (e) {
        // Non-fatal
      }

      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to update section',
        errors: err.response?.data?.errors
      }
    }
  }

  // Reorder sections - INVALIDATE ALL CACHE LAYERS
  const reorderSections = async (items) => {
    try {
      const response = await api.put('/admin/page-sections/reorder', { items })

      // Update local admin sections
      adminSections.value = response.data.data

      // Layer 1: Invalidate TanStack Query cache
      queryClient.invalidateQueries({ queryKey: ['page-sections'] })

      // Layer 2: Clear useLocalCache
      if (selectedPage.value) {
        const cacheKey = `page_sections_${selectedPage.value}`
        setCache(cacheKey, null, 0)
      }
      setCache('page_sections_homepage', null, 0)

      // Layer 3: Clear persistent 24hr cache
      try {
        const persistentCache = localStorage.getItem('PORTFOLIO_QUERY_CACHE')
        if (persistentCache) {
          const parsed = JSON.parse(persistentCache)
          if (parsed.queries) {
            const keysToRemove = Object.keys(parsed.queries).filter(k => k.includes('page-sections'))
            keysToRemove.forEach(k => delete parsed.queries[k])
            localStorage.setItem('PORTFOLIO_QUERY_CACHE', JSON.stringify(parsed))
          }
        }
      } catch (e) {
        // Non-fatal
      }

      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to reorder sections'
      }
    }
  }

  return {
    // State
    sections,
    section,
    isLoading,
    error,

    // Methods
    fetchSections,
    fetchActiveSections,
    updateSection,
    reorderSections
  }
}
