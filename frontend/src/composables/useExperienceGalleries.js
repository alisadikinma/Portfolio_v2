import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/services/api'

// Fetches the work-experience galleries behind the Track Record "career" cards,
// keyed by raw gallery_id (NOT awardId — career galleries are linked directly via
// settings.about.experience[].gallery_ids, e.g. Singapore → gallery 14).
// The /galleries/{id} endpoint returns `file_url` (full CDN URL); BaseGalleryModal
// + the card cover read that field directly. 60-min cache (matches Awards policy).
// Per-gallery failures are isolated (try/catch → null) so one bad id never blanks
// the whole band.
export function useExperienceGalleries(galleryIds) {
  const ids = [...new Set((Array.isArray(galleryIds) ? galleryIds : []).filter((n) => Number.isFinite(n)))]

  const { data, isLoading, error } = useQuery({
    queryKey: ['experience-galleries', ids],
    enabled: ids.length > 0,
    staleTime: 60 * 60 * 1000, // 60 min
    gcTime: 60 * 60 * 1000,
    retry: 1,
    refetchOnWindowFocus: false,
    queryFn: async () => {
      const pairs = await Promise.all(
        ids.map(async (id) => {
          try {
            const res = await api.get(`/galleries/${id}`)
            const g = res.data?.data ?? res.data ?? {}
            const items = Array.isArray(g.items) ? g.items : []
            // Prefer the gallery's curated composite thumbnail (the hero card image
            // shown in the admin picker) over the raw first item.
            const cover = g.thumbnail || items[0]?.file_url || items[0]?.file_path || items[0]?.image || null
            return [id, { cover, items, title: g.title || '' }]
          } catch {
            return [id, null]
          }
        })
      )
      return Object.fromEntries(pairs)
    },
  })

  // { [galleryId]: { cover, items, title } | null }
  const galleries = computed(() => data.value || {})

  // Flatten a chapter's gallery_ids → first available cover + all items merged
  // (one chapter, e.g. Marlin, can span several galleries).
  function coverFor(galleryIdList) {
    for (const id of galleryIdList || []) {
      const c = galleries.value[id]?.cover
      if (c) return c
    }
    return null
  }
  function itemsFor(galleryIdList) {
    const out = []
    for (const id of galleryIdList || []) {
      const its = galleries.value[id]?.items
      if (Array.isArray(its)) out.push(...its)
    }
    return out
  }

  return { galleries, coverFor, itemsFor, isLoading, error }
}
