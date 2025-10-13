import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

// Configure axios base URL
const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

// Add axios interceptor for debugging
axios.interceptors.request.use(request => {
  console.log('🔵 Axios Request:', request.method.toUpperCase(), request.url)
  console.log('📦 Request data:', request.data)
  return request
})

axios.interceptors.response.use(
  response => {
    console.log('✅ Axios Response:', response.status, response.config.url)
    return response
  },
  error => {
    console.error('❌ Axios Error:', error.message)
    if (error.response) {
      console.error('📊 Error Response:', error.response.status, error.response.data)
    }
    return Promise.reject(error)
  }
)

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(null)
  const isAuthenticated = ref(false)
  const isLoading = ref(false)
  const error = ref(null)

  // Computed
  const userRole = computed(() => user.value?.role || null)
  const userName = computed(() => user.value?.name || null)
  const userEmail = computed(() => user.value?.email || null)

  // Initialize auth from localStorage
  const initAuth = () => {
    const savedToken = localStorage.getItem('auth_token')
    const savedUser = localStorage.getItem('auth_user')

    if (savedToken && savedUser) {
      token.value = savedToken
      user.value = JSON.parse(savedUser)
      isAuthenticated.value = true

      // Set default axios header
      axios.defaults.headers.common['Authorization'] = `Bearer ${savedToken}`
    }
  }

  // Login action
  const login = async (credentials) => {
    isLoading.value = true
    error.value = null

    console.log('🔵 Login attempt started')
    console.log('📍 API URL:', API_URL)
    console.log('📧 Email:', credentials.email)

    try {
      console.log('⏳ Sending request to:', `${API_URL}/auth/login`)
      
      const response = await axios.post(`${API_URL}/auth/login`, {
        email: credentials.email,
        password: credentials.password
      }, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      })

      console.log('✅ Login response received:', response.data)
      console.log('✅ Response status:', response.status)

      // Backend returns: { success: true, data: { user: {...}, token: "..." } }
      if (response.data.success && response.data.data) {
        const { token: authToken, user: userData } = response.data.data

        console.log('💾 Saving auth data to localStorage')

        // Save to state
        token.value = authToken
        user.value = userData
        isAuthenticated.value = true

        // Save to localStorage
        localStorage.setItem('auth_token', authToken)
        localStorage.setItem('auth_user', JSON.stringify(userData))

        // Set axios default header
        axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`

        console.log('✅ Login successful!')
        return { success: true }
      } else {
        console.error('❌ Invalid response structure:', response.data)
        throw new Error('Invalid response structure')
      }
    } catch (err) {
      console.error('❌ Login error:', err)
      
      // Extract error message
      let errorMsg = 'Login failed'
      if (err.response?.data?.message) {
        errorMsg = err.response.data.message
        console.error('📊 Server error message:', errorMsg)
      } else if (err.response?.data?.errors?.email) {
        errorMsg = err.response.data.errors.email[0]
        console.error('📊 Validation error:', errorMsg)
      } else if (err.message) {
        errorMsg = err.message
        console.error('📊 Error message:', errorMsg)
      }
      
      // Log detailed error for debugging
      if (err.response) {
        console.error('📊 Response status:', err.response.status)
        console.error('📊 Response data:', err.response.data)
      } else if (err.request) {
        console.error('📊 No response received from server')
        console.error('📊 Request:', err.request)
      }
      
      error.value = errorMsg
      return { success: false, error: errorMsg }
    } finally {
      isLoading.value = false
      console.log('🏁 Login process finished')
    }
  }

  // Register action
  const register = async (userData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await axios.post(`${API_URL}/auth/register`, userData, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      })

      if (response.data.success && response.data.data) {
        const { token: authToken, user: newUser } = response.data.data

        // Save to state
        token.value = authToken
        user.value = newUser
        isAuthenticated.value = true

        // Save to localStorage
        localStorage.setItem('auth_token', authToken)
        localStorage.setItem('auth_user', JSON.stringify(newUser))

        // Set axios default header
        axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`

        return { success: true }
      } else {
        throw new Error('Invalid response structure')
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Registration failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Logout action
  const logout = async () => {
    try {
      // Call logout endpoint if authenticated
      if (isAuthenticated.value) {
        await axios.post(`${API_URL}/auth/logout`, {}, {
          headers: {
            'Authorization': `Bearer ${token.value}`
          }
        })
      }
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      // Clear state regardless of API success
      clearAuth()
    }
  }

  // Clear auth data
  const clearAuth = () => {
    token.value = null
    user.value = null
    isAuthenticated.value = false
    error.value = null

    // Clear localStorage
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')

    // Remove axios header
    delete axios.defaults.headers.common['Authorization']
  }

  // Fetch current user
  const fetchUser = async () => {
    if (!token.value) return

    isLoading.value = true

    try {
      const response = await axios.get(`${API_URL}/auth/me`, {
        headers: {
          'Authorization': `Bearer ${token.value}`
        }
      })
      
      if (response.data.success && response.data.data) {
        user.value = response.data.data.user
        localStorage.setItem('auth_user', JSON.stringify(response.data.data.user))
      }
    } catch (err) {
      console.error('Failed to fetch user:', err)
      // If token is invalid, clear auth
      if (err.response?.status === 401) {
        clearAuth()
      }
    } finally {
      isLoading.value = false
    }
  }

  // Update user profile
  const updateProfile = async (profileData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await axios.put(`${API_URL}/user/profile`, profileData)
      user.value = response.data
      localStorage.setItem('auth_user', JSON.stringify(response.data))

      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Profile update failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Check if user has specific role
  const hasRole = (role) => {
    return userRole.value === role
  }

  // Check if user has any of the roles
  const hasAnyRole = (roles) => {
    return roles.includes(userRole.value)
  }

  return {
    // State
    user,
    token,
    isAuthenticated,
    isLoading,
    error,

    // Computed
    userRole,
    userName,
    userEmail,

    // Actions
    initAuth,
    login,
    register,
    logout,
    clearAuth,
    fetchUser,
    updateProfile,
    hasRole,
    hasAnyRole
  }
})
