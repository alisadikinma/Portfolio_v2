import { ref, computed, onMounted } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function useProjects(initialParams = {}) {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const queryParams = ref(initialParams)
  const selectedProjectSlug = ref(null)
  
  // State untuk instant data dari localStorage
  const cachedProjects = ref(null)
  const cachedProject = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
    cachedProjects.value = getCache(cacheKey)
    
    if (cachedProjects.value) {
      console.log('[useProjects] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all projects with caching (10min stale / 1hr cache)
  const {
    data: projectsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['projects', queryParams],
    queryFn: async () => {
      // Use limit parameter to fetch all projects (skip pagination)
      const params = { ...queryParams.value, limit: 100 }
      const response = await api.get('/projects', { params })
      console.log('[useProjects] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, response.data, 10 * 60 * 1000) // 10min
      
      return response.data
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
      return getCache(cacheKey)
    }
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
      
      console.log('[useProjects] Background fetch project:', selectedProjectSlug.value)
      const response = await api.get(`/projects/${selectedProjectSlug.value}`)
      
      if (response.data.success) {
        console.log('[useProjects] Project cached:', selectedProjectSlug.value)
        
        // Update localStorage
        const cacheKey = `project_${selectedProjectSlug.value}`
        setCache(cacheKey, response.data.data, 10 * 60 * 1000) // 10min
        
        return response.data.data
      }
      return null
    },
    enabled: computed(() => !!selectedProjectSlug.value),
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      if (!selectedProjectSlug.value) return null
      const cacheKey = `project_${selectedProjectSlug.value}`
      const cached = getCache(cacheKey)
      if (cached) {
        console.log('[useProjects] âš¡ INSTANT project from localStorage:', selectedProjectSlug.value)
      }
      return cached
    }
  })

  // Computed values - prefer localStorage instant data, fallback to query data
  const projects = computed(() => {
    if (isLoading.value && cachedProjects.value) {
      // Instant display while loading
      return cachedProjects.value.data || []
    }
    return projectsData.value?.data || []
  })
  
  const project = computed(() => {
    if (isLoadingProject.value && cachedProject.value) {
      // Instant display while loading
      return cachedProject.value
    }
    return projectData.value || null
  })
  
  const pagination = computed(() => {
    const source = cachedProjects.value || projectsData.value
    const meta = source?.meta
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
    
    // Check instant cache
    const cacheKey = `projects_${JSON.stringify(params)}`
    cachedProjects.value = getCache(cacheKey)
    
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single project (with instant localStorage)
  const fetchProject = async (slug) => {
    const cacheKey = `project_${slug}`
    
    // Check instant localStorage
    cachedProject.value = getCache(cacheKey)
    
    // Check TanStack Query cache
    const queryCache = queryClient.getQueryData(['project', slug])
    if (queryCache) {
      console.log('[useProjects] TanStack Query cache HIT:', slug)
      return { success: true, data: queryCache }
    }

    // If no cache at all, show we're fetching
    if (!cachedProject.value) {
      console.log('[useProjects] Cache MISS for:', slug, '- fetching...')
    } else {
      console.log('[useProjects] âš¡ INSTANT from localStorage, background refresh...')
    }
    
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
      // Clear localStorage cache
      const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
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
      // Clear related caches
      const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
    }
  })

  // Delete project mutation
  const deleteProjectMutation = useMutation({
    mutationFn: async (slug) => {
      await api.delete(`/admin/projects/${slug}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
      const cacheKey = `projects_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
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
