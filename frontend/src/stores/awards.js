import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAwardsStore = defineStore('awards', {
  state: () => ({
    awards: [],
    currentAward: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    },
    loading: false,
    error: null
  }),

  getters: {
    getAwardById: (state) => (id) => {
      return state.awards.find(award => award.id === id)
    },
    hasAwards: (state) => state.awards.length > 0
  },

  actions: {
    async fetchAwards(page = 1, perPage = 10, filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: perPage,
          ...filters
        }

        const response = await api.get('/admin/awards', { params })

        this.awards = response.data.data
        this.pagination = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch awards'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchAward(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/awards/${id}`)

        // Extract award data from nested response structure
        if (response.data.success && response.data.data.award) {
          this.currentAward = {
            ...response.data.data.award,
            galleries: response.data.data.galleries || []
          }
        } else {
          this.currentAward = response.data.data
        }

        return this.currentAward
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch award'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createAward(awardData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/awards', awardData)

        const newAward = response.data.success ? response.data.data : response.data.data

        if (this.awards.length < this.pagination.per_page) {
          this.awards.unshift(newAward)
        }

        return newAward
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create award'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateAward(id, awardData) {
      this.loading = true
      this.error = null

      try {
        // Use POST with _method=PUT for FormData compatibility
        const response = await api.post(`/admin/awards/${id}`, awardData)

        const updatedAward = response.data.success ? response.data.data : response.data.data

        const index = this.awards.findIndex(a => a.id === id)
        if (index !== -1) {
          this.awards[index] = updatedAward
        }

        if (this.currentAward?.id === id) {
          this.currentAward = updatedAward
        }

        return updatedAward
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update award'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteAward(id) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/awards/${id}`)

        this.awards = this.awards.filter(a => a.id !== id)

        if (this.currentAward?.id === id) {
          this.currentAward = null
        }

        if (this.pagination.total > 0) {
          this.pagination.total--
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete award'
        throw error
      } finally {
        this.loading = false
      }
    },

    async linkGallery(awardId, galleryId, sortOrder = 0) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post(`/admin/awards/${awardId}/galleries`, {
          gallery_id: galleryId,
          sort_order: sortOrder
        })

        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to link gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async unlinkGallery(awardId, galleryId) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/awards/${awardId}/galleries/${galleryId}`)
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to unlink gallery'
        throw error
      } finally {
        this.loading = false
      }
    },

    async reorderGalleries(awardId, galleries) {
      this.loading = true
      this.error = null

      try {
        const response = await api.put(`/admin/awards/${awardId}/galleries/reorder`, {
          galleries
        })

        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reorder galleries'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearCurrentAward() {
      this.currentAward = null
    },

    clearError() {
      this.error = null
    }
  }
})
