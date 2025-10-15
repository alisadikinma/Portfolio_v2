/**
 * Blog posts store
 * @module stores/posts
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const usePostsStore = defineStore('posts', () => {
  // State
  const posts = ref([])
  const currentPost = ref(null)
  const featuredPosts = ref([])
  const pagination = ref({
    currentPage: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
  })
  const filters = ref({
    category: null,
    tag: null,
    search: '',
  })
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const hasMore = computed(() => pagination.value.currentPage < pagination.value.lastPage)
  const totalPosts = computed(() => pagination.value.total)

  // Actions

  /**
   * Fetch all posts with pagination
   * @param {Object} params - Query parameters
   */
  async function fetchPosts(params = {}) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const queryParams = {
        page: pagination.value.currentPage,
        per_page: pagination.value.perPage,
        ...filters.value,
        ...params,
      }

      console.log('[posts.js] Fetching with params:', queryParams)
      const response = await api.get('/admin/posts', { params: queryParams })
      console.log('[posts.js] Raw API response:', response)

      // Handle response structure
      const responseData = response.data || response
      console.log('[posts.js] Response data:', responseData)
      
      posts.value = responseData.data || []
      console.log('[posts.js] Posts extracted:', posts.value.length, 'items')
      
      // Update pagination if meta exists
      if (responseData.meta) {
        pagination.value = {
          currentPage: responseData.meta.current_page || 1,
          perPage: responseData.meta.per_page || 10,
          total: responseData.meta.total || 0,
          lastPage: responseData.meta.last_page || 1,
        }
      } else {
        // Fallback if no meta in response
        pagination.value = {
          currentPage: 1,
          perPage: 10,
          total: posts.value.length,
          lastPage: 1,
        }
      }
      console.log('[posts.js] Pagination updated:', pagination.value)

      return posts.value
    } catch (err) {
      console.error('[posts.js] Error fetching posts:', err)
      console.error('[posts.js] Error response:', err.response?.data)
      error.value = err.response?.data?.message || 'Failed to fetch posts'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single post by slug
   * @param {string} slug - Post slug
   */
  async function fetchPost(slug) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.get(`/posts/${slug}`)
      currentPost.value = response.data.data

      return currentPost.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch post'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single post by ID (admin endpoint)
   * @param {number} id - Post ID
   */
  async function fetchPostById(id) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.get(`/admin/posts/${id}`)
      currentPost.value = response.data.data

      return currentPost.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch post'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a new post
   * @param {Object} postData - Post data
   */
  async function createPost(postData) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.post('/admin/posts', postData)

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create post'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing post
   * @param {number} id - Post ID
   * @param {Object} postData - Post data
   */
  async function updatePost(id, postData) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.put(`/admin/posts/${id}`, postData)

      // Update in local state if exists
      const index = posts.value.findIndex(p => p.id === id)
      if (index !== -1) {
        posts.value[index] = response.data.data
      }

      if (currentPost.value?.id === id) {
        currentPost.value = response.data.data
      }

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update post'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a post
   * @param {number} id - Post ID
   */
  async function deletePost(id) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      await api.delete(`/admin/posts/${id}`)

      // Remove from local state
      posts.value = posts.value.filter(p => p.id !== id)

      if (currentPost.value?.id === id) {
        currentPost.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete post'
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
      tag: null,
      search: '',
    }
    pagination.value.currentPage = 1
  }

  /**
   * Reset store state
   */
  function reset() {
    posts.value = []
    currentPost.value = null
    featuredPosts.value = []
    pagination.value = {
      currentPage: 1,
      perPage: 10,
      total: 0,
      lastPage: 1,
    }
    clearFilters()
    loading.value = false
    error.value = null
  }

  return {
    // State
    posts,
    currentPost,
    featuredPosts,
    pagination,
    filters,
    loading,
    error,

    // Getters
    hasMore,
    totalPosts,

    // Actions
    fetchPosts,
    fetchPost,
    fetchPostById,
    createPost,
    updatePost,
    deletePost,
    setFilters,
    clearFilters,
    reset,
  }
})
