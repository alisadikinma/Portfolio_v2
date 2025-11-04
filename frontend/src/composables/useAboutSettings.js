import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

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
  const { setCache, getCache } = useLocalCache()
  
  // State untuk instant data dari localStorage
  const cachedAbout = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    cachedAbout.value = getCache('about_settings')
    
    if (cachedAbout.value) {
      console.log('[useAboutSettings] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch about settings with TanStack Query (AGGRESSIVE CACHE - 30min stale / 24hr persist)
  const {
    data: settingsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['about-settings'],
    queryFn: async () => {
      console.log('[useAboutSettings] Background fetch...')
      const response = await api.get('/settings/about')
      
      if (response.data.success) {
        console.log('[useAboutSettings] Background fetch complete')
        
        // Update localStorage cache
        setCache('about_settings', response.data.data, 30 * 60 * 1000) // 30min
        
        return response.data.data
      }
      return DEFAULT_ABOUT // Fallback to default
    },
    placeholderData: DEFAULT_ABOUT, // INSTANT RENDER dengan default data!
    staleTime: 2 * 60 * 1000, // 2 MINUTES (faster updates for hero content)
    gcTime: 24 * 60 * 60 * 1000, // 24 HOURS
    retry: 2,
    initialData: () => {
      // Try get from localStorage first
      return getCache('about_settings') || DEFAULT_ABOUT
    }
  })

  // Computed values - prefer localStorage instant data
  const aboutSettings = computed(() => {
    if (isLoading.value && cachedAbout.value) {
      return cachedAbout.value
    }
    return settingsData.value || DEFAULT_ABOUT
  })
  
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
    // Check localStorage first
    cachedAbout.value = getCache('about_settings')
    
    // Check TanStack Query cache
    const cached = queryClient.getQueryData(['about-settings'])
    if (cached) {
      console.log('[useAboutSettings] TanStack Query cache HIT')
      return { success: true, data: cached }
    }

    if (!cachedAbout.value) {
      console.log('[useAboutSettings] Cache MISS - fetching...')
    } else {
      console.log('[useAboutSettings] âš¡ INSTANT from localStorage, background refresh...')
    }
    
    const result = await refetch()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Prefetch function for early loading (call in router or main.js)
  const prefetchAboutSettings = async () => {
    // Check localStorage first
    const cached = getCache('about_settings')
    if (cached) {
      queryClient.setQueryData(['about-settings'], cached)
      console.log('[useAboutSettings] âš¡ Prefetched from localStorage')
      return
    }
    
    await queryClient.prefetchQuery({
      queryKey: ['about-settings'],
      queryFn: async () => {
        const response = await api.get('/settings/about')
        const data = response.data.success ? response.data.data : DEFAULT_ABOUT
        
        // Cache to localStorage
        setCache('about_settings', data, 30 * 60 * 1000) // 30min
        
        return data
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
