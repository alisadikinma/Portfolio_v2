import { useCategoriesStore } from '@/stores/categories'
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

  const fetchCategory = async (slug) => {
    return await store.fetchCategory(slug)
  }

  return {
    categories,
    loading,
    error,
    fetchCategories,
    fetchCategory
  }
}
