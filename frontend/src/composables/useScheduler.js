import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Scheduler admin — TanStack Query composable.
 *
 * Mirrors the 30s staleTime + refetchOnMount:'always' convention from
 * useLinkedInDrafts.js so operator toggles surface on next navigation.
 *
 * Backend contract (Phases A–E shipped):
 *   GET  /api/admin/scheduler         → { data: { groups: { linkedin:[…], … } } }
 *   PUT  /api/admin/scheduler/{id}    → updates enabled / cron_expression / …
 *   POST /api/admin/scheduler/{id}/run → 202 Accepted (queued via Artisan::queue)
 *
 * NOTE: services/api.js' axios wrapper returns response.data already unwrapped.
 *       So `await api.get(...)` already gives us the JSON envelope body.
 */

const LIST_KEY = ['admin', 'scheduler']

/**
 * List query — returns the `groups` map keyed by category
 * (linkedin / content_engine / newsletter / system / instagram / facebook / tiktok / …).
 */
export function useScheduledCommands() {
  return useQuery({
    queryKey: LIST_KEY,
    queryFn: async () => {
      const data = await api.get('/admin/scheduler')
      // Tolerate both wrapped { data: { groups } } and bare { groups } shapes
      return data?.data?.groups ?? data?.groups ?? {}
    },
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
  })
}

/**
 * Update mutation — payload may include any subset of:
 *   enabled, cron_expression, description, timezone,
 *   without_overlapping_minutes, run_in_background
 * Backend rejects placeholder rows with 403.
 */
export function useUpdateScheduledCommand() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const data = await api.put(`/admin/scheduler/${id}`, payload)
      return data?.data ?? data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LIST_KEY })
    },
  })
}

/**
 * Run-now mutation — backend returns 202 Accepted (job queued via Artisan::queue,
 * not synchronous). The list refetch picks up last_run_at + last_status from the
 * scheduled_command_runs table once the queue worker processes the job.
 */
export function useRunScheduledCommand() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id) => {
      const data = await api.post(`/admin/scheduler/${id}/run`)
      return data?.data ?? data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: LIST_KEY })
    },
  })
}
