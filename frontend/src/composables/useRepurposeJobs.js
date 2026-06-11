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
      return rows.some(r => !isTerminal(r.status)) ? 5_000 : false
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
      const status = q.state.data?.data?.status
      return status && !isTerminal(status) ? 4_000 : false
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
 * Slide thumbnails are auth:sanctum, so a native <img src> can't carry the
 * bearer — fetch as a blob through the axios instance (interceptor adds the
 * token) and hand back an object URL. Caller must URL.revokeObjectURL on unmount.
 */
export async function fetchSlideObjectUrl(id, n) {
  const res = await api.get(`/admin/repurpose/${id}/slide/${n}`, { responseType: 'blob' })
  return URL.createObjectURL(res.data)
}
