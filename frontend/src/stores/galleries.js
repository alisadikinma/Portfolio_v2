import { defineStore } from 'pinia'
import api from '@/services/api'

export const useGalleriesStore = defineStore('galleries', {
  state: () => ({
    galleries: [],
    currentGallery: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 0
    },
    loading: false,
    error: null
  }),

  getters: {
    getGalleryById: (state) => (id) => {
      return state.galleries.find(gallery => gallery.id === id)
    },
    hasGalleries: (state) => state.galleries.length > 0
  },

  actions: {
    async fetchGalleries(page = 1, perPage = 12, filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: perPage,
          ...filters
        }

        const response = await api.get('/admin/gallery', { params })

        this.galleries = response.data.data
        this.pagination = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch galleries'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchGallery(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/gallery/${id}`)
        this.currentGallery = response.data.data
        return this.currentGallery
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createGallery(galleryData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/gallery', galleryData)

        const newGallery = response.data.data

        if (this.galleries.length < this.pagination.per_page) {
          this.galleries.unshift(newGallery)
        }

        return newGallery
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateGallery(id, galleryData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.put(`/admin/gallery/${id}`, galleryData)

        const updatedGallery = response.data.data

        const index = this.galleries.findIndex(g => g.id === id)
        if (index !== -1) {
          this.galleries[index] = updatedGallery
        }

        if (this.currentGallery?.id === id) {
          this.currentGallery = updatedGallery
        }

        return updatedGallery
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteGallery(id) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/gallery/${id}`)

        this.galleries = this.galleries.filter(g => g.id !== id)

        if (this.currentGallery?.id === id) {
          this.currentGallery = null
        }

        if (this.pagination.total > 0) {
          this.pagination.total--
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async bulkUpload(uploadData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/gallery/bulk-upload', uploadData)

        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to upload galleries'
        throw error
      } finally {
        this.loading = false
      }
    },

    async bulkDelete(ids) {
      this.loading = true
      this.error = null

      try {
        await api.post('/admin/gallery/bulk-delete', { ids })

        this.galleries = this.galleries.filter(g => !ids.includes(g.id))

        if (this.pagination.total > 0) {
          this.pagination.total -= ids.length
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete galleries'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearCurrentGallery() {
      this.currentGallery = null
    },

    clearError() {
      this.error = null
    }
  }
})
