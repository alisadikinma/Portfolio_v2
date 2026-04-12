import { ref } from 'vue'
import api from '@/services/api'

export function useContentEngine() {
  const isLoading = ref(false)
  const error = ref(null)

  const request = async (method, url, data = null, params = null) => {
    isLoading.value = true
    error.value = null
    try {
      const config = {}
      if (params) config.params = params
      let response
      if (method === 'get') {
        response = await api.get(url, config)
      } else if (method === 'post') {
        response = await api.post(url, data, config)
      } else if (method === 'put') {
        response = await api.put(url, data, config)
      } else if (method === 'delete') {
        response = await api.delete(url, config)
      }
      return { success: true, data: response.data.data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'Request failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  const checkHealth = () => request('get', '/admin/content-engine/health')

  const listIdeas = (filters = {}) => {
    const params = {}
    if (filters.pillar) params.pillar = filters.pillar
    if (filters.status) params.status = filters.status
    if (filters.priority) params.priority = filters.priority
    if (filters.search) params.search = filters.search
    return request('get', '/admin/content-engine/ideas', null, params)
  }

  const createIdea = (data) => request('post', '/admin/content-engine/ideas', data)

  const updateIdea = (id, data) => request('put', `/admin/content-engine/ideas/${id}`, data)

  const deleteIdea = (id) => request('delete', `/admin/content-engine/ideas/${id}`)

  const archiveIdea = (id) => request('post', `/admin/content-engine/ideas/${id}/archive`)

  const restoreIdea = (id) => request('post', `/admin/content-engine/ideas/${id}/restore`)

  const revertToDraft = (id) => request('post', `/admin/content-engine/ideas/${id}/revert`)

  const pullTrending = (source = '') => {
    const params = source ? { source } : {}
    return request('get', '/admin/content-engine/trending', null, params)
  }

  const importTrending = (topics) => request('post', '/admin/content-engine/trending/import', { topics })

  const startResearch = (id, config) => request('post', `/admin/content-engine/ideas/${id}/research`, config)

  const getResearch = (id) => request('get', `/admin/content-engine/ideas/${id}/research`)

  const approveArticle = async (id, data = {}) => {
    return request('post', `/admin/content-engine/ideas/${id}/approve-article`, data)
  }

  const startImageGeneration = async (id, formData) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.post(`/admin/content-engine/ideas/${id}/generate-images`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to start image generation'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  const approveAndPublish = async (id) => {
    return request('post', `/admin/content-engine/ideas/${id}/publish`)
  }

  const listWorkflows = () => request('get', '/admin/content-engine/workflows')

  const getWorkflowStatus = (id) => request('get', `/admin/content-engine/workflows/${id}`)

  return {
    isLoading,
    error,
    checkHealth,
    listIdeas,
    createIdea,
    updateIdea,
    deleteIdea,
    archiveIdea,
    restoreIdea,
    revertToDraft,
    pullTrending,
    importTrending,
    startResearch,
    getResearch,
    approveArticle,
    startImageGeneration,
    approveAndPublish,
    listWorkflows,
    getWorkflowStatus,
  }
}
