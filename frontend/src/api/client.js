/**
 * Axios client configuration with interceptors
 * @module api/client
 */

import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost/Portfolio_v2/backend/public/api',
  timeout: parseInt(import.meta.env.VITE_API_TIMEOUT) || 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: false,
})

/**
 * Request interceptor - Add auth token
 */
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (import.meta.env.VITE_ENABLE_DEBUG === 'true') {
      console.debug('API Request:', {
        method: config.method?.toUpperCase(),
        url: config.url,
        data: config.data,
      })
    }

    return config
  },
  (error) => {
    console.error('Request Error:', error)
    return Promise.reject(error)
  }
)

/**
 * Response interceptor - Handle errors globally
 */
apiClient.interceptors.response.use(
  (response) => {
    if (import.meta.env.VITE_ENABLE_DEBUG === 'true') {
      console.debug('API Response:', {
        status: response.status,
        data: response.data,
      })
    }
    return response
  },
  async (error) => {
    // Network error
    if (!error.response) {
      console.error('Network error. Please check your connection.')
      return Promise.reject(error)
    }

    const { status } = error.response

    // Handle specific status codes
    switch (status) {
      case 401:
        // Unauthorized - clear auth and redirect to login
        localStorage.removeItem('auth_token')
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
        break

      case 403:
        console.error('You do not have permission to perform this action.')
        break

      case 404:
        console.error('The requested resource was not found.')
        break

      case 422:
        // Validation errors - handled by components
        break

      case 429:
        console.error('Too many requests. Please try again later.')
        break

      case 500:
      case 502:
      case 503:
        console.error('Server error. Please try again later.')
        break
    }

    return Promise.reject(error)
  }
)

export default apiClient
