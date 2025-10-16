import { ref } from 'vue'
import api from '@/services/api'

export function useMenuItems() {
  const menuItems = ref([])
  const menuItem = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  // Fetch all menu items (admin)
  const fetchMenuItems = async () => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/admin/menu-items')
      menuItems.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch menu items'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch active menu items (public)
  const fetchActiveMenuItems = async () => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/menu-items')
      menuItems.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch active menu items'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Create menu item
  const createMenuItem = async (data) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/menu-items', data)
      menuItems.value.push(response.data.data)

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create menu item'
      return { success: false, error: error.value, errors: err.response?.data?.errors }
    } finally {
      isLoading.value = false
    }
  }

  // Update menu item
  const updateMenuItem = async (id, data) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put(`/admin/menu-items/${id}`, data)

      // Update in local array
      const index = menuItems.value.findIndex(item => item.id === id)
      if (index !== -1) {
        menuItems.value[index] = response.data.data
      }

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update menu item'
      return { success: false, error: error.value, errors: err.response?.data?.errors }
    } finally {
      isLoading.value = false
    }
  }

  // Delete menu item
  const deleteMenuItem = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      await api.delete(`/admin/menu-items/${id}`)

      // Remove from local array
      menuItems.value = menuItems.value.filter(item => item.id !== id)

      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete menu item'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Reorder menu items
  const reorderMenuItems = async (items) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put('/admin/menu-items/reorder', { items })
      menuItems.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to reorder menu items'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    menuItems,
    menuItem,
    isLoading,
    error,

    // Methods
    fetchMenuItems,
    fetchActiveMenuItems,
    createMenuItem,
    updateMenuItem,
    deleteMenuItem,
    reorderMenuItems
  }
}
