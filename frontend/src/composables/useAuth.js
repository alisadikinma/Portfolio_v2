/**
 * Authentication composable
 * @module composables/useAuth
 */

import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

/**
 * Authentication composable for component use
 * @returns {Object} Auth methods and computed properties
 */
export function useAuth() {
  const authStore = useAuthStore()
  const router = useRouter()

  // Computed properties
  const user = computed(() => authStore.user)
  const isAuthenticated = computed(() => authStore.isAuthenticated)
  const isAdmin = computed(() => authStore.isAdmin)
  const loading = computed(() => authStore.loading)

  /**
   * Login user
   * @param {Object} credentials - Email and password
   */
  const login = async (credentials) => {
    try {
      await authStore.login(credentials)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || 'Login failed'
      }
    }
  }

  /**
   * Register user
   * @param {Object} userData - Registration data
   */
  const register = async (userData) => {
    try {
      await authStore.register(userData)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || 'Registration failed'
      }
    }
  }

  /**
   * Logout user and redirect
   */
  const logout = async () => {
    await authStore.logout()
    router.push('/login')
  }

  /**
   * Require authentication (redirect if not authenticated)
   */
  const requireAuth = () => {
    if (!isAuthenticated.value) {
      router.push('/login')
      return false
    }
    return true
  }

  /**
   * Require admin role (redirect if not admin)
   */
  const requireAdmin = () => {
    if (!isAdmin.value) {
      router.push('/')
      return false
    }
    return true
  }

  return {
    // State
    user,
    isAuthenticated,
    isAdmin,
    loading,

    // Methods
    login,
    register,
    logout,
    requireAuth,
    requireAdmin,
  }
}
