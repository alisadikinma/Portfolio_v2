import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Generic cross-post admin draft composable. Works for Facebook,
 * Instagram, and TikTok via the `platform` argument (string).
 *
 * Hits `/admin/{platform}-drafts/*` endpoints provisioned by Phase E
 * controllers (HandlesCrossPostDraftActions trait). API contract is
 * uniform across platforms.
 *
 * Mirrors the 30s staleTime + refetchOnMount:'always' pattern from
 * useLinkedInDrafts so admin actions reflect on next navigation.
 */

const VALID_PLATFORMS = ['facebook', 'instagram', 'tiktok']

function ensurePlatform(platform) {
  const p = unref(platform)
  if (!VALID_PLATFORMS.includes(p)) {
    throw new Error(`useCrossPostDrafts: invalid platform '${p}' (expected facebook|instagram|tiktok)`)
  }
  return p
}

function listKey(platform) {
  return ['cross-post-drafts', platform]
}

function detailKey(platform, id) {
  return ['cross-post-drafts', platform, id]
}

function endpoint(platform, suffix = '') {
  return `/admin/${platform}-drafts${suffix}`
}

function listDrafts(platform, filters = {}) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.format) params.format = filters.format
  if (filters.scope) params.scope = filters.scope
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  return api.get(endpoint(platform), { params }).then(r => r.data)
}

function getDraft(platform, id) {
  return api.get(endpoint(platform, `/${id}`)).then(r => r.data)
}

/**
 * List query.
 * @param {string} platform - facebook | instagram | tiktok
 * @param {object|Ref<object>} filters - { status?, format?, scope?, per_page?, page? }
 */
export function useCrossPostDraftsList(platform, filters) {
  const p = ensurePlatform(platform)
  const query = useQuery({
    queryKey: [...listKey(p), filters],
    queryFn: () => listDrafts(p, unref(filters) || {}),
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
  })

  const drafts = computed(() => query.data.value?.data || [])
  const pagination = computed(() => query.data.value?.meta || null)

  return {
    drafts,
    pagination,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

/**
 * Detail query. Polls every 3s while the draft is in an in-progress
 * status (pending_generation, generating). FSM here is 7-state per
 * platform — see {Facebook,Instagram,Tiktok}PostStatus.
 */
export function useCrossPostDraft(platform, id) {
  const p = ensurePlatform(platform)
  const query = useQuery({
    queryKey: detailKey(p, id),
    queryFn: () => getDraft(p, unref(id)),
    enabled: computed(() => !!unref(id)),
    staleTime: 30_000,
    refetchOnMount: 'always',
    refetchInterval: (q) => {
      const status = q.state.data?.data?.status
      if (['pending_generation', 'generating', 'publishing'].includes(status)) {
        return 3_000
      }
      return false
    },
  })

  const draft = computed(() => query.data.value?.data || null)
  return {
    draft,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useApproveCrossPostDraft(platform) {
  const p = ensurePlatform(platform)
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(endpoint(p, `/${id}/approve`)).then(r => r.data),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: detailKey(p, id) })
      queryClient.invalidateQueries({ queryKey: listKey(p) })
    },
  })
}

export function useCancelCrossPostDraft(platform) {
  const p = ensurePlatform(platform)
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(endpoint(p, `/${id}/cancel`)).then(r => r.data),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: detailKey(p, id) })
      queryClient.invalidateQueries({ queryKey: listKey(p) })
    },
  })
}

export function useRegenerateCrossPostDraft(platform) {
  const p = ensurePlatform(platform)
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(endpoint(p, `/${id}/regenerate`)).then(r => r.data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: listKey(p) })
    },
  })
}

export function useUpdateCrossPostDraft(platform) {
  const p = ensurePlatform(platform)
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }) => api.put(endpoint(p, `/${id}`), payload).then(r => r.data),
    onSuccess: (_data, { id }) => {
      queryClient.invalidateQueries({ queryKey: detailKey(p, id) })
      queryClient.invalidateQueries({ queryKey: listKey(p) })
    },
  })
}
