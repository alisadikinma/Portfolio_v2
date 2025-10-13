/**
 * Blog posts API endpoints
 * @module api/posts
 */

import apiClient from './client'

export default {
  /**
   * Get all posts with pagination and filters
   * @param {Object} params - Query parameters
   * @returns {Promise} Paginated posts
   */
  getAll(params = {}) {
    return apiClient.get('/posts', { params })
  },

  /**
   * Get single post by slug
   * @param {string} slug - Post slug
   * @returns {Promise} Post data
   */
  getBySlug(slug) {
    return apiClient.get(`/posts/${slug}`)
  },

  /**
   * Get featured posts
   * @param {number} limit - Number of posts
   * @returns {Promise} Featured posts
   */
  getFeatured(limit = 5) {
    return apiClient.get('/posts/featured', { params: { limit } })
  },

  /**
   * Search posts
   * @param {string} query - Search query
   * @param {Object} filters - Additional filters
   * @returns {Promise} Search results
   */
  search(query, filters = {}) {
    return apiClient.get('/posts/search', {
      params: { q: query, ...filters }
    })
  },

  /**
   * Create new post (Admin)
   * @param {Object} postData - Post data
   * @returns {Promise} Created post
   */
  create(postData) {
    return apiClient.post('/admin/posts', postData)
  },

  /**
   * Update post (Admin)
   * @param {string} slug - Post slug
   * @param {Object} postData - Updated post data
   * @returns {Promise} Updated post
   */
  update(slug, postData) {
    return apiClient.put(`/admin/posts/${slug}`, postData)
  },

  /**
   * Delete post (Admin)
   * @param {string} slug - Post slug
   * @returns {Promise}
   */
  delete(slug) {
    return apiClient.delete(`/admin/posts/${slug}`)
  },
}
