import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useTestimonials(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)

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
      return response.data
    },
    staleTime: 30 * 60 * 1000, // 30 minutes
    gcTime: 30 * 60 * 1000 // 30 minutes
  })

  // Computed values for backward compatibility
  const testimonials = computed(() => testimonialsData.value?.data || [])

  const pagination = computed(() => {
    const meta = testimonialsData.value?.meta
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
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single testimonial
  const fetchTestimonial = async (id) => {
    try {
      const response = await api.get(`/testimonials/${id}`)
      return { success: true, data: response.data.data }
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
