import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api/v1'

export const useAwardsStore = defineStore('awards', () => {
  // State
  const awards = ref([])
  const currentAward = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1
  })

  // Getters
  const getAwardById = computed(() => {
    return (id) => awards.value.find(award => award.id === id)
  })

  const totalAwards = computed(() => pagination.value.total)

  // Actions
  async function fetchAwards(params = {}) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/awards`, {
        params: {
          page: params.page || pagination.value.currentPage,
          per_page: params.perPage || pagination.value.perPage,
          search: params.search,
          year: params.year,
          ...params
        }
      })

      awards.value = response.data.data.data || response.data.data

      if (response.data.data.meta) {
        pagination.value = {
          currentPage: response.data.data.meta.current_page,
          perPage: response.data.data.meta.per_page,
          total: response.data.data.meta.total,
          lastPage: response.data.data.meta.last_page
        }
      }

      return awards.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch awards'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchAward(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/awards/${id}`)
      currentAward.value = response.data.data
      return currentAward.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch award'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createAward(awardData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(
        `${API_BASE_URL}/admin/awards`,
        awardData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const newAward = response.data.data
      awards.value.unshift(newAward)
      pagination.value.total++

      return newAward
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create award'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateAward(id, awardData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/awards/${id}`,
        awardData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      const updatedAward = response.data.data
      const index = awards.value.findIndex(a => a.id === id)
      if (index !== -1) {
        awards.value[index] = updatedAward
      }

      if (currentAward.value?.id === id) {
        currentAward.value = updatedAward
      }

      return updatedAward
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update award'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteAward(id) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`${API_BASE_URL}/admin/awards/${id}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      })

      awards.value = awards.value.filter(a => a.id !== id)
      pagination.value.total--

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete award'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function linkGallery(awardId, galleryId, sortOrder = 0) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(
        `${API_BASE_URL}/admin/awards/${awardId}/galleries`,
        { gallery_id: galleryId, sort_order: sortOrder },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to link gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function unlinkGallery(awardId, galleryId) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(
        `${API_BASE_URL}/admin/awards/${awardId}/galleries/${galleryId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      )

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to unlink gallery'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function reorderGalleries(awardId, galleries) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/awards/${awardId}/galleries/reorder`,
        { galleries },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to reorder galleries'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function resetStore() {
    awards.value = []
    currentAward.value = null
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
    awards,
    currentAward,
    loading,
    error,
    pagination,

    // Getters
    getAwardById,
    totalAwards,

    // Actions
    fetchAwards,
    fetchAward,
    createAward,
    updateAward,
    deleteAward,
    linkGallery,
    unlinkGallery,
    reorderGalleries,
    clearError,
    resetStore
  }
})
