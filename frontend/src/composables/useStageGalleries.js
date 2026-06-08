import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/services/api'

// Fetches the award galleries behind the International Stages cards so each
// curated stage can show a real event photo (cover) + open the full gallery.
// Awards/galleries change rarely → 60-min cache (matches the Awards policy in
// CLAUDE.md). Per-award failures are isolated (try/catch → null) so one bad
// award never blanks the whole section.
export function useStageGalleries(awardIds) {
  const ids = (Array.isArray(awardIds) ? awardIds : []).filter((n) => Number.isFinite(n))

  const { data, isLoading, error } = useQuery({
    queryKey: ['stage-galleries', ids],
    enabled: ids.length > 0,
    staleTime: 60 * 60 * 1000, // 60 min
    gcTime: 60 * 60 * 1000,
    retry: 1,
    refetchOnWindowFocus: false,
    queryFn: async () => {
      const pairs = await Promise.all(
        ids.map(async (id) => {
          try {
            const res = await api.get(`/awards/${id}/galleries`)
            const payload = res.data?.data ?? res.data ?? {}
            const galleries = Array.isArray(payload.galleries) ? payload.galleries : []
            const first = galleries[0] || {}
            const items = Array.isArray(first.items) ? first.items : []
            const cover = items[0]?.file_path || items[0]?.file_url || items[0]?.image || null
            return [id, { cover, items, title: first.title || payload.award?.title || '' }]
          } catch {
            return [id, null]
          }
        })
      )
      return Object.fromEntries(pairs)
    },
  })

  // { [awardId]: { cover, items, title } | null }
  const galleries = computed(() => data.value || {})

  return { galleries, isLoading, error }
}
