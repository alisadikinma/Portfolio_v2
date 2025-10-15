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

      try {
        const response = await api.get('/admin/settings/about')

        if (response.data.success) {
          this.aboutSettings = {
            ...this.aboutSettings,
            ...response.data.data
          }
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
        // If settingsData is FormData, set proper headers
        const config = settingsData instanceof FormData ? {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        } : {}

        const response = await api.put('/admin/settings/about', settingsData, config)

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

      try {
        const response = await api.get('/admin/settings/site')

        if (response.data.success) {
          this.siteSettings = {
            ...this.siteSettings,
            ...response.data.data
          }
        }

        return this.siteSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch site settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateSiteSettings(settingsData) {
      this.loading = true
      this.error = null

      try {
        // If settingsData is FormData, set proper headers
        const config = settingsData instanceof FormData ? {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        } : {}

        const response = await api.put('/admin/settings/site', settingsData, config)

        if (response.data.success) {
          this.siteSettings = {
            ...this.siteSettings,
            ...response.data.data
          }
        }

        return this.siteSettings
      } catch (error) {
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
