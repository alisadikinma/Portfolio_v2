import { computed, unref } from 'vue'
import { useQuery, useQueries } from '@tanstack/vue-query'
import api from '@/services/api'

/**
 * STATUS: Planned infrastructure (May 9, 2026 cross-post UI sprint).
 *   This composable is implemented and unit-tested but not yet consumed
 *   by any view. CrossPostList.vue still uses useCrossPostDraftsList
 *   from useCrossPostDrafts.js (per-platform fetch only).
 *
 *   Future consumer: a unified "All Platforms" queue triage view that
 *   would replace per-platform tab switching with a grouped table
 *   showing every platform's queue in one shot. Specs verify both
 *   single-platform mode and 'all' aggregate mode work; activate by
 *   updating CrossPostList (or a new SocialQueueList view) to import
 *   from this file.
 *
 * --
 *
 * Queue list composable spanning LinkedIn + Facebook + Instagram +
 * TikTok plus an aggregate `all` mode.
 *
 * platform = 'linkedin' | 'facebook' | 'instagram' | 'tiktok' → fires
 * one query against that platform's drafts endpoint.
 *
 * platform = 'all' → fires 4 parallel queries via `useQueries` and
 * merges results, tagging each row with `_platform: '<key>'` so the
 * view can render a platform chip per row + grouped tables.
 *
 * Endpoints:
 *   - linkedin → /admin/linkedin-drafts
 *   - facebook → /admin/facebook-drafts
 *   - instagram → /admin/instagram-drafts
 *   - tiktok → /admin/tiktok-drafts
 *
 * All return Laravel API resource shape: { data: [...rows], meta }.
 */

const PLATFORMS = ['linkedin', 'facebook', 'instagram', 'tiktok']

function endpointFor(platform) {
  return `/admin/${platform}-drafts`
}

function tagRows(rows, platform) {
  return rows.map((r) => ({ ...r, _platform: platform }))
}

function emptyGroups() {
  return PLATFORMS.reduce((acc, p) => ({ ...acc, [p]: [] }), {})
}

export function useSocialQueueList(platformRef, filtersRef) {
  // Single-platform branch: one useQuery, classic shape.
  const singleQuery = useQuery({
    queryKey: ['social-queue', platformRef, filtersRef],
    queryFn: async () => {
      const platform = unref(platformRef)
      if (platform === 'all') return { rows: [] }

      const filters = unref(filtersRef) || {}
      const { data } = await api.get(endpointFor(platform), { params: filters })
      const rows = Array.isArray(data?.data) ? data.data : []
      return { rows: tagRows(rows, platform) }
    },
    enabled: computed(() => unref(platformRef) !== 'all'),
    staleTime: 30_000,
    refetchOnMount: 'always',
    gcTime: 5 * 60 * 1000,
    retry: false,
  })

  // All-platforms branch: 4 parallel queries via useQueries. Each query
  // is independent (a failure on one platform doesn't poison the others)
  // — failed queries surface as empty rows for that platform.
  const allQueries = useQueries({
    queries: PLATFORMS.map((platform) => ({
      queryKey: ['social-queue-all', platform, filtersRef],
      queryFn: async () => {
        const filters = unref(filtersRef) || {}
        const { data } = await api.get(endpointFor(platform), { params: filters })
        const rows = Array.isArray(data?.data) ? data.data : []
        return { platform, rows: tagRows(rows, platform) }
      },
      enabled: computed(() => unref(platformRef) === 'all'),
      staleTime: 30_000,
      refetchOnMount: 'always',
      gcTime: 5 * 60 * 1000,
      retry: false,
    })),
  })

  const drafts = computed(() => {
    const platform = unref(platformRef)
    if (platform !== 'all') {
      return singleQuery.data.value?.rows || []
    }
    // Merge 4 parallel results. Failed queries contribute nothing.
    const merged = []
    for (const q of allQueries.value) {
      if (q.data?.rows) merged.push(...q.data.rows)
    }
    return merged
  })

  const groups = computed(() => {
    const platform = unref(platformRef)
    const out = emptyGroups()
    if (platform !== 'all') {
      // In single-platform mode, only the active platform's bucket is
      // populated. Others remain empty arrays so view renders are
      // stable across platform switches.
      const rows = singleQuery.data.value?.rows || []
      if (PLATFORMS.includes(platform)) out[platform] = rows
      return out
    }
    for (const q of allQueries.value) {
      if (q.data?.platform && q.data?.rows) {
        out[q.data.platform] = q.data.rows
      }
    }
    return out
  })

  const isLoading = computed(() => {
    const platform = unref(platformRef)
    if (platform !== 'all') return singleQuery.isLoading.value
    return allQueries.value.some((q) => q.isLoading)
  })

  const isFetching = computed(() => {
    const platform = unref(platformRef)
    if (platform !== 'all') return singleQuery.isFetching.value
    return allQueries.value.some((q) => q.isFetching)
  })

  return {
    drafts,
    groups,
    isLoading,
    isFetching,
    error: computed(() => singleQuery.error.value),
    refetch: () => {
      const platform = unref(platformRef)
      if (platform !== 'all') return singleQuery.refetch()
      // Refetch all 4. Vue-query useQueries doesn't expose a single
      // top-level refetch — call each query's refetch.
      return Promise.all(allQueries.value.map((q) => q.refetch?.()))
    },
  }
}
