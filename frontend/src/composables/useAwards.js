import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useAwards(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)

  // Fetch all awards with caching (1hr stale / 1hr cache)
  const {
    data: awardsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['awards', queryParams],
    queryFn: async () => {
      const response = await api.get('/awards', { params: queryParams.value })
      return response.data
    },
    staleTime: 60 * 60 * 1000, // 1 hour
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Computed values for backward compatibility
  const awards = computed(() => {
    // Handle both success: true format and direct data format
    const data = awardsData.value?.data || awardsData.value || []
    return data
  })

  const pagination = computed(() => {
    const meta = awardsData.value?.meta
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
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single award
  const fetchAward = async (id) => {
    try {
      const response = await api.get(`/awards/${id}`)
      return { success: true, data: response.data.data }
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
