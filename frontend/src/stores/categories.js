/**
 * Blog categories store
 * @module stores/categories
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCategoriesStore = defineStore('categories', () => {
  // State
  const categories = ref([])
  const currentCategory = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const categoriesOptions = computed(() =>
    categories.value.map(cat => ({
      value: cat.id,
      label: cat.name,
      slug: cat.slug,
    }))
  )

  const getCategoryBySlug = computed(() => (slug) =>
    categories.value.find(cat => cat.slug === slug)
  )

  // Actions

  /**
   * Fetch all categories
   */
  async function fetchCategories() {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.get('/categories')
      categories.value = response.data.data

      return categories.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch categories'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single category by slug
   * @param {string} slug - Category slug
   */
  async function fetchCategory(slug) {
    loading.value = true
    error.value = null

    try {
      const { useApi } = await import('../composables/useApi')
      const api = useApi()

      const response = await api.get(`/categories/${slug}`)
      currentCategory.value = response.data.data

      return currentCategory.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch category'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Reset store state
   */
  function reset() {
    categories.value = []
    currentCategory.value = null
    loading.value = false
    error.value = null
  }

  return {
    // State
    categories,
    currentCategory,
    loading,
    error,

    // Getters
    categoriesOptions,
    getCategoryBySlug,

    // Actions
    fetchCategories,
    fetchCategory,
    reset,
  }
})
