import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useProjects(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)
  const selectedProjectSlug = ref(null)

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
      console.log('[useProjects] TanStack Query - Fetching projects list')
      return response.data
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Fetch single project with TanStack Query (enabled only when slug is set)
  const {
    data: projectData,
    isLoading: isLoadingProject,
    error: projectError,
    refetch: refetchProject
  } = useQuery({
    queryKey: ['project', selectedProjectSlug],
    queryFn: async () => {
      if (!selectedProjectSlug.value) return null
      
      console.log('[useProjects] TanStack Query - Fetching project:', selectedProjectSlug.value)
      const response = await api.get(`/projects/${selectedProjectSlug.value}`)
      
      if (response.data.success) {
        console.log('[useProjects] Project loaded & cached:', selectedProjectSlug.value)
        return response.data.data
      }
      return null
    },
    enabled: computed(() => !!selectedProjectSlug.value),
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Computed values for backward compatibility
  const projects = computed(() => projectsData.value?.data || [])
  const project = computed(() => projectData.value || null)
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

  // Fetch single project (TanStack Query managed)
  const fetchProject = async (slug) => {
    // Check if already cached
    const cached = queryClient.getQueryData(['project', slug])
    if (cached) {
      console.log('[useProjects] ⚡ Cache HIT for project:', slug, '(INSTANT)')
      return { success: true, data: cached }
    }

    console.log('[useProjects] ⏳ Cache MISS for project:', slug, '- fetching via TanStack Query...')
    selectedProjectSlug.value = slug
    
    await refetchProject()
    
    return {
      success: !!project.value,
      data: project.value,
      error: projectError.value?.response?.data?.message
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
      queryClient.invalidateQueries({ queryKey: ['project'] })
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
    project,
    isLoading,
    isLoadingProject,
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
