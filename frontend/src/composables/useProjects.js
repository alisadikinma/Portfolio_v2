import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useProjects(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)

  // Fetch all projects with caching (10min stale / 1hr cache)
  const {
    data: projectsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['projects', queryParams],
    queryFn: async () => {
      const response = await api.get('/projects', { params: queryParams.value })
      return response.data
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Computed values for backward compatibility
  const projects = computed(() => projectsData.value?.data || [])
  const pagination = computed(() => {
    const meta = projectsData.value?.meta
    return meta ? {
      currentPage: meta.current_page,
      perPage: meta.per_page,
      total: meta.total,
      lastPage: meta.last_page
    } : {
      currentPage: 1,
      perPage: 15,
      total: 0,
      lastPage: 1
    }
  })
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch projects with params
  const fetchProjects = async (params = {}) => {
    queryParams.value = params
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single project
  const fetchProject = async (slug) => {
    try {
      const response = await api.get(`/projects/${slug}`)
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch project'
      }
    }
  }

  // Create project mutation
  const createProjectMutation = useMutation({
    mutationFn: async (projectData) => {
      const response = await api.post('/admin/projects', projectData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
    }
  })

  // Update project mutation
  const updateProjectMutation = useMutation({
    mutationFn: async ({ slug, data }) => {
      const response = await api.put(`/admin/projects/${slug}`, data, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
    }
  })

  // Delete project mutation
  const deleteProjectMutation = useMutation({
    mutationFn: async (slug) => {
      await api.delete(`/admin/projects/${slug}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
    }
  })

  // Backward compatible mutation wrappers
  const createProject = async (projectData) => {
    try {
      const data = await createProjectMutation.mutateAsync(projectData)
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to create project'
      }
    }
  }

  const updateProject = async (slug, projectData) => {
    try {
      const data = await updateProjectMutation.mutateAsync({ slug, data: projectData })
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to update project'
      }
    }
  }

  const deleteProject = async (slug) => {
    try {
      await deleteProjectMutation.mutateAsync(slug)
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to delete project'
      }
    }
  }

  return {
    // State
    projects,
    project: ref(null),
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
