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
      // TODO: Implement API call when backend is ready
      // const response = await postsApi.getAll(params)
      // posts.value = response.data.data

      // Mock data for now
      posts.value = []

      return posts.value
    } catch (err) {
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
      // TODO: Implement API call
      currentPost.value = null
      return currentPost.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch post'
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
    setFilters,
    clearFilters,
    reset,
  }
})
