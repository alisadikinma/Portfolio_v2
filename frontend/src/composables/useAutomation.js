import { ref } from 'vue'
import api from '@/services/api'

export function useAutomation() {
  const tokens = ref([])
  const logs = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  /**
   * Fetch all automation tokens
   */
  const fetchTokens = async () => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/admin/automation/tokens')
      if (response.data.success) {
        tokens.value = response.data.data
        return { success: true, data: response.data.data }
      }
      return { success: false, error: 'Failed to fetch tokens' }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch tokens'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Create new automation token
   * @param {Object} tokenData - { name: string, expires_at: string|null }
   */
  const createToken = async (tokenData) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.post('/admin/automation/tokens', tokenData)
      if (response.data.success) {
        // Refresh tokens list
        await fetchTokens()
        return { 
          success: true, 
          data: response.data.data,
          token: response.data.token // Important: return the actual token string
        }
      }
      return { success: false, error: 'Failed to create token' }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create token'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Delete automation token
   * @param {number} id - Token ID
   */
  const deleteToken = async (id) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.delete(`/admin/automation/tokens/${id}`)
      if (response.data.success) {
        // Refresh tokens list
        await fetchTokens()
        return { success: true }
      }
      return { success: false, error: 'Failed to delete token' }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete token'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch automation logs
   * @param {Object} params - Query params (per_page, page, action, etc.)
   */
  const fetchLogs = async (params = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/admin/automation/logs', { params })
      if (response.data.success) {
        logs.value = response.data.data
        return { 
          success: true, 
          data: response.data.data,
          meta: response.data.meta || {}
        }
      }
      return { success: false, error: 'Failed to fetch logs' }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch logs'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Clear all automation logs
   */
  const clearLogs = async () => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.delete('/admin/automation/logs')
      if (response.data.success) {
        logs.value = []
        return { success: true }
      }
      return { success: false, error: 'Failed to clear logs' }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to clear logs'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    tokens,
    logs,
    isLoading,
    error,

    // Methods
    fetchTokens,
    createToken,
    deleteToken,
    fetchLogs,
    clearLogs
  }
}
