import { ref } from 'vue'
import api from '@/services/api'

export function useGallery() {
  const gallery = ref([])
  const galleryItem = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 12,
    total: 0,
    lastPage: 1
  })

  // Fetch gallery items
  const fetchGallery = async (params = {}) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/gallery', { params })
      gallery.value = response.data.data

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
      error.value = err.response?.data?.message || 'Failed to fetch gallery'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single gallery item
  const fetchGalleryItem = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/gallery/${id}`)
      galleryItem.value = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch gallery item'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Upload single image (admin)
  const uploadImage = async (formData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/gallery', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to upload image'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Bulk upload images (admin)
  const bulkUploadImages = async (formData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post('/admin/gallery/bulk-upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to upload images'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Update gallery item (admin)
  const updateGalleryItem = async (id, formData) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.put(`/admin/gallery/${id}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update gallery item'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Delete gallery item (admin)
  const deleteGalleryItem = async (id) => {
    isLoading.value = true
    error.value = null

    try {
      await api.delete(`/admin/gallery/${id}`)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete gallery item'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Bulk delete gallery items (admin)
  const bulkDeleteGalleryItems = async (ids) => {
    isLoading.value = true
    error.value = null

    try {
      await api.post('/admin/gallery/bulk-delete', { ids })
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete gallery items'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  return {
    // State
    gallery,
    galleryItem,
    isLoading,
    error,
    pagination,

    // Methods
    fetchGallery,
    fetchGalleryItem,
    uploadImage,
    bulkUploadImages,
    updateGalleryItem,
    deleteGalleryItem,
    bulkDeleteGalleryItems
  }
}
