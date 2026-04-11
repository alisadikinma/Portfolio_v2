import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useCarouselDrafts } from '@/composables/useCarouselDrafts'

export const useCarouselDraftsStore = defineStore('carouselDrafts', () => {
  const drafts = ref([])
  const selectedDraft = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const pagination = ref({})

  const { listDrafts, getDraft, approveDraft, rejectDraft, scheduleDraft } = useCarouselDrafts()

  const getDraftById = computed(() => (id) => drafts.value.find(d => d.id === id))

  const getDraftsByStatus = computed(() => (status) => drafts.value.filter(d => d.status === status))

  const pendingGenerations = computed(() => {
    if (!selectedDraft.value) return []
    return selectedDraft.value.slides?.filter(s =>
      ['pending', 'processing'].includes(s.generation_status)
    ) || []
  })

  const fetchDrafts = async (filters = {}) => {
    loading.value = true
    error.value = null
    try {
      const response = await listDrafts(filters)
      if (response.success) {
        drafts.value = response.data.data
        pagination.value = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        }
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  const fetchDraft = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await getDraft(id)
      if (response.success) {
        selectedDraft.value = response.data
        const idx = drafts.value.findIndex(d => d.id === id)
        if (idx >= 0) {
          drafts.value[idx] = response.data
        } else {
          drafts.value.push(response.data)
        }
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  const approveDraftAction = async (id) => {
    loading.value = true
    error.value = null
    try {
      const response = await approveDraft(id)
      if (response.success) {
        selectedDraft.value = response.data
        const idx = drafts.value.findIndex(d => d.id === id)
        if (idx >= 0) {
          drafts.value[idx] = response.data
        }
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  const rejectDraftAction = async (id, reason) => {
    loading.value = true
    error.value = null
    try {
      const response = await rejectDraft(id, reason)
      if (response.success) {
        selectedDraft.value = response.data
        const idx = drafts.value.findIndex(d => d.id === id)
        if (idx >= 0) {
          drafts.value[idx] = response.data
        }
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  const scheduleDraftAction = async (id, date) => {
    loading.value = true
    error.value = null
    try {
      const response = await scheduleDraft(id, date)
      if (response.success) {
        selectedDraft.value = response.data
        const idx = drafts.value.findIndex(d => d.id === id)
        if (idx >= 0) {
          drafts.value[idx] = response.data
        }
      }
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  return {
    drafts,
    selectedDraft,
    loading,
    error,
    pagination,
    getDraftById,
    getDraftsByStatus,
    pendingGenerations,
    fetchDrafts,
    fetchDraft,
    approveDraftAction,
    rejectDraftAction,
    scheduleDraftAction,
  }
})
