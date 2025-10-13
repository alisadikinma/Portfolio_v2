import { ref } from 'vue'
import api from '@/services/api'

export function useAwards() {
  const awards = ref([])
  const award = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Fetch all awards
  const fetchAwards = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/awards', { params })
      awards.value = response.data.data

      if (response.data.meta) {
        pagination.value = {
          currentPage: response.data.meta.current_page,
          perPage: response.data.meta.per_page,
          total: response.data.meta.total,
          lastPage: response.data.meta.last_page
        }
      }

      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch awards'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single award
  const fetchAward = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/awards/${id}`)
      award.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch award'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    awards,
    award,
    isLoading,
    error,
    pagination,

    // Methods
    fetchAwards,
    fetchAward
  }
}
