import { ref } from 'vue'
import api from '@/services/api'

export function useSettings() {
  const settings = ref([])
  const settingsByGroup = ref({})
  const setting = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  // Cache for settings to avoid repeated API calls
  const cache = ref({
    all: null,
    groups: {},
    keys: {}
  })

  // Fetch all settings
  const fetchSettings = async (forceRefresh = false) => {
    // Return cached data if available and not forcing refresh
    if (cache.value.all && !forceRefresh) {
      settings.value = cache.value.all
      return { success: true, data: cache.value.all }
    }

    isLoading.value = true
    error.value = null

    try {
      const response = await api.get('/settings')
      settings.value = response.data.data
      cache.value.all = response.data.data

      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch settings'
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch settings by group
  const fetchSettingsByGroup = async (group, forceRefresh = false) => {
    // Return cached data if available and not forcing refresh
    if (cache.value.groups[group] && !forceRefresh) {
      settingsByGroup.value = cache.value.groups[group]
      return { success: true, data: cache.value.groups[group] }
    }

    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/settings/group/${group}`)
      const data = response.data.data
      
      settingsByGroup.value = data
      cache.value.groups[group] = data

      return { success: true, data }
    } catch (err) {
      error.value = err.response?.data?.message || `Failed to fetch ${group} settings`
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
    }
  }

  // Fetch single setting by key
  const fetchSetting = async (key, forceRefresh = false) => {
    // Return cached data if available and not forcing refresh
    if (cache.value.keys[key] && !forceRefresh) {
      setting.value = cache.value.keys[key]
      return { success: true, data: cache.value.keys[key] }
    }

    isLoading.value = true
    error.value = null

    try {
      const response = await api.get(`/settings/${key}`)
      const data = response.data.data
      
      setting.value = data
      cache.value.keys[key] = data

      return { success: true, data }
    } catch (err) {
      error.value = err.response?.data?.message || `Failed to fetch setting: ${key}`
      return { success: false, error: error.value }
    } finally {
      isLoading.value = false
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
    cache.value = {
      all: null,
      groups: {},
      keys: {}
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
