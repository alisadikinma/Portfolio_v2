import { defineStore } from 'pinia'
import api from '@/services/api'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    aboutSettings: {
      name: '',
      title: '',
      bio: '',
      profile_photo: null,
      skills: [],
      experience: [],
      education: [],
      social_links: []
    },
    siteSettings: {
      site_name: '',
      site_description: '',
      site_logo: null,
      contact_email: '',
      contact_phone: '',
      social_media: [],
      meta_tags: [],
      analytics_code: ''
    },
    loading: false,
    error: null
  }),

  getters: {
    hasAboutSettings: (state) => {
      return state.aboutSettings.name !== '' || state.aboutSettings.bio !== ''
    },
    hasSiteSettings: (state) => {
      return state.siteSettings.site_name !== ''
    }
  },

  actions: {
    async fetchAboutSettings() {
      this.loading = true
      this.error = null
      
      console.log('🔍 Fetching about settings from API...')

      try {
        const response = await api.get('/admin/settings/about')
        
        console.log('📥 API Response:', response.data)

        if (response.data.success) {
          this.aboutSettings = {
            ...this.aboutSettings,
            ...response.data.data
          }
          
          console.log('✅ Store updated:', this.aboutSettings)
        }

        return this.aboutSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch about settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateAboutSettings(settingsData) {
      this.loading = true
      this.error = null

      try {
        // If settingsData is FormData, use POST with _method spoofing (Laravel requirement)
        const config = settingsData instanceof FormData ? {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        } : {}

        // Use POST for FormData (Laravel doesn't support PUT with FormData)
        const response = await api.post('/admin/settings/about', settingsData, config)

        if (response.data.success) {
          this.aboutSettings = {
            ...this.aboutSettings,
            ...response.data.data
          }
        }

        return this.aboutSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update about settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchSiteSettings() {
      this.loading = true
      this.error = null
      
      console.log('🔍 Fetching site settings from API...')

      try {
        const response = await api.get('/admin/settings/site')
        
        console.log('📥 API Response:', response.data)

        if (response.data.success) {
          this.siteSettings = {
            ...this.siteSettings,
            ...response.data.data
          }
          
          console.log('✅ Store updated:', this.siteSettings)
        }

        return this.siteSettings
      } catch (error) {
        console.error('❌ Failed to fetch site settings:', error)
        this.error = error.response?.data?.message || 'Failed to fetch site settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateSiteSettings(settingsData) {
      this.loading = true
      this.error = null

      console.log('🔵 updateSiteSettings - Starting update...', {
        isFormData: settingsData instanceof FormData
      })

      try {
        // If settingsData is FormData, use POST with _method spoofing (Laravel requirement)
        const config = settingsData instanceof FormData ? {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        } : {}

        // Add _method for Laravel if using FormData
        if (settingsData instanceof FormData && !settingsData.has('_method')) {
          settingsData.append('_method', 'PUT')
        }

        // Use POST for FormData (Laravel doesn't support PUT with FormData)
        const response = await api.post('/admin/settings/site', settingsData, config)

        console.log('📥 Response received:', response.data)

        if (response.data.success) {
          this.siteSettings = {
            ...this.siteSettings,
            ...response.data.data
          }
          console.log('✅ Store updated successfully:', this.siteSettings)
        }

        return this.siteSettings
      } catch (error) {
        console.error('❌ Store update failed:', error)
        this.error = error.response?.data?.message || 'Failed to update site settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    clearError() {
      this.error = null
    }
  }
})
