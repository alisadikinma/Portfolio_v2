import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAutomation = defineStore('automation', {
  state: () => ({
    tokens: [],
    logs: [],
    loading: false,
    error: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      perPage: 20,
      total: 0
    }
  }),

  getters: {
    activeTokens: (state) => state.tokens.filter(token => !token.revoked_at),
    revokedTokens: (state) => state.tokens.filter(token => token.revoked_at),
    totalActiveTokens: (state) => state.tokens.filter(token => !token.revoked_at).length,
  },

  actions: {
    /**
     * Fetch all API tokens
     */
    async fetchTokens() {
      this.loading = true
      this.error = null

      try {
        const response = await api.get('/admin/automation/tokens')
        this.tokens = response.data.data || []
        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch tokens'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Create new API token
     */
    async createToken(tokenData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/automation/tokens', tokenData)

        // Add the new token to the list
        if (response.data.data) {
          this.tokens.unshift(response.data.data)
        }

        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create token'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Revoke API token
     */
    async revokeToken(tokenId) {
      this.loading = true
      this.error = null

      try {
        const response = await api.delete(`/admin/automation/tokens/${tokenId}`)

        // Update the token in the list
        const tokenIndex = this.tokens.findIndex(t => t.id === tokenId)
        if (tokenIndex !== -1) {
          this.tokens[tokenIndex].revoked_at = new Date().toISOString()
        }

        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to revoke token'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Fetch automation logs with filters
     */
    async fetchLogs(filters = {}) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get('/admin/automation/logs', { params: filters })

        this.logs = response.data.data || []

        if (response.data.meta) {
          this.pagination = {
            currentPage: response.data.meta.current_page,
            lastPage: response.data.meta.last_page,
            perPage: response.data.meta.per_page,
            total: response.data.meta.total
          }
        }

        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch logs'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Clear all logs (admin only)
     */
    async clearLogs() {
      this.loading = true
      this.error = null

      try {
        const response = await api.delete('/admin/automation/logs')
        this.logs = []
        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to clear logs'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Test automation endpoint
     */
    async testEndpoint(endpoint, method = 'GET', data = null) {
      this.loading = true
      this.error = null

      try {
        let response
        switch (method.toUpperCase()) {
          case 'POST':
            response = await api.post(endpoint, data)
            break
          case 'PUT':
            response = await api.put(endpoint, data)
            break
          case 'DELETE':
            response = await api.delete(endpoint)
            break
          default:
            response = await api.get(endpoint)
        }

        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Request failed'
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Reset error state
     */
    clearError() {
      this.error = null
    }
  }
})
