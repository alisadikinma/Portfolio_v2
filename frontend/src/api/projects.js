/**
 * Projects API endpoints
 * @module api/projects
 */

import apiClient from './client'

export default {
  /**
   * Get all projects with pagination and filters
   * @param {Object} params - Query parameters
   * @returns {Promise} Paginated projects
   */
  getAll(params = {}) {
    return apiClient.get('/projects', { params })
  },

  /**
   * Get single project by slug
   * @param {string} slug - Project slug
   * @returns {Promise} Project data
   */
  getBySlug(slug) {
    return apiClient.get(`/projects/${slug}`)
  },

  /**
   * Get featured projects
   * @param {number} limit - Number of projects
   * @returns {Promise} Featured projects
   */
  getFeatured(limit = 6) {
    return apiClient.get('/projects/featured', { params: { limit } })
  },

  /**
   * Search projects
   * @param {string} query - Search query
   * @param {Object} filters - Additional filters
   * @returns {Promise} Search results
   */
  search(query, filters = {}) {
    return apiClient.get('/projects/search', {
      params: { q: query, ...filters }
    })
  },

  /**
   * Create new project (Admin)
   * @param {Object} projectData - Project data
   * @returns {Promise} Created project
   */
  create(projectData) {
    return apiClient.post('/admin/projects', projectData)
  },

  /**
   * Update project (Admin)
   * @param {string} slug - Project slug
   * @param {Object} projectData - Updated project data
   * @returns {Promise} Updated project
   */
  update(slug, projectData) {
    return apiClient.put(`/admin/projects/${slug}`, projectData)
  },

  /**
   * Delete project (Admin)
   * @param {string} slug - Project slug
   * @returns {Promise}
   */
  delete(slug) {
    return apiClient.delete(`/admin/projects/${slug}`)
  },
}
