/**
 * Projects store
 * @module stores/projects
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useProjectsStore = defineStore('projects', () => {
  // State
  const projects = ref([])
  const currentProject = ref(null)
  const featuredProjects = ref([])
  const pagination = ref({
    currentPage: 1,
    perPage: 12,
    total: 0,
    lastPage: 1,
  })
  const filters = ref({
    category: null,
    technology: null,
    featured: null,
  })
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const hasMore = computed(() => pagination.value.currentPage < pagination.value.lastPage)
  const totalProjects = computed(() => pagination.value.total)

  // Actions

  /**
   * Fetch all projects with pagination
   * @param {Object} params - Query parameters
   */
  async function fetchProjects(params = {}) {
    loading.value = true
    error.value = null

    try {
      // TODO: Implement API call when backend is ready
      // const response = await projectsApi.getAll(params)
      // projects.value = response.data.data

      // Mock data for now
      projects.value = []

      return projects.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch projects'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single project by slug
   * @param {string} slug - Project slug
   */
  async function fetchProject(slug) {
    loading.value = true
    error.value = null

    try {
      // TODO: Implement API call
      currentProject.value = null
      return currentProject.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch project'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Set filters
   * @param {Object} newFilters - Filter values
   */
  function setFilters(newFilters) {
    filters.value = { ...filters.value, ...newFilters }
    pagination.value.currentPage = 1
  }

  /**
   * Clear filters
   */
  function clearFilters() {
    filters.value = {
      category: null,
      technology: null,
      featured: null,
    }
    pagination.value.currentPage = 1
  }

  /**
   * Reset store state
   */
  function reset() {
    projects.value = []
    currentProject.value = null
    featuredProjects.value = []
    pagination.value = {
      currentPage: 1,
      perPage: 12,
      total: 0,
      lastPage: 1,
    }
    clearFilters()
    loading.value = false
    error.value = null
  }

  return {
    // State
    projects,
    currentProject,
    featuredProjects,
    pagination,
    filters,
    loading,
    error,

    // Getters
    hasMore,
    totalProjects,

    // Actions
    fetchProjects,
    fetchProject,
    setFilters,
    clearFilters,
    reset,
  }
})
