import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Stale-content freshness surface — TanStack Query composable (GEO
 * publish-and-forget fix). Mirrors the 30s staleTime + refetchOnMount:'always'
 * convention from useScheduler.js / useNewsletterAdmin.js so operator actions
 * surface on next navigation.
 *
 * Backend contract (Phase D):
 *   GET  /api/admin/content-engine/stale-posts?days=90
 *        → { data: [{ id, slug, title, published_at, content_reviewed_at, days_stale }], meta }
 *   POST /api/admin/content-engine/posts/{id}/mark-reviewed
 *        → stamps content_reviewed_at=now() (post drops out of the stale list)
 *
 * api.get/post return the raw axios response → unwrap with .then(r => r.data).
 */

const STALE_KEY = ['admin', 'stale-posts']

export function useStalePosts(days = 90) {
  return useQuery({
    queryKey: [...STALE_KEY, days],
    queryFn: () =>
      api
        .get('/admin/content-engine/stale-posts', { params: { days } })
        .then((r) => r.data?.data ?? []),
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
  })
}

export function useMarkReviewed() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id) =>
      api
        .post(`/admin/content-engine/posts/${id}/mark-reviewed`)
        .then((r) => r.data),
    onSuccess: () => {
      // Refresh the stale set + the admin posts list (the reviewed post drops
      // out of the badge count).
      queryClient.invalidateQueries({ queryKey: STALE_KEY })
      queryClient.invalidateQueries({ queryKey: ['admin', 'posts'] })
    },
  })
}
