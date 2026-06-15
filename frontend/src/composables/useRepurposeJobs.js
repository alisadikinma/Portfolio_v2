import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { isTerminal } from '@/views/admin/repurposeHelpers.js'

/**
 * IG-repurpose admin monitoring — TanStack Query composable.
 *
 * Mirrors useLinkedInDrafts.js conventions: 30s staleTime +
 * refetchOnMount:'always', with auto-poll while any job is non-terminal so the
 * operator sees live pipeline progress without manual refresh.
 *
 * Backend: RepurposeJobController (routes/api.php → /admin/repurpose).
 */

const LIST_KEY = 'repurpose-jobs'

function listJobs(filters = {}) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  // Social Studio opts in: hide IG jobs whose downstream carousel/blog has SETTLED —
  // scheduled into the Content Calendar OR published (mirrors the blog source's
  // scope=queue gate, which already drops awaiting_publish + published drafts).
  if (filters.exclude_settled) params.exclude_settled = 1
  return api.get('/admin/repurpose', { params }).then(r => r.data)
}

function getJob(id) {
  return api.get(`/admin/repurpose/${id}`).then(r => r.data)
}

export function useRepurposeJobsList(filters) {
  const query = useQuery({
    queryKey: [LIST_KEY, filters],
    queryFn: () => listJobs(unref(filters) || {}),
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
    refetchInterval: (q) => {
      const rows = q.state.data?.data || []
      // Poll while a job is mid-pipeline OR a `drafted` job's downstream
      // carousel is still re-authoring / rendering (so the list progress climbs).
      const active = rows.some(r =>
        !isTerminal(r.status) ||
        (r.status === 'drafted' && ['reauthoring', 'generating', 'pending', 'partial'].includes(r.render_state)),
      )
      return active ? 5_000 : false
    },
  })

  return {
    jobs: computed(() => query.data.value?.data || []),
    pagination: computed(() => query.data.value?.meta || null),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useRepurposeJob(id) {
  const query = useQuery({
    queryKey: [LIST_KEY, id],
    queryFn: () => getJob(unref(id)),
    enabled: computed(() => !!unref(id)),
    staleTime: 30_000,
    refetchOnMount: 'always',
    refetchInterval: (q) => {
      const job = q.state.data?.data
      const status = job?.status
      if (status && !isTerminal(status)) return 4_000
      // Keep polling while any video_rebrand slide is mid-flight even on a
      // `drafted` (terminal) job — a single-tool re-skin is FSM-neutral, so the
      // job status never leaves `drafted` while the slide re-composites.
      const slides = Array.isArray(job?.video_slides) ? job.video_slides : []
      const slideActive = slides.some(s =>
        ['pending', 'compositing'].includes(s.composited_status) ||
        ['pending', 'generating'].includes(s.keyframe_status) ||
        ['pending', 'generating'].includes(s.veo_status),
      )
      // Also keep polling while a Zernio publish is in flight so the per-platform
      // status chip flips publishing → published/failed without a manual refresh.
      const zernioActive = Object.values(job?.zernio_publish || {})
        .some(s => s?.status === 'publishing')
      return (slideActive || zernioActive) ? 4_000 : false
    },
  })

  return {
    job: computed(() => query.data.value?.data || null),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useRetryRepurposeJob() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(`/admin/repurpose/${id}/retry`).then(r => r.data),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * Regenerate ALL slides of a video_rebrand carousel (batch). Re-renders the
 * hook/CTA Veo bookends (burns Veo credits) AND re-skins every tool slide.
 * Backend forces the job to `extracted` + dispatches GenerateRebrandAssets.
 */
export function useRegenerateAllRepurposeSlides() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(`/admin/repurpose/${id}/regenerate-slides`).then(r => r.data),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

/**
 * Regenerate a SINGLE slide by its slide_index. A `tool` slide is a free ffmpeg
 * re-skin; a `hook`/`cta` bookend is a Veo re-render (burns credits).
 * Arg: { id, slideIndex }.
 */
export function useRegenerateRepurposeSlide() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, slideIndex }) =>
      api.post(`/admin/repurpose/${id}/slides/${slideIndex}/regenerate`).then(r => r.data),
    onSuccess: (_data, { id }) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * CREDIT-FREE re-skin of a hook/CTA bookend. Re-applies the brand overlay
 * (hook → cover headline + topic logo; CTA → single Follow ask card) onto the
 * already-rendered Veo/GROK clip — NO keyframe, NO i2v render, NO Veo credits.
 * Backend 422s if the bookend was never rendered (use Re-render first).
 * Arg: { id, slideIndex }.
 */
export function useReskinRepurposeSlide() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, slideIndex }) =>
      api.post(`/admin/repurpose/${id}/slides/${slideIndex}/reskin`).then(r => r.data),
    onSuccess: (_data, { id }) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * Publish a video_rebrand carousel to Zernio (IG + Threads) — now or scheduled.
 * Arg: { id, platforms?: string[], scheduledAt?: ISO string|null }.
 * Approve = omit scheduledAt; Schedule = pass a future ISO instant.
 */
export function usePublishRepurposeZernio() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, platforms, scheduledAt }) =>
      api.post(`/admin/repurpose/${id}/publish-zernio`, {
        ...(platforms ? { platforms } : {}),
        ...(scheduledAt ? { scheduled_at: scheduledAt } : {}),
      }).then(r => r.data),
    onSuccess: (_data, { id }) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

/**
 * Re-download a job's source IG slides (the reaper clears them ~7 days after
 * publish). Backend dispatches the capture asynchronously; the detail view
 * polls slide_count until the images reappear.
 */
export function useRefetchRepurposeSource() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.post(`/admin/repurpose/${id}/refetch-source`).then(r => r.data),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: [LIST_KEY, id] })
    },
  })
}

/**
 * Hard-delete a repurpose job (Social Studio "Delete" action). Removes the row
 * + its captured-slide artifacts on the backend; invalidate the list so the
 * card disappears.
 */
export function useDeleteRepurposeJob() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id) => api.delete(`/admin/repurpose/${id}`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

/**
 * Slide thumbnails are auth:sanctum, so a native <img src> can't carry the
 * bearer — fetch as a blob through the axios instance (interceptor adds the
 * token) and hand back an object URL. Caller must URL.revokeObjectURL on unmount.
 */
export async function fetchSlideObjectUrl(id, n) {
  const res = await api.get(`/admin/repurpose/${id}/slide/${n}`, { responseType: 'blob' })
  return URL.createObjectURL(res.data)
}
