import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api/v1'

export const useGalleriesStore = defineStore('galleries', () => {
  // State
  const galleries = ref([])
  const currentGallery = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Getters
  const getGalleryById = computed(() => {
    return (id) => galleries.value.find(gallery => gallery.id === id)
  })

  const totalGalleries = computed(() => pagination.value.total)

  // Actions
  async function fetchGalleries(params = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/galleries`, {
        params: {
          page: params.page || pagination.value.currentPage,
          per_page: params.perPage || pagination.value.perPage,
          search: params.search,
          ...params
        }
      })

      galleries.value = response.data.data.data || response.data.data

      if (response.data.data.meta) {
        pagination.value = {
          currentPage: response.data.data.meta.current_page,
          perPage: response.data.data.meta.per_page,
          total: response.data.data.meta.total,
          lastPage: response.data.data.meta.last_page
        }
      }

      return galleries.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch galleries'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchGallery(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/galleries/${id}`)
      currentGallery.value = response.data.data
      return currentGallery.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createGallery(galleryData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(
        `${API_BASE_URL}/admin/galleries`,
        galleryData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const newGallery = response.data.data
      galleries.value.unshift(newGallery)
      pagination.value.total++

      return newGallery
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateGallery(id, galleryData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/galleries/${id}`,
        galleryData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const updatedGallery = response.data.data
      const index = galleries.value.findIndex(g => g.id === id)
      if (index !== -1) {
        galleries.value[index] = updatedGallery
      }

      if (currentGallery.value?.id === id) {
        currentGallery.value = updatedGallery
      }

      return updatedGallery
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteGallery(id) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`${API_BASE_URL}/admin/galleries/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })

      galleries.value = galleries.value.filter(g => g.id !== id)
      pagination.value.total--

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function resetStore() {
    galleries.value = []
    currentGallery.value = null
    loading.value = false
    error.value = null
    pagination.value = {
      currentPage: 1,
      perPage: 15,
      total: 0,
      lastPage: 1
    }
  }

  return {
    // State
    galleries,
    currentGallery,
    loading,
    error,
    pagination,

    // Getters
    getGalleryById,
    totalGalleries,

    // Actions
    fetchGalleries,
    fetchGallery,
    createGallery,
    updateGallery,
    deleteGallery,
    clearError,
    resetStore
  }
})
