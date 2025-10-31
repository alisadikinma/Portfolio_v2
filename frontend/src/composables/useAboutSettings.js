import { computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'

// Default placeholder data for instant render
const DEFAULT_ABOUT = {
  name: 'Ali Sadikin',
  title: 'Full-Stack Developer',
  bio: 'Crafting exceptional digital experiences through modern design and innovative solutions',
  profile_photo: null,
  skills: ['Vue.js', 'React', 'Laravel', 'Node.js', 'TypeScript', 'TailwindCSS', 'MySQL', 'Docker']
}

export function useAboutSettings() {
  const queryClient = useQueryClient()

  // Fetch about settings with TanStack Query (15min stale / 2hr cache)
  const {
    data: settingsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['about-settings'],
    queryFn: async () => {
      console.log('[useAboutSettings] TanStack Query - Fetching about settings')
      const response = await api.get('/settings/about')
      
      if (response.data.success) {
        console.log('[useAboutSettings] Settings loaded & cached')
        return response.data.data
      }
      return DEFAULT_ABOUT // Fallback to default
    },
    placeholderData: DEFAULT_ABOUT, // INSTANT RENDER dengan default data!
    staleTime: 15 * 60 * 1000, // 15 minutes (hero data rarely changes)
    gcTime: 2 * 60 * 60 * 1000, // 2 hours
    retry: 2 // Retry 2 times on failure
  })

  // Computed values (always return data, never null during loading)
  const aboutSettings = computed(() => settingsData.value || DEFAULT_ABOUT)
  const loading = computed(() => isLoading.value)
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Computed hero properties with defaults (never undefined)
  const heroName = computed(() => aboutSettings.value?.name || DEFAULT_ABOUT.name)
  const heroTitle = computed(() => aboutSettings.value?.title || DEFAULT_ABOUT.title)
  const heroBio = computed(() => aboutSettings.value?.bio || DEFAULT_ABOUT.bio)
  const heroAvatar = computed(() => aboutSettings.value?.profile_photo || DEFAULT_ABOUT.profile_photo)
  const heroSkills = computed(() => aboutSettings.value?.skills || DEFAULT_ABOUT.skills)

  // Fetch about settings (will use cache if available)
  const fetchAboutSettings = async () => {
    // Check cache first
    const cached = queryClient.getQueryData(['about-settings'])
    if (cached) {
      console.log('[useAboutSettings] ⚡ Cache HIT (INSTANT)')
      return { success: true, data: cached }
    }

    console.log('[useAboutSettings] ⏳ Cache MISS - fetching via TanStack Query...')
    const result = await refetch()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Prefetch function for early loading (call in router or main.js)
  const prefetchAboutSettings = async () => {
    await queryClient.prefetchQuery({
      queryKey: ['about-settings'],
      queryFn: async () => {
        const response = await api.get('/settings/about')
        return response.data.success ? response.data.data : DEFAULT_ABOUT
      },
      staleTime: 15 * 60 * 1000
    })
    console.log('[useAboutSettings] ✅ Prefetched for instant homepage render')
  }

  return {
    // State
    aboutSettings,
    loading,
    error,

    // Hero properties
    heroName,
    heroTitle,
    heroBio,
    heroAvatar,
    heroSkills,

    // Methods
    fetchAboutSettings,
    prefetchAboutSettings
  }
}
