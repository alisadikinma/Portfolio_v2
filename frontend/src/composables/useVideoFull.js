import { computed, unref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { isTerminal } from '@/views/admin/videoFullHelpers.js'

/**
 * video_full admin monitoring — TanStack Query composable (mirrors
 * useRepurposeJobs.js). Polls while a job is non-terminal OR any segment is
 * mid-flight so the operator sees the MacBook worker's live progress.
 *
 * Backend: VideoFullController (routes/api.php → /admin/video-full).
 */

const LIST_KEY = 'video-full-jobs'

function listJobs(filters = {}) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.per_page) params.per_page = filters.per_page
  if (filters.page) params.page = filters.page
  return api.get('/admin/video-full', { params }).then((r) => r.data)
}

function getJob(id) {
  return api.get(`/admin/video-full/${id}`).then((r) => r.data)
}

export function useVideoFullList(filters) {
  const query = useQuery({
    queryKey: [LIST_KEY, filters],
    queryFn: () => listJobs(unref(filters) || {}),
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
    refetchInterval: (q) => {
      const rows = q.state.data?.data || []
      return rows.some((j) => !isTerminal(j.status)) ? 8000 : false
    },
  })
  return {
    jobs: computed(() => query.data.value?.data || []),
    pagination: computed(() => query.data.value?.meta || query.data.value || {}),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useVideoFullJob(id) {
  const query = useQuery({
    queryKey: ['video-full-job', id],
    queryFn: () => getJob(unref(id)),
    staleTime: 5_000,
    refetchOnMount: 'always',
    refetchInterval: (q) => {
      const d = q.state.data
      if (!d) return 4000
      const segMidFlight = (d.segments || []).some((s) => ['pending', 'processing'].includes(s.status))
      return !isTerminal(d.job?.status) || segMidFlight ? 4000 : false
    },
  })
  return {
    job: computed(() => query.data.value?.job || null),
    segments: computed(() => query.data.value?.segments || []),
    workerOnline: computed(() => query.data.value?.worker_online || false),
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
  }
}

export function useRegenerateSegment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, index }) =>
      api.post(`/admin/video-full/${id}/regenerate-segment/${index}`).then((r) => r.data),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: ['video-full-job', vars.id] })
      qc.invalidateQueries({ queryKey: [LIST_KEY] })
    },
  })
}

export function usePublishVideoFull() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, platforms, scheduledAt }) =>
      api.post(`/admin/video-full/${id}/publish-zernio`, {
        platforms,
        ...(scheduledAt ? { scheduled_at: scheduledAt } : {}),
      }).then((r) => r.data),
    onSuccess: (_data, vars) => qc.invalidateQueries({ queryKey: ['video-full-job', vars.id] }),
  })
}
