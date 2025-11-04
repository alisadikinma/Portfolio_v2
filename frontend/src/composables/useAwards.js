import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function useAwards(initialParams = {}) {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const queryParams = ref(initialParams)
  
  // State untuk instant data dari localStorage
  const cachedAwards = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    const cacheKey = `awards_${JSON.stringify(queryParams.value)}`
    cachedAwards.value = getCache(cacheKey)
    
    if (cachedAwards.value) {
      console.log('[useAwards] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all awards with caching (1hr stale / 1hr cache)
  const {
    data: awardsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['awards', queryParams],
    queryFn: async () => {
      console.log('[useAwards] Background fetch...')
      const response = await api.get('/awards', { params: queryParams.value })
      console.log('[useAwards] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `awards_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, response.data, 60 * 60 * 1000) // 1 hour
      
      return response.data
    },
    staleTime: 60 * 60 * 1000, // 1 hour
    gcTime: 60 * 60 * 1000, // 1 hour
    refetchOnMount: false,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    retry: false,
    initialData: () => {
      // Try get from localStorage first
      const cacheKey = `awards_${JSON.stringify(queryParams.value)}`
      return getCache(cacheKey)
    }
  })

  // Computed values - prefer localStorage instant data
  const awards = computed(() => {
    if (isLoading.value && cachedAwards.value) {
      // Instant display while loading
      const data = cachedAwards.value.data || cachedAwards.value || []
      console.log('[useAwards] Using cached data:', data.length, 'items')
      return data
    }
    
    // Handle both success: true format and direct data format
    const data = awardsData.value?.data || awardsData.value || []
    console.log('[useAwards] Using query data:', data.length, 'items')
    return data
  })

  const pagination = computed(() => {
    const source = cachedAwards.value || awardsData.value
    const meta = source?.meta
    return meta ? {
      currentPage: meta.current_page,
      perPage: meta.per_page,
      total: meta.total,
      lastPage: meta.last_page
    } : {
      currentPage: 1,
      perPage: 15,
      total: 0,
      lastPage: 1
    }
  })

  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch awards with params
  const fetchAwards = async (params = {}) => {
    queryParams.value = params
    
    // Check instant cache
    const cacheKey = `awards_${JSON.stringify(params)}`
    cachedAwards.value = getCache(cacheKey)
    
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single award
  const fetchAward = async (id) => {
    const cacheKey = `award_${id}`
    
    // Check localStorage first
    const cached = getCache(cacheKey)
    if (cached) {
      console.log('[useAwards] âš¡ INSTANT award from localStorage:', id)
      return { success: true, data: cached }
    }
    
    try {
      const response = await api.get(`/awards/${id}`)
      const data = response.data.data
      
      // Cache the result
      setCache(cacheKey, data, 60 * 60 * 1000) // 1 hour
      
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch award'
      }
    }
  }

  return {
    // State - computed for reactivity
    awards,
    award: ref(null),
    isLoading,
    error,
    pagination,

    // Methods - backward compatible
    fetchAwards,
    fetchAward
  }
}
