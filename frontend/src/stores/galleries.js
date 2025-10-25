import { defineStore } from 'pinia'
import api from '@/services/api'

export const useGalleriesStore = defineStore('galleries', {
  state: () => ({
    galleries: [],
    currentGallery: null,
    currentGalleryItems: [],
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

        const response = await api.get('/admin/galleries', { params })

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
        const response = await api.get(`/admin/galleries/${id}`)
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
        // Create FormData for file upload
        const formData = new FormData()
        Object.keys(galleryData).forEach(key => {
          if (galleryData[key] !== null && galleryData[key] !== undefined) {
            formData.append(key, galleryData[key])
          }
        })

        const response = await api.post('/admin/galleries', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

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
        // Create FormData for file upload
        const formData = new FormData()
        Object.keys(galleryData).forEach(key => {
          if (galleryData[key] !== null && galleryData[key] !== undefined) {
            formData.append(key, galleryData[key])
          }
        })

        const response = await api.put(`/admin/galleries/${id}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

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
        await api.delete(`/admin/galleries/${id}`)

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

    // Gallery Items Actions
    async fetchGalleryItems(galleryId) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/galleries/${galleryId}/items`)
        this.currentGalleryItems = response.data.data
        return this.currentGalleryItems
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch gallery items'
        throw error
      } finally {
        this.loading = false
      }
    },

    async addGalleryItem(galleryId, itemData) {
      this.loading = true
      this.error = null

      try {
        const formData = new FormData()
        Object.keys(itemData).forEach(key => {
          if (itemData[key] !== null && itemData[key] !== undefined) {
            formData.append(key, itemData[key])
          }
        })

        const response = await api.post(`/admin/galleries/${galleryId}/items`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        const newItem = response.data.data
        this.currentGalleryItems.push(newItem)

        return newItem
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to add gallery item'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateGalleryItem(galleryId, itemId, itemData) {
      this.loading = true
      this.error = null

      try {
        const formData = new FormData()
        Object.keys(itemData).forEach(key => {
          if (itemData[key] !== null && itemData[key] !== undefined) {
            formData.append(key, itemData[key])
          }
        })

        const response = await api.put(`/admin/galleries/${galleryId}/items/${itemId}`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        const updatedItem = response.data.data
        const index = this.currentGalleryItems.findIndex(item => item.id === itemId)
        if (index !== -1) {
          this.currentGalleryItems[index] = updatedItem
        }

        return updatedItem
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update gallery item'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteGalleryItem(galleryId, itemId) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/galleries/${galleryId}/items/${itemId}`)
        this.currentGalleryItems = this.currentGalleryItems.filter(item => item.id !== itemId)
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete gallery item'
        throw error
      } finally {
        this.loading = false
      }
    },

    async bulkUploadGalleryItems(galleryId, files, titles = [], descriptions = []) {
      this.loading = true
      this.error = null

      try {
        const formData = new FormData()
        files.forEach(file => formData.append('files[]', file))
        titles.forEach((title, index) => formData.append(`titles[${index}]`, title))
        descriptions.forEach((desc, index) => formData.append(`descriptions[${index}]`, desc))

        const response = await api.post(`/admin/galleries/${galleryId}/items/bulk-upload`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        const newItems = response.data.data
        this.currentGalleryItems.push(...newItems)

        return newItems
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to bulk upload gallery items'
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
