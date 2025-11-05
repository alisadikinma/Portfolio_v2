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

  // Fetch about settings with TanStack Query
  const {
    data: settingsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['about-settings'],
    queryFn: async () => {
      console.log('[useAboutSettings] Fetching from API...')
      const response = await api.get('/settings/about')
      
      if (response.data.success) {
        console.log('[useAboutSettings] ✅ Fresh data loaded')
        return response.data.data
      }
      return DEFAULT_ABOUT
    },
    placeholderData: DEFAULT_ABOUT, // Instant render
    staleTime: 10 * 1000, // 10 SECONDS - very aggressive for instant updates
    gcTime: 5 * 60 * 1000, // 5 MINUTES - short cache for fresh data
    retry: 2,
    refetchOnWindowFocus: true, // Auto-refetch when user returns to tab
    refetchOnMount: 'always' // CRITICAL: Always refetch on mount
  })

  // Computed values - ONLY use TanStack Query data
  const aboutSettings = computed(() => settingsData.value || DEFAULT_ABOUT)
  const loading = computed(() => isLoading.value)
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Computed hero properties with defaults
  const heroName = computed(() => aboutSettings.value?.name || DEFAULT_ABOUT.name)
  const heroTitle = computed(() => aboutSettings.value?.title || DEFAULT_ABOUT.title)
  const heroBio = computed(() => aboutSettings.value?.bio || DEFAULT_ABOUT.bio)
  const heroAvatar = computed(() => aboutSettings.value?.profile_photo || DEFAULT_ABOUT.profile_photo)
  const heroSkills = computed(() => aboutSettings.value?.skills || DEFAULT_ABOUT.skills)

  // Fetch about settings (force refetch)
  const fetchAboutSettings = async () => {
    console.log('[useAboutSettings] Force refetch...')
    const result = await refetch()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Prefetch function for early loading
  const prefetchAboutSettings = async () => {
    await queryClient.prefetchQuery({
      queryKey: ['about-settings'],
      queryFn: async () => {
        const response = await api.get('/settings/about')
        return response.data.success ? response.data.data : DEFAULT_ABOUT
      },
      staleTime: 10 * 1000
    })
    console.log('[useAboutSettings] ✅ Prefetched')
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
