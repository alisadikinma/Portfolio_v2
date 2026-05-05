import { useQuery } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Fetches the bundled homepage payload from /api/homepage/featured.
 * Returns stats + featured_awards + featured_testimonials (LinkedIn only)
 * + featured_projects + latest_articles in a single round-trip.
 *
 * Cached 30 min. Phase 1 of homepage redesign.
 */
export function useHomepageFeatured() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['homepage-featured'],
    queryFn: async () => {
      const res = await api.get('/homepage/featured')
      return res.data?.data ?? null
    },
    staleTime: 30 * 1000,
    refetchOnMount: 'always',
    refetchOnWindowFocus: false,
  })

  return { data, isLoading, error }
}
