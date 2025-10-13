/**
 * API composable
 * @module composables/useApi
 */

import { ref } from 'vue'
import apiClient from '@/api/client'

/**
 * Generic API composable for HTTP requests
 * @returns {Object} API methods and state
 */
export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  const data = ref(null)

  /**
   * Generic GET request
   * @param {string} url - API endpoint
   * @param {Object} params - Query parameters
   */
  const get = async (url, params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.get(url, { params })
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Generic POST request
   * @param {string} url - API endpoint
   * @param {Object} payload - Request body
   */
  const post = async (url, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.post(url, payload)
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Generic PUT request
   * @param {string} url - API endpoint
   * @param {Object} payload - Request body
   */
  const put = async (url, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.put(url, payload)
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Generic PATCH request
   * @param {string} url - API endpoint
   * @param {Object} payload - Request body
   */
  const patch = async (url, payload) => {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.patch(url, payload)
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Generic DELETE request
   * @param {string} url - API endpoint
   */
  const del = async (url) => {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.delete(url)
      data.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Reset state
   */
  const reset = () => {
    loading.value = false
    error.value = null
    data.value = null
  }

  return {
    // State
    loading,
    error,
    data,

    // Methods
    get,
    post,
    put,
    patch,
    delete: del,
    reset,

    // Direct access to client
    client: apiClient,
  }
}
