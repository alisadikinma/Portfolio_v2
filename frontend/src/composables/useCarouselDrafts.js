import { ref } from 'vue'
import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api'

export function useCarouselDrafts() {
  const loading = ref(false)
  const error = ref(null)

  const axiosInstance = axios.create({
    baseURL: API_BASE,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  })

  // Add auth token if available
  axiosInstance.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  })

  const listDrafts = async (filters = {}) => {
    loading.value = true
    error.value = null
    try {
      const params = new URLSearchParams()
      if (filters.status) params.append('status', filters.status)
      if (filters.target_platform) params.append('target_platform', filters.target_platform)
      if (filters.per_page) params.append('per_page', filters.per_page)
      if (filters.page) params.append('page', filters.page)

      const response = await axiosInstance.get('/admin/carousel-drafts', { params })
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || e.message
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const getDraft = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.get(`/admin/carousel-drafts/${id}`)
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || e.message
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const approveDraft = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.patch(`/admin/carousel-drafts/${id}/approve`)
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || e.message
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const rejectDraft = async (id, rejectionReason) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.patch(`/admin/carousel-drafts/${id}/reject`, {
        rejection_reason: rejectionReason,
      })
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || e.message
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const scheduleDraft = async (id, scheduledAt) => {
    loading.value = true
    error.value = null
    try {
      const response = await axiosInstance.patch(`/admin/carousel-drafts/${id}/schedule`, {
        scheduled_at: scheduledAt,
      })
      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || e.message
      throw error.value
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    listDrafts,
    getDraft,
    approveDraft,
    rejectDraft,
    scheduleDraft,
  }
}
