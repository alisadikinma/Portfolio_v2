import { defineStore } from 'pinia'
import api from '@/services/api'

export const useContactsStore = defineStore('contacts', {
  state: () => ({
    contacts: [],
    currentContact: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0
    },
    loading: false,
    error: null
  }),

  getters: {
    getContactById: (state) => (id) => {
      return state.contacts.find(contact => contact.id === id)
    },
    hasContacts: (state) => state.contacts.length > 0,
    unreadCount: (state) => {
      return state.contacts.filter(c => !c.is_read).length
    },
    readCount: (state) => {
      return state.contacts.filter(c => c.is_read).length
    }
  },

  actions: {
    async fetchContacts(page = 1, perPage = 20, filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: perPage,
          ...filters
        }

        const response = await api.get('/admin/contacts', { params })

        this.contacts = response.data.data
        this.pagination = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch contacts'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchContact(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/contacts/${id}`)
        this.currentContact = response.data.data

        // Update in contacts list if present
        const index = this.contacts.findIndex(c => c.id === id)
        if (index !== -1) {
          this.contacts[index] = response.data.data
        }

        return this.currentContact
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch contact'
        throw error
      } finally {
        this.loading = false
      }
    },

    async markAsRead(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.patch(`/admin/contacts/${id}/mark-as-read`)

        const updatedContact = response.data.data

        // Update in contacts list
        const index = this.contacts.findIndex(c => c.id === id)
        if (index !== -1) {
          this.contacts[index] = updatedContact
        }

        // Update current contact if viewing it
        if (this.currentContact?.id === id) {
          this.currentContact = updatedContact
        }

        return updatedContact
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to mark as read'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteContact(id) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/contacts/${id}`)

        this.contacts = this.contacts.filter(c => c.id !== id)

        if (this.currentContact?.id === id) {
          this.currentContact = null
        }

        if (this.pagination.total > 0) {
          this.pagination.total--
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete contact'
        throw error
      } finally {
        this.loading = false
      }
    },

    async exportContacts(filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = { ...filters }

        const response = await api.get('/admin/contacts/export', {
          params,
          responseType: 'blob'
        })

        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url

        // Extract filename from content-disposition header or use default
        const contentDisposition = response.headers['content-disposition']
        let filename = 'contacts_export.csv'
        if (contentDisposition) {
          const filenameMatch = contentDisposition.match(/filename="?(.+)"?/)
          if (filenameMatch) {
            filename = filenameMatch[1]
          }
        }

        link.setAttribute('download', filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        return true
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to export contacts'
        throw error
      } finally {
        this.loading = false
      }
    },

    async submitContactForm(formData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/contact', formData)
        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to submit contact form'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearCurrentContact() {
      this.currentContact = null
    },

    clearError() {
      this.error = null
    }
  }
})
