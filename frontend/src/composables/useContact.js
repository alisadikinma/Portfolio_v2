import { ref } from 'vue'
import api from '@/services/api'

export function useContact() {
  const contacts = ref([])
  const contact = ref(null)
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 20,
    total: 0,
    lastPage: 1
  })

  // Submit contact form (public)
  const submitContact = async (contactData) => {
    isSubmitting.value = true
    error.value = null

    try {
      const response = await api.post('/contact', contactData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to send message'
      return {
        success: false,
        error: error.value,
        errors: err.response?.data?.errors || null
      }
    } finally {
      isSubmitting.value = false
    }
  }

  // Fetch all contacts (admin)
  const fetchContacts = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/admin/contacts', { params })
      contacts.value = response.data.data

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
      error.value = err.response?.data?.message || 'Failed to fetch contacts'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single contact (admin)
  const fetchContact = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/admin/contacts/${id}`)
      contact.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contact'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Mark contact as read (admin)
  const markAsRead = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.patch(`/admin/contacts/${id}/mark-as-read`)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to mark as read'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Delete contact (admin)
  const deleteContact = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      await api.delete(`/admin/contacts/${id}`)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete contact'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    contacts,
    contact,
    isLoading,
    isSubmitting,
    error,
    pagination,

    // Methods
    submitContact,
    fetchContacts,
    fetchContact,
    markAsRead,
    deleteContact
  }
}
