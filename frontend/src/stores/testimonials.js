import { defineStore } from 'pinia'
import api from '@/services/api'

export const useTestimonialsStore = defineStore('testimonials', {
  state: () => ({
    testimonials: [],
    currentTestimonial: null,
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
    getTestimonialById: (state) => (id) => {
      return state.testimonials.find(testimonial => testimonial.id === id)
    },
    hasTestimonials: (state) => state.testimonials.length > 0,
    averageRating: (state) => {
      if (state.testimonials.length === 0) return 0
      const sum = state.testimonials.reduce((acc, t) => acc + (t.star_rating || 0), 0)
      return (sum / state.testimonials.length).toFixed(1)
    }
  },

  actions: {
    async fetchTestimonials(page = 1, perPage = 10, filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: perPage,
          ...filters
        }

        const response = await api.get('/admin/testimonials', { params })

        this.testimonials = response.data.data
        this.pagination = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch testimonials'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchTestimonial(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/testimonials/${id}`)
        this.currentTestimonial = response.data.data
        return this.currentTestimonial
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch testimonial'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createTestimonial(testimonialData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/testimonials', testimonialData)

        const newTestimonial = response.data.data

        if (this.testimonials.length < this.pagination.per_page) {
          this.testimonials.unshift(newTestimonial)
        }

        return newTestimonial
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create testimonial'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateTestimonial(id, testimonialData) {
      this.loading = true
      this.error = null

      try {
        // Use POST with _method=PUT for FormData compatibility
        const response = await api.post(`/admin/testimonials/${id}`, testimonialData)

        const updatedTestimonial = response.data.data

        const index = this.testimonials.findIndex(t => t.id === id)
        if (index !== -1) {
          this.testimonials[index] = updatedTestimonial
        }

        if (this.currentTestimonial?.id === id) {
          this.currentTestimonial = updatedTestimonial
        }

        return updatedTestimonial
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update testimonial'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteTestimonial(id) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/testimonials/${id}`)

        this.testimonials = this.testimonials.filter(t => t.id !== id)

        if (this.currentTestimonial?.id === id) {
          this.currentTestimonial = null
        }

        if (this.pagination.total > 0) {
          this.pagination.total--
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete testimonial'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearCurrentTestimonial() {
      this.currentTestimonial = null
    },

    clearError() {
      this.error = null
    }
  }
})
