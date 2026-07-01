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
    // Trending aggregates Google News RSS + Google Trends JSON + optional
    // Instagram — several serial external fetches that occasionally push past
    // the global 30s axios default, especially on first-hit cold caches.
    // Give it 90s so a slow upstream doesn't surface as "timeout of 30000ms".
    return request('get', '/admin/content-engine/trending', null, params, { timeout: 90000 })
  }

  const scoreBatchTrending = (source = '') => {
    const body = source ? { source } : {}
    // Batch virality scoring runs 3 sequential Sonnet sync calls
    // (60 topics ÷ batch of 20) on top of ~15s of external feed aggregation.
    // Real-world latency lands at 105-135s — production nginx logs status
    // 499 (client closed) at exactly 119s, meaning axios was bailing one
    // batch before Sonnet finished. 240s gives ~80s headroom over the
    // observed worst case without sitting on a request indefinitely.
    return request('post', '/admin/content-engine/trending/score-batch', body, null, { timeout: 240000 })
  }

  // Direct call (not via request() wrapper) so the response carries
  // `skipped[]` + `threshold` fields surfaced by the backend's virality
  // gate. The shared wrapper strips everything except {success,data,message}.
  const importTrending = async (topics) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.post('/admin/content-engine/trending/import', { topics })
      return {
        success: true,
        data: response.data.data,
        skipped: response.data.skipped || [],
        threshold: response.data.threshold,
        message: response.data.message,
      }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'Request failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  const startResearch = (id, config) => request('post', `/admin/content-engine/ideas/${id}/research`, config)

  const getResearch = (id) => request('get', `/admin/content-engine/ideas/${id}/research`)

  const getProgress = (id) => request('get', `/admin/content-engine/ideas/${id}/progress`)

  const runDeepScore = (id) => request('post', `/admin/content-engine/ideas/${id}/run-deep-score`)

  const saveDraft = (id, data) => request('put', `/admin/content-engine/ideas/${id}/save-draft`, data)

  const generateSegmentImage = (id, segmentData) => request('post', `/admin/content-engine/ideas/${id}/generate-segment-image`, segmentData)

  // Fast-path retry for a failed segment. Reuses cached prompt + refs,
  // no plugin NER rerun. 409 when retry cap (3) reached — surface to user
  // so they can choose Skip or Regenerate Prompt instead.
  const retrySegment = (id, segmentIndex) =>
    request('post', `/admin/content-engine/ideas/${id}/retry-segment/${segmentIndex}`)

  // Manual skip. `force` required for cover (segment 0) — backend returns
  // 409 COVER_SKIP_REQUIRES_FORCE so the UI can surface a confirm dialog.
  const skipSegment = (id, segmentIndex, { force = false } = {}) =>
    request('post', `/admin/content-engine/ideas/${id}/skip-segment/${segmentIndex}`, { force })

  const rewriteSegmentVd = (id, segmentIndex, faceRefUrl) =>
    request('post', `/admin/content-engine/ideas/${id}/rewrite-vd`, {
      segment_index: segmentIndex,
      face_ref_url: faceRefUrl,
    })

  // Kicks off translation async (backend returns 202 + dispatches a queued job).
  // Returns instantly; caller polls getIdea for generated_article.translation_status.
  const translateArticle = (id) => request(
    'post',
    `/admin/content-engine/ideas/${id}/translate-article`,
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

  // Regen Prompt = backend SSH-dispatches /article-images via Claude CLI
  // (background nohup). Dispatch itself just opens SSH + writes runner files
  // + reads pidfile, but cold SSH connect can run 5-30s. Default 30s axios
  // timeout collides with the slow path even when backend successfully
  // queued the job — symptom: "timeout of 30000ms exceeded" while the
  // skill actually started running. Same justification as translateArticle
  // and pullTrending which already lift the ceiling.
  const regenerateImagePrompts = (id, sections = []) =>
    request('post', `/admin/content-engine/ideas/${id}/regenerate-image-prompts`, { sections }, null, { timeout: 90000 })

  const resumeImagePipeline = (id) =>
    request('post', `/automation/content-ideas/${id}/continue-pipeline`, { phase: 'images_resume' })

  const resumePipeline = (id) =>
    request('post', `/admin/content-engine/ideas/${id}/resume`)

  const downloadStockImage = (url) =>
    request('post', '/admin/content-engine/download-stock-image', { url })

  const cleanupVariationImages = (urlsToDelete) =>
    request('post', '/admin/content-engine/cleanup-variation-images', { urls_to_delete: urlsToDelete })

  // Phase H: named-entity-aware cover flow
  const uploadEntityReference = async (id, entityName, entityType, file) => {
    isLoading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('entity_name', entityName)
      formData.append('entity_type', entityType)
      formData.append('file', file)

      const response = await api.post(
        `/admin/content-engine/ideas/${id}/upload-entity-reference`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      return { success: true, data: response.data.data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'Upload failed'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  const skipEntityReference = (id, entityName) =>
    request('post', `/admin/content-engine/ideas/${id}/skip-entity-reference`, {
      entity_name: entityName,
    })

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
    runDeepScore,
    saveDraft,
    generateSegmentImage,
    retrySegment,
    skipSegment,
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
    resumePipeline,
    downloadStockImage,
    cleanupVariationImages,
    uploadEntityReference,
    skipEntityReference,
    listWorkflows,
    getWorkflowStatus,
  }
}
