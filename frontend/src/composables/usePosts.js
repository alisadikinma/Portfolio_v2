import { ref, computed, onMounted } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function usePosts(initialParams = {}) {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const queryParams = ref(initialParams)
  const selectedPostSlug = ref(null)
  const selectedLang = ref('en')
  
  // State untuk instant data dari localStorage
  const cachedPosts = ref(null)
  const cachedPost = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
    cachedPosts.value = getCache(cacheKey)
    
    if (cachedPosts.value) {
      console.log('[usePosts] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all posts with caching (5min stale / 30min cache)
  const {
    data: postsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['posts', queryParams, selectedLang],
    queryFn: async () => {
      const response = await api.get('/posts', { params: { ...queryParams.value, lang: selectedLang.value } })
      console.log('[usePosts] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, response.data, 5 * 60 * 1000) // 5min
      
      return response.data
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    gcTime: 30 * 60 * 1000, // 30 minutes
    initialData: () => {
      // Try get from localStorage first
      const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
      return getCache(cacheKey)
    }
  })

  // Fetch single post with TanStack Query (enabled only when slug is set)
  const {
    data: postData,
    isLoading: isLoadingPost,
    error: postError,
    refetch: refetchPost
  } = useQuery({
    queryKey: ['post', selectedPostSlug, selectedLang],
    queryFn: async () => {
      if (!selectedPostSlug.value) return null
      
      console.log('[usePosts] Background fetch post:', selectedPostSlug.value)
      // Pass preview=1 if URL has ?preview=1 (allows viewing draft posts)
      const urlParams = new URLSearchParams(window.location.search)
      const params = { lang: selectedLang.value }
      if (urlParams.get('preview')) params.preview = 1

      const response = await api.get(`/posts/${selectedPostSlug.value}`, { params })

      // API returns { data: {...} } — extract the post data
      const postResult = response.data?.data || response.data
      if (postResult) {
        console.log('[usePosts] Post cached:', selectedPostSlug.value)

        // Update localStorage
        const cacheKey = `post_${selectedPostSlug.value}_${selectedLang.value}`
        setCache(cacheKey, postResult, 10 * 60 * 1000) // 10min

        return postResult
      }
      return null
    },
    enabled: computed(() => !!selectedPostSlug.value),
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      if (!selectedPostSlug.value) return null
      const cacheKey = `post_${selectedPostSlug.value}_${selectedLang.value}`
      const cached = getCache(cacheKey)
      if (cached) {
        console.log('[usePosts] âš¡ INSTANT post from localStorage:', selectedPostSlug.value)
      }
      return cached
    }
  })

  // Computed values - prefer localStorage instant data, fallback to query data
  const posts = computed(() => {
    if (isLoading.value && cachedPosts.value) {
      // Instant display while loading
      return cachedPosts.value.data || []
    }
    return postsData.value?.data || []
  })
  
  const post = computed(() => {
    if (isLoadingPost.value && cachedPost.value) {
      // Instant display while loading
      return cachedPost.value
    }
    return postData.value || null
  })
  
  const pagination = computed(() => {
    const source = cachedPosts.value || postsData.value
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

  // Fetch posts with params
  const fetchPosts = async (params = {}, lang = null) => {
    queryParams.value = params
    if (lang) selectedLang.value = lang

    // Check instant cache
    const cacheKey = `posts_${JSON.stringify(params)}_${selectedLang.value}`
    cachedPosts.value = getCache(cacheKey)
    
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single post
  const fetchPost = async (slug, lang = 'en') => {
    selectedPostSlug.value = slug
    selectedLang.value = lang

    // Invalidate stale cache for this slug+lang combo
    queryClient.invalidateQueries({ queryKey: ['post', slug, lang] })

    await refetchPost()

    return {
      success: !!post.value,
      data: post.value,
      error: postError.value?.response?.data?.message
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
      // Clear localStorage cache
      const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0) // Expire immediately
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
      queryClient.invalidateQueries({ queryKey: ['post'] })
      // Clear related caches
      const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
    }
  })

  // Delete post mutation
  const deletePostMutation = useMutation({
    mutationFn: async (id) => {
      await api.delete(`/admin/posts/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['posts'] })
      const cacheKey = `posts_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
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
    post,
    categories: ref([]),
    category: ref(null),
    isLoading,
    isLoadingPost,
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
