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

  // Fetch active sections with caching (10min stale / 1hr cache)
  const {
    data: sectionsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['page-sections', selectedPage],
    queryFn: async () => {
      if (!selectedPage.value) return []
      
      const params = { page: selectedPage.value }
      const response = await api.get('/page-sections', { params })
      console.log('[usePageSections] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `page_sections_${selectedPage.value}`
      setCache(cacheKey, response.data.data, 10 * 60 * 1000) // 10min
      
      return response.data.data
    },
    enabled: computed(() => !!selectedPage.value),
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      if (!selectedPage.value) return []
      const cacheKey = `page_sections_${selectedPage.value}`
      return getCache(cacheKey) || []
    }
  })

  // Computed values - prefer localStorage instant data
  const sections = computed(() => {
    if (isLoading.value && cachedSections.value) {
      return cachedSections.value
    }
    return sectionsData.value || []
  })
  
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch all sections (admin) - NO CACHE (admin data)
  const fetchSections = async (page = null) => {
    try {
      const params = page ? { page } : {}
      const response = await api.get('/admin/page-sections', { params })
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch sections'
      }
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

  // Update section (toggle active/inactive) - INVALIDATE CACHE
  const updateSection = async (id, data) => {
    try {
      const response = await api.put(`/admin/page-sections/${id}`, data)

      // Invalidate cache
      queryClient.invalidateQueries({ queryKey: ['page-sections'] })
      if (selectedPage.value) {
        const cacheKey = `page_sections_${selectedPage.value}`
        setCache(cacheKey, null, 0)
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

  // Reorder sections - INVALIDATE CACHE
  const reorderSections = async (items) => {
    try {
      const response = await api.put('/admin/page-sections/reorder', { items })
      
      // Invalidate cache
      queryClient.invalidateQueries({ queryKey: ['page-sections'] })
      if (selectedPage.value) {
        const cacheKey = `page_sections_${selectedPage.value}`
        setCache(cacheKey, null, 0)
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
