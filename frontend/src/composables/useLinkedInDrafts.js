import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * LinkedIn admin draft management — TanStack Query composable.
 *
 * Mirrors the 30s staleTime + refetchOnMount:'always' convention from
 * usePageSections.js so operator actions (approve/cancel/publish-now)
 * are reflected on next navigation rather than stale-cached for 10min.
 *
 * API contract matches plugin design §4.5 + admin endpoints in routes/api.php.
 */

const LIST_KEY = 'linkedin-drafts'

function listDrafts(filters = {}) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.format) params.format = filters.format
  if (filters.scope) params.scope = filters.scope
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  return api.get('/admin/linkedin-drafts', { params }).then(r => r.data)
}

function getDraft(id) {
  return api.get(`/admin/linkedin-drafts/${id}`).then(r => r.data)
}

/**
 * List query. `filters` can be a ref — query re-fetches when filters change.
 *
 * Usage:
 *   const status = ref('awaiting_publish')
 *   const { drafts, pagination, isLoading } = useLinkedInDraftsList(
 *     computed(() => ({ status: status.value, scope: 'feed' }))
 *   )
 */
export function useLinkedInDraftsList(filters) {
  const query = useQuery({
    queryKey: [LIST_KEY, filters],
    queryFn: () => listDrafts(unref(filters) || {}),
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
 * Detail query. Polls every 3s when status is in-progress (pending_generation,
 * generating, validating) to reflect plugin progress in real time.
 */
export function useLinkedInDraft(id) {
  const query = useQuery({
    queryKey: [LIST_KEY, id],
    queryFn: () => getDraft(unref(id)),
    enabled: computed(() => !!unref(id)),
    staleTime: 30_000,
    refetchOnMount: 'always',
    refetchInterval: (q) => {
      const status = q.state.data?.data?.status
      return ['pending_generation', 'generating', 'validating'].includes(status) ? 3_000 : false
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

/** PUT /admin/linkedin-drafts/{id} — saves content edits */
export function useUpdateLinkedInDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }) =>
      api.put(`/admin/linkedin-drafts/${id}`, payload).then(r => r.data),
    onSuccess: (_, { id }) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/approve */
export function useApproveLinkedInDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/approve`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/cancel */
export function useCancelLinkedInDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/cancel`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/publish-now */
export function usePublishLinkedInDraftNow() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/publish-now`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/regenerate */
export function useRegenerateLinkedInDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

// ============================================================================
// OAuth + Settings composables
// ============================================================================

/** GET /admin/linkedin/account — list connected LinkedIn accounts + OAuth config state */
export function useLinkedInAccounts() {
  const query = useQuery({
    queryKey: ['linkedin-accounts'],
    queryFn: () => api.get('/admin/linkedin/account').then(r => r.data),
    staleTime: 30_000,
    refetchOnMount: 'always',
  })

  const oauthConfigured = computed(() => query.data.value?.data?.oauth_configured ?? false)
  const accounts = computed(() => query.data.value?.data?.accounts || [])
  const hasConnectedAccount = computed(() =>
    accounts.value.some(a => !a.is_access_token_expired)
  )

  return {
    oauthConfigured,
    accounts,
    hasConnectedAccount,
    isLoading: query.isLoading,
    refetch: query.refetch,
  }
}

/**
 * Resolve the display title for a draft's source blog post.
 *
 * The `posts` table has no `title` column — titles live in `post_translations`.
 * Controller eager-loads `post.translations` so this helper picks the English
 * translation (falling back to the first available translation, then slug).
 */
export function postTitle(draft) {
  const post = draft?.post
  if (!post) return `Post #${draft?.post_id ?? '?'}`
  const translations = Array.isArray(post.translations) ? post.translations : []
  const en = translations.find((t) => t.language === 'en')
  return en?.title
    || translations[0]?.title
    || post.slug
    || `Post #${post.id ?? draft?.post_id ?? '?'}`
}

/** Open the LinkedIn authorize URL in the current tab. Must be triggered
 *  from a user gesture (click) or popup blockers will intervene. */
export async function startLinkedInConnect() {
  const response = await api.get('/admin/linkedin/connect')
  const authorizeUrl = response.data?.data?.authorize_url
  if (!authorizeUrl) {
    throw new Error(response.data?.error?.message || 'Failed to get LinkedIn authorize URL')
  }
  window.location.href = authorizeUrl
}

/** DELETE /admin/linkedin/account/{id} */
export function useDisconnectLinkedInAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.delete(`/admin/linkedin/account/${id}`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['linkedin-accounts'] })
      qc.invalidateQueries({ queryKey: ['linkedin-settings'] })
    },
  })
}

/** POST /admin/linkedin/account/{id}/test */
export function useTestLinkedInConnection() {
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin/account/${id}/test`).then(r => r.data),
  })
}

/** GET + PUT /admin/settings/linkedin */
export function useLinkedInSettings() {
  const qc = useQueryClient()

  const query = useQuery({
    queryKey: ['linkedin-settings'],
    queryFn: () => api.get('/admin/settings/linkedin').then(r => r.data),
    staleTime: 30_000,
    refetchOnMount: 'always',
  })

  const settings = computed(() => query.data.value?.data || {})

  const mutation = useMutation({
    mutationFn: (payload) =>
      api.put('/admin/settings/linkedin', payload).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['linkedin-settings'] }),
  })

  return {
    settings,
    isLoading: query.isLoading,
    save: mutation.mutateAsync,
    isSaving: mutation.isPending,
    refetch: query.refetch,
  }
}
