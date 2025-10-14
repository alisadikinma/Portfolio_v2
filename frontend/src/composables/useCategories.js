import { ref } from 'vue'
import { useCategories as useCategoriesStore } from '@/stores/categories'
import { storeToRefs } from 'pinia'

export function useCategories() {
  const store = useCategoriesStore()
  const { categories, loading, error } = storeToRefs(store)

  const fetchCategories = async (force = false) => {
    if (categories.value.length > 0 && !force) {
      return categories.value
    }
    return await store.fetchCategories()
  }

  const fetchCategory = async (id) => {
    return await store.fetchCategory(id)
  }

  const createCategory = async (categoryData) => {
    return await store.createCategory(categoryData)
  }

  const updateCategory = async (id, categoryData) => {
    return await store.updateCategory(id, categoryData)
  }

  const deleteCategory = async (id) => {
    return await store.deleteCategory(id)
  }

  return {
    categories,
    loading,
    error,
    fetchCategories,
    fetchCategory,
    createCategory,
    updateCategory,
    deleteCategory
  }
}
