import { ref, computed, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import api from '@/services/api'
import { useLocalCache } from './useLocalCache'

export function useSettings() {
  const queryClient = useQueryClient()
  const { setCache, getCache } = useLocalCache()
  
  const selectedGroup = ref(null)
  const selectedKey = ref(null)
  const setting = ref(null)
  
  // State untuk instant data dari localStorage
  const cachedSettings = ref(null)
  const cachedGroupSettings = ref(null)

  // Load instant cache on mount
  onMounted(() => {
    cachedSettings.value = getCache('settings_all')
    
    if (cachedSettings.value) {
      console.log('[useSettings] âš¡ INSTANT from localStorage')
    }
  })

  // Fetch all settings with caching (10min stale / 1hr cache)
  const {
    data: settingsData,
    isLoading,
    error: queryError,
    refetch
  } = useQuery({
    queryKey: ['settings'],
    queryFn: async () => {
      const response = await api.get('/settings')
      console.log('[useSettings] Background fetch complete')
      
      // API returns grouped data: { data: { profile: [...], about: [...], ... } }
      // We need to flatten it to array
      const groupedData = response.data.data
      const flattenedSettings = []
      
      Object.keys(groupedData).forEach(groupName => {
        const groupSettings = groupedData[groupName]
        if (Array.isArray(groupSettings)) {
          flattenedSettings.push(...groupSettings)
        }
      })
      
      // Update localStorage cache
      setCache('settings_all', flattenedSettings, 10 * 60 * 1000) // 10min
      
      return flattenedSettings
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
    gcTime: 60 * 60 * 1000, // 1 hour
    initialData: () => {
      // Try get from localStorage first
      return getCache('settings_all') || []
    }
  })

  // Computed values - prefer localStorage instant data
  const settings = computed(() => {
    if (isLoading.value && cachedSettings.value) {
      return cachedSettings.value
    }
    return settingsData.value || []
  })
  
  const error = computed(() => queryError.value?.response?.data?.message || queryError.value?.message || null)

  // Fetch settings by group with caching
  const {
    data: groupSettingsData,
    isLoading: isLoadingGroup,
    refetch: refetchGroup
  } = useQuery({
    queryKey: ['settings-group', selectedGroup],
    queryFn: async () => {
      if (!selectedGroup.value) return []
      
      const response = await api.get(`/settings/group/${selectedGroup.value}`)
      console.log('[useSettings] Group background fetch complete')
      
      // Update localStorage cache
      const cacheKey = `settings_group_${selectedGroup.value}`
      setCache(cacheKey, response.data.data, 10 * 60 * 1000) // 10min
      
      return response.data.data
    },
    enabled: computed(() => !!selectedGroup.value),
    staleTime: 10 * 60 * 1000,
    gcTime: 60 * 60 * 1000,
    initialData: () => {
      if (!selectedGroup.value) return []
      const cacheKey = `settings_group_${selectedGroup.value}`
      return getCache(cacheKey) || []
    }
  })

  const settingsByGroup = computed(() => {
    if (isLoadingGroup.value && cachedGroupSettings.value) {
      return cachedGroupSettings.value
    }
    return groupSettingsData.value || []
  })

  // Fetch all settings - WITH CACHE
  const fetchSettings = async (forceRefresh = false) => {
    if (!forceRefresh) {
      // Check instant localStorage
      cachedSettings.value = getCache('settings_all')
      
      // Check TanStack Query cache
      const queryCache = queryClient.getQueryData(['settings'])
      if (queryCache) {
        console.log('[useSettings] TanStack Query cache HIT')
        return { success: true, data: queryCache }
      }

      if (!cachedSettings.value) {
        console.log('[useSettings] Cache MISS - fetching...')
      } else {
        console.log('[useSettings] âš¡ INSTANT from localStorage, background refresh...')
      }
    } else {
      console.log('[useSettings] Force refresh...')
    }
    
    const result = await refetch()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch settings by group - WITH CACHE
  const fetchSettingsByGroup = async (group, forceRefresh = false) => {
    selectedGroup.value = group
    
    if (!forceRefresh) {
      const cacheKey = `settings_group_${group}`
      
      // Check instant localStorage
      cachedGroupSettings.value = getCache(cacheKey)
      
      // Check TanStack Query cache
      const queryCache = queryClient.getQueryData(['settings-group', group])
      if (queryCache) {
        console.log('[useSettings] Group cache HIT:', group)
        return { success: true, data: queryCache }
      }

      if (!cachedGroupSettings.value) {
        console.log('[useSettings] Group cache MISS:', group)
      } else {
        console.log('[useSettings] âš¡ INSTANT group from localStorage:', group)
      }
    }
    
    const result = await refetchGroup()
    
    return {
      success: !result.isError,
      data: result.data,
      error: result.error?.response?.data?.message
    }
  }

  // Fetch single setting by key - WITH CACHE
  const fetchSetting = async (key, forceRefresh = false) => {
    const cacheKey = `setting_${key}`
    
    if (!forceRefresh) {
      // Check localStorage first
      const cached = getCache(cacheKey)
      if (cached) {
        console.log('[useSettings] âš¡ INSTANT setting from localStorage:', key)
        setting.value = cached
        return { success: true, data: cached }
      }
    }
    
    try {
      const response = await api.get(`/settings/${key}`)
      const data = response.data.data
      
      // Cache the result
      setCache(cacheKey, data, 10 * 60 * 1000) // 10min
      
      setting.value = data
      return { success: true, data }
    } catch (err) {
      return {
        success: false,
        error: err.response?.data?.message || `Failed to fetch setting: ${key}`
      }
    }
  }

  // Get setting value by key from cached settings
  const getSettingValue = (key, defaultValue = null) => {
    if (!settings.value || settings.value.length === 0) {
      return defaultValue
    }

    const foundSetting = settings.value.find(s => s.key === key)
    return foundSetting ? foundSetting.value : defaultValue
  }

  // Get settings by group from cached settings
  const getSettingsByGroup = (group) => {
    if (!settings.value || settings.value.length === 0) {
      return []
    }

    return settings.value.filter(s => s.group === group)
  }

  // Clear cache
  const clearCache = () => {
    // Invalidate ALL settings-related queries
    queryClient.invalidateQueries({ queryKey: ['settings'] })
    queryClient.invalidateQueries({ queryKey: ['settings-group'] })
    queryClient.invalidateQueries({ queryKey: ['about-settings'] }) // CRITICAL: Hero section cache
    
    console.log('✅ [useSettings] All caches invalidated (settings, groups, about)')
    
    // Clear localStorage cache
    setCache('settings_all', null, 0)
    setCache('about_settings', null, 0) // CRITICAL: Hero section localStorage
    if (selectedGroup.value) {
      setCache(`settings_group_${selectedGroup.value}`, null, 0)
    }
  }

  return {
    // State
    settings,
    settingsByGroup,
    setting,
    isLoading,
    error,

    // Methods
    fetchSettings,
    fetchSettingsByGroup,
    fetchSetting,
    getSettingValue,
    getSettingsByGroup,
    clearCache
  }
}
