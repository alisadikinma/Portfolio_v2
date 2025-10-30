import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function usePosts(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)

  // Fetch all posts with caching (5min stale / 30min cache)
  const {
    data: postsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['posts', queryParams],
    queryFn: async () => {
      const response = await api.get('/posts', { params: queryParams.value })
      return response.data
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    gcTime: 30 * 60 * 1000 // 30 minutes
  })

  // Computed values for backward compatibility
  const posts = computed(() => postsData.value?.data || [])
  const pagination = computed(() => {
    const meta = postsData.value?.meta
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

  // Fetch posts with params
  const fetchPosts = async (params = {}) => {
    queryParams.value = params
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single post
  const fetchPost = async (slug, lang = 'en') => {
    try {
      const response = await api.get(`/posts/${slug}`, { params: { lang } })
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch post'
      }
    }
  }

  // Fetch categories
  const fetchCategories = async () => {
    try {
      const response = await api.get('/categories')
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch categories'
      }
    }
  }

  // Fetch category
  const fetchCategory = async (slug, params = {}) => {
    try {
      const response = await api.get(`/categories/${slug}`, { params })
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch category'
      }
    }
  }

  // Create post mutation
  const createPostMutation = useMutation({
    mutationFn: async (postData) => {
      const response = await api.post('/admin/posts', postData)
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['posts'] })
    }
  })

  // Update post mutation
  const updatePostMutation = useMutation({
    mutationFn: async ({ id, data }) => {
      const response = await api.put(`/admin/posts/${id}`, data)
      return response.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['posts'] })
    }
  })

  // Delete post mutation
  const deletePostMutation = useMutation({
    mutationFn: async (id) => {
      await api.delete(`/admin/posts/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['posts'] })
    }
  })

  // Backward compatible mutation wrappers
  const createPost = async (postData) => {
    try {
      const data = await createPostMutation.mutateAsync(postData)
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to create post'
      }
    }
  }

  const updatePost = async (id, postData) => {
    try {
      const data = await updatePostMutation.mutateAsync({ id, data: postData })
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to update post'
      }
    }
  }

  const deletePost = async (id) => {
    try {
      await deletePostMutation.mutateAsync(id)
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to delete post'
      }
    }
  }

  return {
    // State - computed for reactivity
    posts,
    post: ref(null),
    categories: ref([]),
    category: ref(null),
    isLoading,
    error,
    pagination,

    // Methods - backward compatible
    fetchPosts,
    fetchPost,
    fetchCategories,
    fetchCategory,
    createPost,
    updatePost,
    deletePost
  }
}
