/**
 * Authentication API endpoints
 * @module api/auth
 */

import apiClient from './client'

export default {
  /**
   * Login user
   * @param {Object} credentials - Email and password
   * @returns {Promise} User data and token
   */
  login(credentials) {
    return apiClient.post('/auth/login', credentials)
  },

  /**
   * Register new user
   * @param {Object} userData - User registration data
   * @returns {Promise} User data and token
   */
  register(userData) {
    return apiClient.post('/auth/register', userData)
  },

  /**
   * Logout user
   * @returns {Promise}
   */
  logout() {
    return apiClient.post('/auth/logout')
  },

  /**
   * Get current authenticated user
   * @returns {Promise} User data
   */
  getUser() {
    return apiClient.get('/auth/user')
  },

  /**
   * Refresh authentication token
   * @returns {Promise} New token
   */
  refreshToken() {
    return apiClient.post('/auth/refresh')
  },

  /**
   * Request password reset
   * @param {string} email - User email
   * @returns {Promise}
   */
  forgotPassword(email) {
    return apiClient.post('/auth/forgot-password', { email })
  },

  /**
   * Reset password with token
   * @param {Object} data - Reset token and new password
   * @returns {Promise}
   */
  resetPassword(data) {
    return apiClient.post('/auth/reset-password', data)
  },

  /**
   * Update user profile
   * @param {Object} profileData - Updated profile data
   * @returns {Promise} Updated user data
   */
  updateProfile(profileData) {
    return apiClient.put('/auth/profile', profileData)
  },

  /**
   * Change password
   * @param {Object} passwords - Current and new password
   * @returns {Promise}
   */
  changePassword(passwords) {
    return apiClient.put('/auth/change-password', passwords)
  },
}
