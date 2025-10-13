import { ref } from 'vue'
import api from '@/services/api'

export function usePosts() {
  const posts = ref([])
  const post = ref(null)
  const categories = ref([])
  const category = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Fetch all posts
  const fetchPosts = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/posts', { params })
      posts.value = response.data.data

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
      error.value = err.response?.data?.message || 'Failed to fetch posts'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single post by slug
  const fetchPost = async (slug, lang = 'en') => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/posts/${slug}`, {
        params: { lang }
      })
      post.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch all categories
  const fetchCategories = async () => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/categories')
      categories.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch categories'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch category with posts
  const fetchCategory = async (slug, params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/categories/${slug}`, { params })
      category.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch category'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Create post (admin)
  const createPost = async (postData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/posts', postData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Update post (admin)
  const updatePost = async (id, postData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put(`/admin/posts/${id}`, postData)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Delete post (admin)
  const deletePost = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      await api.delete(`/admin/posts/${id}`)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete post'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    posts,
    post,
    categories,
    category,
    isLoading,
    error,
    pagination,

    // Methods
    fetchPosts,
    fetchPost,
    fetchCategories,
    fetchCategory,
    createPost,
    updatePost,
    deletePost
  }
}
