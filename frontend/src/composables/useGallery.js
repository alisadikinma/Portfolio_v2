import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useGallery(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)

  // Fetch gallery items with caching (1hr stale / 1hr cache)
  const {
    data: galleryData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['gallery', queryParams],
    queryFn: async () => {
      const response = await api.get('/gallery', { params: queryParams.value })
      return response.data
    },
    staleTime: 60 * 60 * 1000, // 1 hour
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Computed values for backward compatibility
  const gallery = computed(() => {
    // Handle both success: true format and direct data format
    const data = galleryData.value?.data || galleryData.value || []
    return data
  })

  const pagination = computed(() => {
    const meta = galleryData.value?.meta
    return meta ? {
      currentPage: meta.current_page,
      perPage: meta.per_page,
      total: meta.total,
      lastPage: meta.last_page
    } : {
      currentPage: 1,
      perPage: 12,
      total: 0,
      lastPage: 1
    }
  })

  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch gallery with params
  const fetchGallery = async (params = {}) => {
    queryParams.value = params
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single gallery item
  const fetchGalleryItem = async (id) => {
    try {
      const response = await api.get(`/gallery/${id}`)
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch gallery item'
      }
    }
  }

  // Upload single image (admin)
  const uploadImage = async (formData) => {
    try {
      const response = await api.post('/admin/gallery', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      // Invalidate cache after mutation
      queryClient.invalidateQueries({ queryKey: ['gallery'] })
      return { success: true, data: response.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to upload image'
      }
    }
  }

  // Bulk upload images (admin)
  const bulkUploadImages = async (formData) => {
    try {
      const response = await api.post('/admin/gallery/bulk-upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      // Invalidate cache after mutation
      queryClient.invalidateQueries({ queryKey: ['gallery'] })
      return { success: true, data: response.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to upload images'
      }
    }
  }

  // Update gallery item (admin)
  const updateGalleryItem = async (id, formData) => {
    try {
      const response = await api.put(`/admin/gallery/${id}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      // Invalidate cache after mutation
      queryClient.invalidateQueries({ queryKey: ['gallery'] })
      return { success: true, data: response.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to update gallery item'
      }
    }
  }

  // Delete gallery item (admin)
  const deleteGalleryItem = async (id) => {
    try {
      await api.delete(`/admin/gallery/${id}`)
      // Invalidate cache after mutation
      queryClient.invalidateQueries({ queryKey: ['gallery'] })
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to delete gallery item'
      }
    }
  }

  // Bulk delete gallery items (admin)
  const bulkDeleteGalleryItems = async (ids) => {
    try {
      await api.post('/admin/gallery/bulk-delete', { ids })
      // Invalidate cache after mutation
      queryClient.invalidateQueries({ queryKey: ['gallery'] })
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to delete gallery items'
      }
    }
  }

  return {
    // State - computed for reactivity
    gallery,
    galleryItem: ref(null),
    isLoading,
    error,
    pagination,

    // Methods - backward compatible
    fetchGallery,
    fetchGalleryItem,
    uploadImage,
    bulkUploadImages,
    updateGalleryItem,
    deleteGalleryItem,
    bulkDeleteGalleryItems
  }
}
