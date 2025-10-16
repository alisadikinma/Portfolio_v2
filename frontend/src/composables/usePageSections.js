import { ref } from 'vue'
import api from '@/services/api'

export function usePageSections() {
  const sections = ref([])
  const section = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  // Fetch all sections (admin)
  const fetchSections = async (page = null) => {
    isLoading.value = true
    error.value = null

    try {
      const params = page ? { page } : {}
      const response = await api.get('/admin/page-sections', { params })
      sections.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch sections'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch active sections (public)
  const fetchActiveSections = async (page = null) => {
    isLoading.value = true
    error.value = null

    try {
      const params = page ? { page } : {}
      const response = await api.get('/page-sections', { params })
      sections.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch active sections'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Update section (toggle active/inactive)
  const updateSection = async (id, data) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put(`/admin/page-sections/${id}`, data)

      // Update in local array
      const index = sections.value.findIndex(s => s.id === id)
      if (index !== -1) {
        sections.value[index] = response.data.data
      }

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update section'
      return { success: false, error: error.value, errors: err.response?.data?.errors }
    } finally {
      isLoading.value = false
    }
  }

  // Reorder sections
  const reorderSections = async (items) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put('/admin/page-sections/reorder', { items })
      sections.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to reorder sections'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
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
