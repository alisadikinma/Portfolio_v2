import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

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

      // Note: Token is automatically added by api.js interceptor
    }
  }

  // Login action
  const login = async (credentials) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/auth/login', {
        email: credentials.email,
        password: credentials.password
      })

      console.log('Login response:', response.data)

      // Backend returns: { success: true, data: { user: {...}, token: "..." } }
      if (response.data.success && response.data.data) {
        const { token: authToken, user: userData } = response.data.data

        // Save to state
        token.value = authToken
        user.value = userData
        isAuthenticated.value = true

        // Save to localStorage
        localStorage.setItem('auth_token', authToken)
        localStorage.setItem('auth_user', JSON.stringify(userData))

        // Note: Token is automatically added by api.js interceptor

        return { success: true }
      } else {
        throw new Error('Invalid response structure')
      }
    } catch (err) {
      console.error('Login error:', err)
      
      // Extract error message
      let errorMsg = 'Login failed'
      if (err.response?.data?.message) {
        errorMsg = err.response.data.message
      } else if (err.response?.data?.errors?.email) {
        errorMsg = err.response.data.errors.email[0]
      } else if (err.message) {
        errorMsg = err.message
      }
      
      error.value = errorMsg
      return { success: false, error: errorMsg }
    } finally {
      isLoading.value = false
    }
  }

  // Register action
  const register = async (userData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/auth/register', userData)

      if (response.data.success && response.data.data) {
        const { token: authToken, user: newUser } = response.data.data

        // Save to state
        token.value = authToken
        user.value = newUser
        isAuthenticated.value = true

        // Save to localStorage
        localStorage.setItem('auth_token', authToken)
        localStorage.setItem('auth_user', JSON.stringify(newUser))

        // Note: Token is automatically added by api.js interceptor

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
        await api.post('/auth/logout')
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

    // Note: Token is automatically removed by api.js interceptor when not in localStorage
  }

  // Fetch current user
  const fetchUser = async () => {
    if (!token.value) return

    isLoading.value = true

    try {
      const response = await api.get('/auth/me')
      
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
      const response = await api.put('/user/profile', profileData)
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
