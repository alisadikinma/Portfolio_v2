import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/Portfolio_v2/backend/public/api/v1'

export const useSettingsStore = defineStore('settings', () => {
  // State
  const settings = ref({
    // General Settings
    site_name: '',
    site_description: '',
    site_logo: '',
    site_favicon: '',

    // Social Media
    github_url: '',
    linkedin_url: '',
    twitter_url: '',
    facebook_url: '',
    instagram_url: '',
    youtube_url: '',

    // SEO Settings
    default_meta_title: '',
    default_meta_description: '',
    default_og_image: '',
    google_analytics_id: '',
    google_search_console: '',

    // Contact Information
    contact_email: '',
    contact_phone: '',
    contact_address: '',
    contact_city: '',
    contact_country: '',
    contact_postal_code: '',

    // Additional Settings
    maintenance_mode: false,
    allow_comments: true,
    items_per_page: 15
  })

  const loading = ref(false)
  const error = ref(null)

  // Actions
  async function fetchSettings() {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`${API_BASE_URL}/settings`)

      // Merge fetched settings with default settings
      if (response.data.data) {
        settings.value = { ...settings.value, ...response.data.data }
      }

      return settings.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch settings'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSettings(settingsData) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/settings`,
        settingsData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      // Update local settings
      if (response.data.data) {
        settings.value = { ...settings.value, ...response.data.data }
      }

      return settings.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update settings'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSetting(key, value) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(
        `${API_BASE_URL}/admin/settings`,
        { [key]: value },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      )

      // Update local setting
      settings.value[key] = value

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update setting'
      throw err
    } finally {
      loading.value = false
    }
  }

  function getSetting(key, defaultValue = null) {
    return settings.value[key] !== undefined ? settings.value[key] : defaultValue
  }

  function clearError() {
    error.value = null
  }

  function resetStore() {
    settings.value = {
      site_name: '',
      site_description: '',
      site_logo: '',
      site_favicon: '',
      github_url: '',
      linkedin_url: '',
      twitter_url: '',
      facebook_url: '',
      instagram_url: '',
      youtube_url: '',
      default_meta_title: '',
      default_meta_description: '',
      default_og_image: '',
      google_analytics_id: '',
      google_search_console: '',
      contact_email: '',
      contact_phone: '',
      contact_address: '',
      contact_city: '',
      contact_country: '',
      contact_postal_code: '',
      maintenance_mode: false,
      allow_comments: true,
      items_per_page: 15
    }
    loading.value = false
    error.value = null
  }

  return {
    // State
    settings,
    loading,
    error,

    // Actions
    fetchSettings,
    updateSettings,
    updateSetting,
    getSetting,
    clearError,
    resetStore
  }
})
