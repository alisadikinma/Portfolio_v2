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
 * generating, validating) to reflect plugin progress in real time. Also polls
 * every 5s when this is a carousel draft with at least one slide whose
 * image_status is 'generating' or 'pending' — so the operator sees freshly-
 * rendered slide PNGs without manual refresh.
 */
export function useLinkedInDraft(id) {
  const query = useQuery({
    queryKey: [LIST_KEY, id],
    queryFn: () => getDraft(unref(id)),
    enabled: computed(() => !!unref(id)),
    staleTime: 30_000,
    refetchOnMount: 'always',
    refetchInterval: (q) => {
      const data = q.state.data?.data
      const status = data?.status
      if (['pending_generation', 'generating', 'validating'].includes(status)) {
        return 3_000
      }
      // Carousel slide image polling — 5s while any slide is mid-flight.
      // Webhooks land server-side; this poll picks them up on the client.
      const slides = Array.isArray(data?.carousel_slides) ? data.carousel_slides : []
      const anyMidFlight = slides.some(s =>
        s?.image_status === 'generating' || s?.image_status === 'pending'
      )
      return anyMidFlight ? 5_000 : false
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

/**
 * Streaming progress query for a draft. Mirrors useContentEngine's
 * getProgress pattern — returns {progress_percentage, current_step,
 * progress_log[], process_alive, images_pending, status, format,
 * last_error}.
 *
 * Polls every 3s while the BRIEF→VALIDATE pipeline is running
 * (process_alive=true), every 5s while only image rendering is in
 * flight (images_pending=true), otherwise stops.
 */
export function useLinkedInDraftProgress(id, options = {}) {
  const enabled = options.enabled ?? computed(() => !!unref(id))

  const query = useQuery({
    queryKey: [LIST_KEY, id, 'progress'],
    queryFn: () =>
      api.get(`/admin/linkedin-drafts/${unref(id)}/progress`).then(r => r.data),
    enabled,
    staleTime: 0, // progress is always "stale" when re-opened
    gcTime: 60_000,
    refetchInterval: (q) => {
      const data = q.state.data?.data
      if (!data) return 3_000
      if (data.process_alive) return 3_000
      if (data.images_pending) return 5_000
      return false
    },
  })

  const progress = computed(() => query.data.value?.data || {
    progress_percentage: 0,
    current_step: null,
    progress_log: [],
    process_alive: false,
    images_pending: false,
    status: null,
    format: null,
    last_error: null,
  })

  return {
    progress,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

/**
 * Optimistic helper: flip every slide to pending + clear errors locally so
 * the UI reflects the click instantly, before the network round-trip and
 * the queue worker pickup latency. Returns rollback context for onError.
 *
 * Used by the bulk re-render mutations. Single-slide retry uses a narrower
 * variant inline (only flips the one slide).
 */
async function optimisticResetAllSlides(qc, id) {
  await qc.cancelQueries({ queryKey: [LIST_KEY, id] })
  const previous = qc.getQueryData([LIST_KEY, id])
  if (previous?.data?.carousel_slides && Array.isArray(previous.data.carousel_slides)) {
    const nextSlides = previous.data.carousel_slides.map(s => ({
      ...s,
      image_status: 'pending',
      image_url: '',
      image_error: null,
      image_job_uuid: null,
    }))
    qc.setQueryData([LIST_KEY, id], {
      ...previous,
      data: {
        ...previous.data,
        carousel_slides: nextSlides,
        last_error: null,
      },
    })
  }
  return { previous }
}

/** POST /admin/linkedin-drafts/{id}/regenerate-images — bulk re-dispatch */
export function useRegenerateAllCarouselImages() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate-images`).then(r => r.data),
    onMutate: (id) => optimisticResetAllSlides(qc, id),
    onError: (_err, id, context) => {
      if (context?.previous) qc.setQueryData([LIST_KEY, id], context.previous)
    },
    onSuccess: (response, id) => {
      // Populate detail cache from the response so we don't pay another
      // round-trip after invalidation. List cache (queue/feed counts)
      // still gets invalidated via onSettled.
      if (response?.data) {
        const cur = qc.getQueryData([LIST_KEY, id])
        qc.setQueryData([LIST_KEY, id], { ...(cur || {}), data: response.data })
      }
    },
    onSettled: (_d, _e, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

/**
 * POST /admin/linkedin-drafts/{id}/rerender-images — render only.
 * Skips /carousel-gen entirely; uses existing carousel_slides image_prompts.
 * Operator escape hatch when /carousel-gen keeps failing on Sonnet output
 * truncation but the prompts in DB are still good.
 */
export function useRerenderImagesOnly() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/rerender-images`).then(r => r.data),
    onMutate: (id) => optimisticResetAllSlides(qc, id),
    onError: (_err, id, context) => {
      if (context?.previous) qc.setQueryData([LIST_KEY, id], context.previous)
    },
    onSuccess: (response, id) => {
      if (response?.data) {
        const cur = qc.getQueryData([LIST_KEY, id])
        qc.setQueryData([LIST_KEY, id], { ...(cur || {}), data: response.data })
      }
    },
    onSettled: (_d, _e, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

/**
 * POST /admin/linkedin-drafts/{id}/regenerate-caption — re-synth caption + hashtags
 * from existing slide content. Synchronous (~1s, pure PHP), so the mutation
 * resolves with the updated draft in the response. Carousel-only — text
 * format's caption IS the post body and can't be regenerated independently.
 */
export function useRegenerateCaption() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate-caption`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/slides/{slideIndex}/regenerate-image — single slide retry */
export function useRegenerateSlideImage() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, slideIndex }) =>
      api.post(`/admin/linkedin-drafts/${id}/slides/${slideIndex}/regenerate-image`).then(r => r.data),
    onMutate: async ({ id, slideIndex }) => {
      await qc.cancelQueries({ queryKey: [LIST_KEY, id] })
      const previous = qc.getQueryData([LIST_KEY, id])
      if (previous?.data?.carousel_slides && Array.isArray(previous.data.carousel_slides)) {
        const nextSlides = previous.data.carousel_slides.map((s, i) =>
          i === slideIndex
            ? { ...s, image_status: 'pending', image_url: '', image_error: null, image_job_uuid: null }
            : s
        )
        qc.setQueryData([LIST_KEY, id], {
          ...previous,
          data: { ...previous.data, carousel_slides: nextSlides },
        })
      }
      return { previous }
    },
    onError: (_err, { id }, context) => {
      if (context?.previous) qc.setQueryData([LIST_KEY, id], context.previous)
    },
    onSuccess: (response, { id }) => {
      if (response?.data) {
        const cur = qc.getQueryData([LIST_KEY, id])
        qc.setQueryData([LIST_KEY, id], { ...(cur || {}), data: response.data })
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
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

/**
 * POST /admin/linkedin-drafts/{id}/approve
 *
 * mutate(id) → fires after default cancel_window_minutes
 * mutate({ id, publishAt }) → fires at the exact ISO datetime
 *
 * For drafts already in awaiting_publish, this endpoint reschedules
 * (no FSM transition, just updates cancel_window_ends_at).
 */
export function useApproveLinkedInDraft() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input) => {
      const id = typeof input === 'object' ? input.id : input
      const publishAt = typeof input === 'object' ? input.publishAt : undefined
      const payload = publishAt ? { publish_at: publishAt } : {}
      return api.post(`/admin/linkedin-drafts/${id}/approve`, payload).then(r => r.data)
    },
    onSuccess: (_, input) => {
      const id = typeof input === 'object' ? input.id : input
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

/**
 * POST /admin/linkedin-drafts/{id}/publish-now
 *
 * Carousel publishes upload N slide PNGs to LinkedIn DigitalMedia (3-step
 * register → fetch bytes → PUT) before the ugcPosts call — typically 30-60s
 * for 9 slides on a fresh upload. Override the global 15s axios timeout
 * for this endpoint only so the operator doesn't see a misleading "Network
 * error" while the backend keeps working (which then succeeds, leaving the
 * UI out of sync with the published draft).
 */
export function usePublishLinkedInDraftNow() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/publish-now`, {}, { timeout: 120000 })
        .then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * POST /admin/linkedin-drafts/{id}/publish-all
 *
 * Cascade: publishes LinkedIn + flips auto_approve_cross_posts so the
 * per-platform Generation jobs (FB/IG/TT/Threads) skip operator review and
 * fire Publer publish directly. Same long-timeout pattern as publish-now
 * since LinkedIn upload still happens synchronously (~30-60s for carousel).
 */
export function usePublishAllPlatforms() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/publish-all`, {}, { timeout: 120000 })
        .then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * POST /admin/linkedin-drafts/{id}/generate-threads
 *
 * One-off Threads cross-post sibling creation for a LinkedIn draft that
 * pre-dates the /threads-gen plugin (May 10, 2026). Fans out a single Threads
 * draft + dispatches generation. Returns 409 if Threads sibling already exists.
 *
 * Bulk equivalent: `php artisan linkedin:backfill-threads` (CLI only).
 */
export function useGenerateThreads() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/generate-threads`).then(r => r.data),
    onSuccess: (_, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/** POST /admin/linkedin-drafts/{id}/regenerate-instagram — reset IG sibling caption + dispatch (~30s) */
export function useRegenerateInstagram() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate-instagram`).then(r => r.data),
    onSuccess: (_, id) => qc.invalidateQueries({ queryKey: [LIST_KEY, id] }),
  })
}

/** POST /admin/linkedin-drafts/{id}/regenerate-tiktok — reset TikTok sibling caption + dispatch (~30s) */
export function useRegenerateTiktok() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate-tiktok`).then(r => r.data),
    onSuccess: (_, id) => qc.invalidateQueries({ queryKey: [LIST_KEY, id] }),
  })
}

/** POST /admin/linkedin-drafts/{id}/regenerate-threads — reset Threads sibling caption + dispatch (~30s) */
export function useRegenerateThreads() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api.post(`/admin/linkedin-drafts/${id}/regenerate-threads`).then(r => r.data),
    onSuccess: (_, id) => qc.invalidateQueries({ queryKey: [LIST_KEY, id] }),
  })
}

/**
 * GET /admin/linkedin-posts/calendar?from=&to=&status=
 *
 * Date-range query for the calendar month grid. Returns compact per-row shape
 * (id, status, format, post_title, scheduled_at, published_at, pin_at,
 * depth_score). Frontend buckets rows by `pin_at` day.
 *
 * `range` should be a ref/computed `{ from: 'YYYY-MM-DD', to: 'YYYY-MM-DD',
 * status?: 'awaiting_publish' }`. Query re-fetches when range changes.
 */
export function useLinkedInCalendar(range) {
  const query = useQuery({
    queryKey: ['linkedin-calendar', range],
    queryFn: async () => {
      const { from, to, status } = unref(range) || {}
      if (!from || !to) return { items: [], from: null, to: null, count: 0 }
      const params = { from, to }
      if (status) params.status = status
      const { data } = await api.get('/admin/linkedin-posts/calendar', { params })
      return data?.data ?? { items: [], from, to, count: 0 }
    },
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
  })

  return {
    items: computed(() => query.data.value?.items || []),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

/**
 * GET /admin/posting-rules?platform=linkedin
 *
 * Heatmap data for the calendar — rule scores per (day_of_week, hour) +
 * day_summaries (top 3 best_hours per day where score>=80). Long-cached
 * because rules only refresh quarterly via the artisan.
 */
export function usePostingRules(platform = 'linkedin') {
  const query = useQuery({
    queryKey: ['posting-rules', platform],
    queryFn: async () => {
      const p = unref(platform) || 'linkedin'
      const { data } = await api.get('/admin/posting-rules', { params: { platform: p } })
      return data?.data ?? null
    },
    staleTime: 1000 * 60 * 60 * 6, // 6h — survives operator session
    gcTime: 1000 * 60 * 60 * 12,
  })

  return {
    rules: computed(() => query.data.value?.rules || []),
    daySummaries: computed(() => query.data.value?.day_summaries || []),
    sources: computed(() => query.data.value?.sources || []),
    lastResearchedAt: computed(() => query.data.value?.last_researched_at || null),
    ruleCount: computed(() => query.data.value?.rule_count || 0),
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  }
}

/**
 * POST /admin/posting-rules/refresh body: {platform: linkedin}
 * Dispatches the research artisan via Artisan::queue. UI button gates spam.
 */
export function useRefreshPostingRules() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ platform }) => {
      const { data } = await api.post('/admin/posting-rules/refresh', { platform })
      return data?.data ?? data
    },
    onSuccess: () => {
      // Invalidate so the next index() call surfaces the fresh last_researched_at
      qc.invalidateQueries({ queryKey: ['posting-rules'] })
    },
  })
}

/**
 * POST /admin/linkedin-drafts/{id}/check-conflict
 *
 * Soft-warning lookup for the reschedule modal — debounce in the consumer
 * (~300ms) and fire on every datetime input change. Operator can still
 * proceed when has_conflict=true; this just surfaces the warning + suggests
 * AI-recommended alternatives sourced from the posting_time_rules table.
 *
 * Usage:
 *   const conflictMutation = useConflictCheck()
 *   const result = await conflictMutation.mutateAsync({ draftId: 12, at: isoString })
 *   // result: {has_conflict, conflicts[], suggested_alternatives[], window_minutes}
 */
export function useConflictCheck() {
  return useMutation({
    mutationFn: async ({ draftId, at }) => {
      const { data } = await api.post(`/admin/linkedin-drafts/${draftId}/check-conflict`, { at })
      return data?.data ?? data
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
