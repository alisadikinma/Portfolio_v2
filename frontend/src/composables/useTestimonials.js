import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function useTestimonials(initialParams = {}) {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const queryParams = ref(initialParams)
  
  // State untuk instant data dari localStorage
  const cachedTestimonials = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    const cacheKey = `testimonials_${JSON.stringify(queryParams.value)}`
    cachedTestimonials.value = getCache(cacheKey)
    
    if (cachedTestimonials.value) {
      console.log('[useTestimonials] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all testimonials with caching (30min stale / 30min cache)
  const {
    data: testimonialsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['testimonials', queryParams],
    queryFn: async () => {
      const response = await api.get('/testimonials', { params: queryParams.value })
      console.log('[useTestimonials] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `testimonials_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, response.data, 30 * 60 * 1000) // 30min
      
      return response.data
    },
    staleTime: 30 * 60 * 1000, // 30 minutes
    gcTime: 30 * 60 * 1000, // 30 minutes
    initialData: () => {
      // Try get from localStorage first
      const cacheKey = `testimonials_${JSON.stringify(queryParams.value)}`
      return getCache(cacheKey)
    }
  })

  // Computed values - prefer localStorage instant data
  const testimonials = computed(() => {
    if (isLoading.value && cachedTestimonials.value) {
      // Instant display while loading
      return cachedTestimonials.value.data || []
    }
    return testimonialsData.value?.data || []
  })

  const pagination = computed(() => {
    const source = cachedTestimonials.value || testimonialsData.value
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

  // Fetch testimonials with params
  const fetchTestimonials = async (params = {}) => {
    queryParams.value = params
    
    // Check instant cache
    const cacheKey = `testimonials_${JSON.stringify(params)}`
    cachedTestimonials.value = getCache(cacheKey)
    
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single testimonial
  const fetchTestimonial = async (id) => {
    const cacheKey = `testimonial_${id}`
    
    // Check localStorage first
    const cached = getCache(cacheKey)
    if (cached) {
      console.log('[useTestimonials] âš¡ INSTANT testimonial from localStorage:', id)
      return { success: true, data: cached }
    }
    
    try {
      const response = await api.get(`/testimonials/${id}`)
      const data = response.data.data
      
      // Cache the result
      setCache(cacheKey, data, 30 * 60 * 1000) // 30min
      
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch testimonial'
      }
    }
  }

  return {
    // State - computed for reactivity
    testimonials,
    testimonial: ref(null),
    isLoading,
    error,
    pagination,

    // Methods - backward compatible
    fetchTestimonials,
    fetchTestimonial
  }
}
