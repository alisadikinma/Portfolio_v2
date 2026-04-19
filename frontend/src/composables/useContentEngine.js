import { ref } from 'vue'
import api from '@/services/api'

export function useContentEngine() {
  const isLoading = ref(false)
  const error = ref(null)

  const request = async (method, url, data = null, params = null, overrides = {}) => {
    isLoading.value = true
    error.value = null
    try {
      const config = { ...overrides }
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

  const getIdea = (id) => request('get', `/admin/content-engine/ideas/${id}`)

  const listIdeas = (filters = {}) => {
    const params = {}
    if (filters.pillar) params.pillar = filters.pillar
    if (filters.status) params.status = filters.status
    if (filters.priority) params.priority = filters.priority
    if (filters.search) params.search = filters.search
    if (filters.page) params.page = filters.page
    if (filters.per_page) params.per_page = filters.per_page
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

  const scoreBatchTrending = (source = '') => {
    const body = source ? { source } : {}
    return request('post', '/admin/content-engine/trending/score-batch', body)
  }

  const importTrending = (topics) => request('post', '/admin/content-engine/trending/import', { topics })

  const startResearch = (id, config) => request('post', `/admin/content-engine/ideas/${id}/research`, config)

  const getResearch = (id) => request('get', `/admin/content-engine/ideas/${id}/research`)

  const getProgress = (id) => request('get', `/admin/content-engine/ideas/${id}/progress`)

  const saveDraft = (id, data) => request('put', `/admin/content-engine/ideas/${id}/save-draft`, data)

  const generateSegmentImage = (id, segmentData) => request('post', `/admin/content-engine/ideas/${id}/generate-segment-image`, segmentData)

  const rewriteSegmentVd = (id, segmentIndex, faceRefUrl) =>
    request('post', `/admin/content-engine/ideas/${id}/rewrite-vd`, {
      segment_index: segmentIndex,
      face_ref_url: faceRefUrl,
    })

  // Translation runs SSH → Claude CLI; backend timeout is 300s (matches
  // nginx fastcgi_read_timeout). Frontend waits 320s for headroom.
  const translateArticle = (id) => request(
    'post',
    `/admin/content-engine/ideas/${id}/translate-article`,
    null,
    null,
    { timeout: 320000 }
  )

  const searchStockImages = (query, options = {}) => {
    const params = { q: query, ...options }
    return request('get', '/admin/content-engine/stock-images/search', null, params)
  }

  const approveArticle = async (id, data = {}) => {
    return request('post', `/admin/content-engine/ideas/${id}/approve-article`, data)
  }

  const regenerateArticle = async (id, data = {}) => {
    return request('post', `/admin/content-engine/ideas/${id}/regenerate`, data)
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

  const updateImageConcept = (id, sectionPosition, imageConcept) =>
    request('put', `/admin/content-engine/ideas/${id}/update-image-concept`, {
      section_position: sectionPosition,
      image_concept: imageConcept,
    })

  const regenerateImagePrompts = (id, sections = []) =>
    request('post', `/admin/content-engine/ideas/${id}/regenerate-image-prompts`, { sections })

  const resumeImagePipeline = (id) =>
    request('post', `/automation/content-ideas/${id}/continue-pipeline`, { phase: 'images_resume' })

  const downloadStockImage = (url) =>
    request('post', '/admin/content-engine/download-stock-image', { url })

  const cleanupVariationImages = (urlsToDelete) =>
    request('post', '/admin/content-engine/cleanup-variation-images', { urls_to_delete: urlsToDelete })

  const listWorkflows = () => request('get', '/admin/content-engine/workflows')

  const getWorkflowStatus = (id) => request('get', `/admin/content-engine/workflows/${id}`)

  return {
    isLoading,
    error,
    checkHealth,
    getIdea,
    listIdeas,
    createIdea,
    updateIdea,
    deleteIdea,
    archiveIdea,
    restoreIdea,
    revertToDraft,
    pullTrending,
    scoreBatchTrending,
    importTrending,
    startResearch,
    getResearch,
    getProgress,
    saveDraft,
    generateSegmentImage,
    rewriteSegmentVd,
    translateArticle,
    searchStockImages,
    approveArticle,
    regenerateArticle,
    startImageGeneration,
    approveAndPublish,
    updateImageConcept,
    regenerateImagePrompts,
    resumeImagePipeline,
    downloadStockImage,
    cleanupVariationImages,
    listWorkflows,
    getWorkflowStatus,
  }
}
