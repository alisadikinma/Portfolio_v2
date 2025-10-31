import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

export function useGallery(initialParams = {}) {
  const queryClient = useQueryClient()
  const queryParams = ref(initialParams)
  const selectedGalleryId = ref(null)

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
      console.log('[useGallery] TanStack Query - Fetching galleries:', response.data)
      return response.data
    },
    staleTime: 10 * 60 * 1000, // 10 minutes - data dianggap fresh
    gcTime: 60 * 60 * 1000 // 1 hour - cache disimpan
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
      
      console.log('[useGallery] TanStack Query - Fetching items for gallery:', selectedGalleryId.value)
      const response = await api.get(`/galleries/${selectedGalleryId.value}/items`)
      
      if (response.data.success) {
        console.log('[useGallery] Items loaded & cached:', response.data.data.length, 'items')
        return response.data.data
      }
      return []
    },
    enabled: computed(() => !!selectedGalleryId.value), // Only fetch when ID is set
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000 // 1 hour
  })

  // Computed values for backward compatibility
  const galleries = computed(() => galleriesData.value?.data || [])
  const loading = computed(() => isLoading.value)
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)
  const galleryItems = computed(() => galleryItemsData.value || [])
  const loadingItems = computed(() => isLoadingItems.value)

  // Fetch galleries with params
  const fetchGalleries = async (params = {}) => {
    queryParams.value = params
    const result = await refetch()
    console.log('[useGallery] Refetch result:', result)
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single gallery with items (dengan caching individual)
  const fetchGallery = async (id) => {
    try {
      // Check cache first
      const cached = queryClient.getQueryData(['gallery', id])
      if (cached) {
        console.log('[useGallery] Cache HIT for gallery:', id)
        return { success: true, data: cached.data }
      }

      console.log('[useGallery] Cache MISS for gallery:', id, '- fetching...')
      const response = await api.get(`/galleries/${id}`)
      
      // Cache the result
      queryClient.setQueryData(['gallery', id], response.data, {
        staleTime: 10 * 60 * 1000,
        gcTime: 60 * 60 * 1000
      })

      return { success: true, data: response.data.data }
    } catch (err) {
      console.error('[useGallery] Fetch gallery error:', err)
      return {
        success: false,
        error: err.response?.data?.message || 'Failed to fetch gallery'
      }
    }
  }

  // Fetch gallery items (TanStack Query managed)
  const fetchGalleryItems = async (galleryId) => {
    // Check if already cached
    const cached = queryClient.getQueryData(['gallery-items', galleryId])
    if (cached) {
      console.log('[useGallery] ⚡ Cache HIT for items:', galleryId, '-', cached.length, 'items (INSTANT)')
      return cached
    }

    // Set gallery ID to trigger query
    console.log('[useGallery] ⏳ Cache MISS for items:', galleryId, '- fetching via TanStack Query...')
    selectedGalleryId.value = galleryId
    
    // Wait for query to complete
    await refetchItems()
    
    return galleryItems.value
  }

  return {
    // State
    galleries,
    gallery: ref(null),
    loading,
    error,
    galleryItems,
    loadingItems,

    // Methods
    fetchGalleries,
    fetchGallery,
    fetchGalleryItems
  }
}
