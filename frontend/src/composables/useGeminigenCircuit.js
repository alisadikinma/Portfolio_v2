import { useQuery } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Polls the GeminiGen circuit breaker state every 30s.
 * Used by the read-only status indicator in ContentEngine + LinkedInQueueList headers.
 *
 * Backend contract: GET /api/admin/geminigen/circuit-status (auth:sanctum, Phase J)
 * Returns: { state, opened_at, next_probe_at, last_probe_result, failure_count_in_window }
 *
 * The shared `@/services/api` axios instance already injects the
 * `Authorization: Bearer <token>` header via its request interceptor
 * (see services/api.js), so no manual auth wiring is needed here.
 */
export function useGeminigenCircuit() {
  return useQuery({
    queryKey: ['geminigen-circuit-status'],
    queryFn: async () => {
      const { data } = await api.get('/admin/geminigen/circuit-status')
      return data?.data ?? null
    },
    staleTime: 30_000,
    refetchInterval: 30_000,
    refetchOnMount: 'always',
    // Don't retry aggressively — if the endpoint fails, show "unknown" badge
    retry: 1,
  })
}
