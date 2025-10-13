import { ref } from 'vue'
import api from '@/services/api'

export function useTestimonials() {
  const testimonials = ref([])
  const testimonial = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Fetch all testimonials
  const fetchTestimonials = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/testimonials', { params })
      testimonials.value = response.data.data

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
      error.value = err.response?.data?.message || 'Failed to fetch testimonials'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single testimonial
  const fetchTestimonial = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/testimonials/${id}`)
      testimonial.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch testimonial'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    testimonials,
    testimonial,
    isLoading,
    error,
    pagination,

    // Methods
    fetchTestimonials,
    fetchTestimonial
  }
}
