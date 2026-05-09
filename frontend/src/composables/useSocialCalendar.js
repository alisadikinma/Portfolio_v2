import { computed, unref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * Unified calendar data composable for the SocialPostsCalendar view.
 *
 * Maps a platform key (linkedin / facebook / instagram / tiktok) to its
 * backend calendar endpoint and normalizes the two response shapes:
 *
 *   - LinkedIn (LinkedInDraftController::calendar):
 *       { data: { items: [...rows], from, to, count } }
 *   - Cross-post (HandlesCrossPostDraftActions::calendar):
 *       { data: [...rows], meta: { from, to } }
 *
 * Both yield a flat `items[]` of:
 *   { id, status, format?, post_id, post_title, scheduled_at,
 *     published_at, pin_at, depth_score? }
 *
 * Pin-at semantics match LinkedIn: `published_at ?? scheduled_at`,
 * already computed server-side. The view buckets by WIB date.
 */

const ENDPOINT = {
  linkedin: '/admin/linkedin-posts/calendar',
  facebook: '/admin/facebook-drafts/calendar',
  instagram: '/admin/instagram-drafts/calendar',
  tiktok: '/admin/tiktok-drafts/calendar',
}

function endpointFor(platform) {
  const url = ENDPOINT[platform]
  if (!url) {
    throw new Error(`useSocialCalendar: unknown platform "${platform}"`)
  }
  return url
}

/**
 * Normalize either response shape to a flat array. The conditional
 * lives here (not in the view) so platform variations stay in this
 * single chokepoint.
 */
function extractItems(payload) {
  // Cross-post shape: data is already the array
  if (Array.isArray(payload?.data)) {
    return payload.data
  }
  // LinkedIn shape: data.items
  if (Array.isArray(payload?.data?.items)) {
    return payload.data.items
  }
  return []
}

export function useSocialCalendar(platformRef, rangeRef) {
  const query = useQuery({
    // Platform + range both keyed so platform switch invalidates cleanly.
    queryKey: ['social-calendar', platformRef, rangeRef],
    queryFn: async () => {
      const platform = unref(platformRef)
      const { from, to, status } = unref(rangeRef) || {}

      // No range → no fetch. Return empty so view renders skeleton.
      if (!from || !to) {
        return { items: [] }
      }

      const params = { from, to }
      if (status) params.status = status

      const response = await api.get(endpointFor(platform), { params })
      const items = extractItems(response.data)
      return { items }
    },
    // Match useLinkedInCalendar staleness so existing UX doesn't shift.
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
    // Don't fire when range is missing — composable returns empty `items`.
    enabled: computed(() => {
      const range = unref(rangeRef)
      return !!(range?.from && range?.to)
    }),
  })

  return {
    items: computed(() => query.data.value?.items || []),
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    error: query.error,
    refetch: query.refetch,
  }
}
