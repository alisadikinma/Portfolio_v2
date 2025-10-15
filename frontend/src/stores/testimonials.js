import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api/v1'

export const useTestimonialsStore = defineStore('testimonials', () => {
  // State
  const testimonials = ref([])
  const currentTestimonial = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Getters
  const getTestimonialById = computed(() => {
    return (id) => testimonials.value.find(testimonial => testimonial.id === id)
  })

  const totalTestimonials = computed(() => pagination.value.total)

  const averageRating = computed(() => {
    if (testimonials.value.length === 0) return 0
    const sum = testimonials.value.reduce((acc, t) => acc + (t.rating || 0), 0)
    return (sum / testimonials.value.length).toFixed(1)
  })

  // Actions
  async function fetchTestimonials(params = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/testimonials`, {
        params: {
          page: params.page || pagination.value.currentPage,
          per_page: params.perPage || pagination.value.perPage,
          search: params.search,
          rating: params.rating,
          ...params
        }
      })

      testimonials.value = response.data.data.data || response.data.data

      if (response.data.data.meta) {
        pagination.value = {
          currentPage: response.data.data.meta.current_page,
          perPage: response.data.data.meta.per_page,
          total: response.data.data.meta.total,
          lastPage: response.data.data.meta.last_page
        }
      }

      return testimonials.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch testimonials'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchTestimonial(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/testimonials/${id}`)
      currentTestimonial.value = response.data.data
      return currentTestimonial.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch testimonial'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTestimonial(testimonialData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(
        `${API_BASE_URL}/admin/testimonials`,
        testimonialData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const newTestimonial = response.data.data
      testimonials.value.unshift(newTestimonial)
      pagination.value.total++

      return newTestimonial
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create testimonial'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTestimonial(id, testimonialData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/testimonials/${id}`,
        testimonialData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const updatedTestimonial = response.data.data
      const index = testimonials.value.findIndex(t => t.id === id)
      if (index !== -1) {
        testimonials.value[index] = updatedTestimonial
      }

      if (currentTestimonial.value?.id === id) {
        currentTestimonial.value = updatedTestimonial
      }

      return updatedTestimonial
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update testimonial'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTestimonial(id) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`${API_BASE_URL}/admin/testimonials/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })

      testimonials.value = testimonials.value.filter(t => t.id !== id)
      pagination.value.total--

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete testimonial'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function resetStore() {
    testimonials.value = []
    currentTestimonial.value = null
    loading.value = false
    error.value = null
    pagination.value = {
      currentPage: 1,
      perPage: 15,
      total: 0,
      lastPage: 1
    }
  }

  return {
    // State
    testimonials,
    currentTestimonial,
    loading,
    error,
    pagination,

    // Getters
    getTestimonialById,
    totalTestimonials,
    averageRating,

    // Actions
    fetchTestimonials,
    fetchTestimonial,
    createTestimonial,
    updateTestimonial,
    deleteTestimonial,
    clearError,
    resetStore
  }
})
