import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api/v1'

export const useContactsStore = defineStore('contacts', () => {
  // State
  const contacts = ref([])
  const currentContact = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Getters
  const getContactById = computed(() => {
    return (id) => contacts.value.find(contact => contact.id === id)
  })

  const totalContacts = computed(() => pagination.value.total)

  const unreadCount = computed(() => {
    return contacts.value.filter(c => !c.is_read).length
  })

  const readCount = computed(() => {
    return contacts.value.filter(c => c.is_read).length
  })

  // Actions
  async function fetchContacts(params = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/admin/contacts`, {
        params: {
          page: params.page || pagination.value.currentPage,
          per_page: params.perPage || pagination.value.perPage,
          search: params.search,
          is_read: params.is_read,
          ...params
        },
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })

      contacts.value = response.data.data.data || response.data.data

      if (response.data.data.meta) {
        pagination.value = {
          currentPage: response.data.data.meta.current_page,
          perPage: response.data.data.meta.per_page,
          total: response.data.data.meta.total,
          lastPage: response.data.data.meta.last_page
        }
      }

      return contacts.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contacts'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchContact(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/admin/contacts/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })
      currentContact.value = response.data.data
      return currentContact.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/contacts/${id}/read`,
        {},
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      )

      const updatedContact = response.data.data
      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index] = { ...contacts.value[index], is_read: true }
      }

      if (currentContact.value?.id === id) {
        currentContact.value = { ...currentContact.value, is_read: true }
      }

      return updatedContact
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to mark as read'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function markAsUnread(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/contacts/${id}/unread`,
        {},
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      )

      const updatedContact = response.data.data
      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index] = { ...contacts.value[index], is_read: false }
      }

      if (currentContact.value?.id === id) {
        currentContact.value = { ...currentContact.value, is_read: false }
      }

      return updatedContact
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to mark as unread'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteContact(id) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`${API_BASE_URL}/admin/contacts/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })

      contacts.value = contacts.value.filter(c => c.id !== id)
      pagination.value.total--

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function submitContactForm(formData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(
        `${API_BASE_URL}/contact`,
        formData,
        {
          headers: {
            'Content-Type': 'application/json'
          }
        }
      )

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to submit contact form'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function resetStore() {
    contacts.value = []
    currentContact.value = null
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
    contacts,
    currentContact,
    loading,
    error,
    pagination,

    // Getters
    getContactById,
    totalContacts,
    unreadCount,
    readCount,

    // Actions
    fetchContacts,
    fetchContact,
    markAsRead,
    markAsUnread,
    deleteContact,
    submitContactForm,
    clearError,
    resetStore
  }
})
