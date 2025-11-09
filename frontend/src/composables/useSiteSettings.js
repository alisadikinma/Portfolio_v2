import { ref, computed } from 'vue'
import api from '@/services/api'

const siteSettings = ref(null)
const loading = ref(false)
const error = ref(null)

export function useSiteSettings() {
  const fetchSiteSettings = async (forceRefresh = false) => {
    if (siteSettings.value && !forceRefresh) return // Cache

    loading.value = true
    error.value = null

    try {
      const response = await api.get('/settings/site')
      
      if (response.data.success) {
        siteSettings.value = response.data.data
      }
    } catch (err) {
      console.error('Failed to fetch site settings:', err)
      error.value = err.message
      
      // Fallback ke placeholder
      siteSettings.value = {
        site_name: 'Portfolio',
        site_logo: null,
        site_tagline: 'Professional Portfolio',
        contact_email: 'contact@example.com',
        contact_phone: '+62 xxx xxx xxxx'
      }
    } finally {
      loading.value = false
    }
  }

  // Computed properties
  const siteName = computed(() => siteSettings.value?.site_name || 'Portfolio')
  const siteLogo = computed(() => {
    const logo = siteSettings.value?.site_logo
    if (!logo) return null
    if (logo.startsWith('http')) return logo
    // Add cache buster for development
    const baseUrl = import.meta.env.VITE_API_URL.replace('/api', '')
    return `${baseUrl}${logo}?t=${Date.now()}`
  })
  const siteTagline = computed(() => siteSettings.value?.site_tagline || '')
  const contactEmail = computed(() => siteSettings.value?.contact_email || '')
  const contactPhone = computed(() => siteSettings.value?.contact_phone || '')

  const clearCache = () => {
    siteSettings.value = null
    console.log('🗑️ [useSiteSettings] Cache cleared')
  }

  return {
    siteSettings,
    loading,
    error,
    fetchSiteSettings,
    clearCache,
    siteName,
    siteLogo,
    siteTagline,
    contactEmail,
    contactPhone
  }
}
