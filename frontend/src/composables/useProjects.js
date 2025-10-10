import { ref } from 'vue'
import api from '@/services/api'

export function useProjects() {
  const projects = ref([])
  const project = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Fetch all projects
  const fetchProjects = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/projects', { params })
      projects.value = response.data.data

      if (response.data.meta) {
        pagination.value = {
          currentPage: response.data.meta.current_page,
          perPage: response.data.meta.per_page,
          total: response.data.meta.total,
          lastPage: response.data.meta.last_page
        }
      }

      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch projects'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single project by slug
  const fetchProject = async (slug, lang = 'en') => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/projects/${slug}`, {
        params: { lang }
      })
      project.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch project'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Create project (admin)
  const createProject = async (projectData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/projects', projectData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create project'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Update project (admin)
  const updateProject = async (id, projectData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put(`/admin/projects/${id}`, projectData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update project'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Delete project (admin)
  const deleteProject = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      await api.delete(`/admin/projects/${id}`)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete project'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    projects,
    project,
    isLoading,
    error,
    pagination,

    // Methods
    fetchProjects,
    fetchProject,
    createProject,
    updateProject,
    deleteProject
  }
}
