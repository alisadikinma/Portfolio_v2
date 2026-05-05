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
    telegramSettings: {
      telegram_bot_token: null,
      telegram_chat_id: null,
      telegram_enabled: 'false',
      telegram_notify_manifest_needed: 'true',
      telegram_notify_generation_failed: 'true',
      telegram_notify_publish_success: 'false',
    },
    creatorBrandSettings: {
      creator_brand_logo: null,
      creator_brand_tagline: 'alisadikinma.com',
      creator_brand_slug: 'alisadikinma',
      watermark_opacity: '0.30',
      watermark_enabled: 'false'
    },
    mailSettings: {
      mail_mailer: 'smtp',
      mail_host: 'smtp.hostinger.com',
      mail_port: '465',
      mail_username: 'aiagent@alisadikinma.com',
      mail_password: '',
      mail_password_configured: false,
      mail_encryption: 'ssl',
      mail_from_address: 'aiagent@alisadikinma.com',
      mail_from_name: 'Ali Sadikin',
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
          // REPLACE completely instead of merge to avoid stale data
          this.aboutSettings = response.data.data
          
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
          // REPLACE completely instead of merge to avoid stale data
          this.aboutSettings = response.data.data
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
          // REPLACE completely instead of merge to avoid stale data
          this.siteSettings = response.data.data
          
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
          // REPLACE completely instead of merge to avoid stale data
          this.siteSettings = response.data.data
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

    async fetchCreatorBrandSettings() {
      this.loading = true
      this.error = null

      try {
        const response = await api.get('/admin/settings/creator-brand')

        if (response.data.success) {
          this.creatorBrandSettings = response.data.data
        }

        return this.creatorBrandSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch creator brand settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateCreatorBrandSettings(settingsData) {
      this.loading = true
      this.error = null

      try {
        const config = settingsData instanceof FormData ? {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        } : {}

        if (settingsData instanceof FormData && !settingsData.has('_method')) {
          settingsData.append('_method', 'PUT')
        }

        const response = await api.post('/admin/settings/creator-brand', settingsData, config)

        if (response.data.success) {
          this.creatorBrandSettings = response.data.data
        }

        return this.creatorBrandSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update creator brand settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    // Phase I: Telegram notification settings
    async fetchTelegramSettings() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/admin/settings/telegram')
        if (response.data.success) {
          this.telegramSettings = response.data.data
        }
        return this.telegramSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch telegram settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateTelegramSettings(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put('/admin/settings/telegram', payload)
        if (response.data.success) {
          this.telegramSettings = response.data.data
        }
        return this.telegramSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update telegram settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async sendTelegramTestMessage() {
      try {
        const response = await api.post('/admin/settings/telegram/test', {})
        return { success: true, data: response.data }
      } catch (error) {
        return {
          success: false,
          error: error.response?.data?.message || error.message || 'Test message failed',
        }
      }
    },

    // Mail SMTP settings (Newsletter system, May 2026)
    async fetchMailSettings() {
      this.loading = true
      this.error = null
      try {
        const response = await api.get('/admin/settings/mail')
        if (response.data.success) {
          this.mailSettings = response.data.data
        }
        return this.mailSettings
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch mail settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateMailSettings(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await api.put('/admin/settings/mail', payload)
        if (response.data.success) {
          // Refresh the masked-password state from server
          await this.fetchMailSettings()
        }
        return response.data
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update mail settings'
        throw error
      } finally {
        this.loading = false
      }
    },

    async sendMailTestMessage(recipient) {
      try {
        const body = recipient ? { recipient } : {}
        const response = await api.post('/admin/settings/mail/test', body)
        return { success: true, message: response.data.message }
      } catch (error) {
        return {
          success: false,
          error: error.response?.data?.error?.message || error.response?.data?.message || error.message || 'SMTP test failed',
        }
      }
    },

    clearError() {
      this.error = null
    }
  }
})
