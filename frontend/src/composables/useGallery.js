import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function useGallery(initialParams = {}) {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const queryParams = ref(initialParams)
  const selectedGalleryId = ref(null)
  
  // State untuk instant data dari localStorage
  const cachedGalleries = ref(null)
  const cachedGalleryItems = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
    cachedGalleries.value = getCache(cacheKey)
    
    if (cachedGalleries.value) {
      console.log('[useGallery] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all galleries with caching (10min stale / 1hr cache)
  const {
    data: galleriesData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['galleries', queryParams],
    queryFn: async () => {
      const response = await api.get('/galleries', { params: queryParams.value })
      console.log('[useGallery] Background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, response.data, 10 * 60 * 1000) // 10min
      
      return response.data
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      return getCache(cacheKey)
    }
  })

  // Fetch gallery items with TanStack Query (enabled only when selectedGalleryId is set)
  const {
    data: galleryItemsData,
    isLoading: isLoadingItems,
    error: itemsError,
    refetch: refetchItems
  } = useQuery({
    queryKey: ['gallery-items', selectedGalleryId],
    queryFn: async () => {
      if (!selectedGalleryId.value) return null
      
      console.log('[useGallery] Background fetch items for:', selectedGalleryId.value)
      const response = await api.get(`/galleries/${selectedGalleryId.value}/items`)
      
      if (response.data.success) {
        const items = response.data.data
        console.log('[useGallery] Items cached:', items.length)
        
        // Update localStorage
        const cacheKey = `gallery_items_${selectedGalleryId.value}`
        setCache(cacheKey, items, 10 * 60 * 1000) // 10min
        
        return items
      }
      return []
    },
    enabled: computed(() => !!selectedGalleryId.value),
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      if (!selectedGalleryId.value) return null
      const cacheKey = `gallery_items_${selectedGalleryId.value}`
      const cached = getCache(cacheKey)
      if (cached) {
        console.log('[useGallery] âš¡ INSTANT items from localStorage:', selectedGalleryId.value)
      }
      return cached
    }
  })

  // Computed values - prefer localStorage instant data
  const galleries = computed(() => {
    if (isLoading.value && cachedGalleries.value) {
      // Instant display while loading
      return cachedGalleries.value.data || []
    }
    return galleriesData.value?.data || []
  })
  
  const loading = computed(() => isLoading.value)
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)
  
  const galleryItems = computed(() => {
    if (isLoadingItems.value && cachedGalleryItems.value) {
      // Instant display while loading
      return cachedGalleryItems.value
    }
    return galleryItemsData.value || []
  })
  
  const loadingItems = computed(() => isLoadingItems.value)

  // Fetch galleries with params
  const fetchGalleries = async (params = {}) => {
    queryParams.value = params
    
    // Check instant cache
    const cacheKey = `galleries_${JSON.stringify(params)}`
    cachedGalleries.value = getCache(cacheKey)
    
    const result = await refetch()
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single gallery with items (dengan caching individual)
  const fetchGallery = async (id) => {
    const cacheKey = `gallery_${id}`
    
    // Check localStorage first
    const cached = getCache(cacheKey)
    if (cached) {
      console.log('[useGallery] âš¡ INSTANT gallery from localStorage:', id)
      return { success: true, data: cached }
    }

    // Check TanStack Query cache
    const queryCache = queryClient.getQueryData(['gallery', id])
    if (queryCache) {
      console.log('[useGallery] TanStack Query cache HIT:', id)
      return { success: true, data: queryCache.data }
    }

    try {
      console.log('[useGallery] Cache MISS for gallery:', id, '- fetching...')
      const response = await api.get(`/galleries/${id}`)
      const data = response.data.data
      
      // Cache the result
      setCache(cacheKey, data, 10 * 60 * 1000) // 10min
      queryClient.setQueryData(['gallery', id], response.data, {
        staleTime: 10 * 60 * 1000,
        gcTime: 60 * 60 * 1000
      })

      return { success: true, data }
    } catch (err) {
      console.error('[useGallery] Fetch gallery error:', err)
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch gallery'
      }
    }
  }

  // Fetch gallery items (TanStack Query managed with localStorage)
  const fetchGalleryItems = async (galleryId) => {
    const cacheKey = `gallery_items_${galleryId}`
    
    // Check instant localStorage
    cachedGalleryItems.value = getCache(cacheKey)
    
    // Check TanStack Query cache
    const queryCache = queryClient.getQueryData(['gallery-items', galleryId])
    if (queryCache) {
      console.log('[useGallery] TanStack Query cache HIT for items:', galleryId)
      return queryCache
    }

    // If no cache at all, show we're fetching
    if (!cachedGalleryItems.value) {
      console.log('[useGallery] Cache MISS for items:', galleryId, '- fetching...')
    } else {
      console.log('[useGallery] âš¡ INSTANT from localStorage, background refresh...')
    }
    
    // Set gallery ID to trigger query
    selectedGalleryId.value = galleryId
    
    // Wait for query to complete
    await refetchItems()
    
    return galleryItems.value
  }

  // Pagination state
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0
  })

  // Fetch gallery item (alias for fetchGallery)
  const fetchGalleryItem = fetchGallery

  // Upload single image
  const uploadImage = async (formData) => {
    try {
      const response = await api.post('/admin/galleries', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      
      // Invalidate cache
      await refetch()
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
      
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Upload failed'
      }
    }
  }

  // Bulk upload images
  const bulkUploadImages = async (formData) => {
    try {
      const response = await api.post('/admin/galleries/bulk-upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      
      // Invalidate cache
      await refetch()
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
      
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Bulk upload failed'
      }
    }
  }

  // Update gallery item
  const updateGalleryItem = async (id, formData) => {
    try {
      const response = await api.post(`/admin/galleries/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      
      queryClient.invalidateQueries(['gallery', id])
      await refetch()
      
      // Clear related caches
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
      setCache(`gallery_${id}`, null, 0)
      
      return { success: true, data: response.data.data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Update failed'
      }
    }
  }

  // Delete gallery item
  const deleteGalleryItem = async (id) => {
    try {
      await api.delete(`/admin/galleries/${id}`)
      
      queryClient.invalidateQueries(['gallery', id])
      await refetch()
      
      // Clear related caches
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
      setCache(`gallery_${id}`, null, 0)
      
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Delete failed'
      }
    }
  }

  // Bulk delete gallery items
  const bulkDeleteGalleryItems = async (ids) => {
    try {
      await Promise.all(ids.map(id => api.delete(`/admin/galleries/${id}`)))
      
      ids.forEach(id => {
        queryClient.invalidateQueries(['gallery', id])
        setCache(`gallery_${id}`, null, 0)
      })
      await refetch()
      
      // Clear main cache
      const cacheKey = `galleries_${JSON.stringify(queryParams.value)}`
      setCache(cacheKey, null, 0)
      
      return { success: true }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || 'Bulk delete failed'
      }
    }
  }

  return {
    // State
    galleries,
    gallery: ref(null),
    loading,
    error,
    galleryItems,
    loadingItems,
    pagination,

    // Methods
    fetchGalleries,
    fetchGallery,
    fetchGalleryItem,
    fetchGalleryItems,
    uploadImage,
    bulkUploadImages,
    updateGalleryItem,
    deleteGalleryItem,
    bulkDeleteGalleryItems
  }
}
