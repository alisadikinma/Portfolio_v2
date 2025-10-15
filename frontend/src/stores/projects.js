import { defineStore } from 'pinia'
import api from '@/services/api'

export const useProjectsStore = defineStore('projects', {
  state: () => ({
    projects: [],
    currentProject: null,
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    },
    loading: false,
    error: null
  }),

  getters: {
    getProjectById: (state) => (id) => {
      return state.projects.find(project => project.id === id)
    },
    hasProjects: (state) => state.projects.length > 0
  },

  actions: {
    async fetchProjects(page = 1, perPage = 10, filters = {}) {
      this.loading = true
      this.error = null

      try {
        const params = {
          page,
          per_page: perPage,
          ...filters
        }

        const response = await api.get('/admin/projects', { params })

        this.projects = response.data.data
        this.pagination = {
          current_page: response.data.meta.current_page,
          last_page: response.data.meta.last_page,
          per_page: response.data.meta.per_page,
          total: response.data.meta.total
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch projects'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchProject(id) {
      this.loading = true
      this.error = null

      try {
        const response = await api.get(`/admin/projects/${id}`)
        this.currentProject = response.data.data
        return this.currentProject
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch project'
        throw error
      } finally {
        this.loading = false
      }
    },

    async createProject(projectData) {
      this.loading = true
      this.error = null

      try {
        const response = await api.post('/admin/projects', projectData)

        if (this.projects.length < this.pagination.per_page) {
          this.projects.unshift(response.data.data)
        }

        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create project'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateProject(id, projectData) {
      this.loading = true
      this.error = null

      try {
        // Use POST with _method=PUT for FormData compatibility
        const response = await api.post(`/admin/projects/${id}`, projectData)

        const index = this.projects.findIndex(p => p.id === id)
        if (index !== -1) {
          this.projects[index] = response.data.data
        }

        if (this.currentProject?.id === id) {
          this.currentProject = response.data.data
        }

        return response.data.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update project'
        throw error
      } finally {
        this.loading = false
      }
    },

    async deleteProject(id) {
      this.loading = true
      this.error = null

      try {
        await api.delete(`/admin/projects/${id}`)

        this.projects = this.projects.filter(p => p.id !== id)

        if (this.currentProject?.id === id) {
          this.currentProject = null
        }

        if (this.pagination.total > 0) {
          this.pagination.total--
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete project'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearCurrentProject() {
      this.currentProject = null
    },

    clearError() {
      this.error = null
    }
  }
})
