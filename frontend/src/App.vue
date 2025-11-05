<template>
  <component :is="layout">
    <router-view />
  </component>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import { useSiteSettings } from '@/composables/useSiteSettings'
import { useMetaTags } from '@/composables/useMetaTags'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()
const themeStore = useThemeStore()
const { fetchSiteSettings, siteSettings } = useSiteSettings()
const { setMetaFromSettings, resetPageMeta } = useMetaTags()

const layout = computed(() => {
  const layoutName = route.meta.layout || 'default'

  const layouts = {
    default: DefaultLayout,
    admin: AdminLayout,
    auth: AuthLayout
  }

  return layouts[layoutName] || DefaultLayout
})

// Setup on mount
onMounted(async () => {
  console.log('✅ App.vue mounted')

  // Fetch site settings and update meta tags
  try {
    await fetchSiteSettings()
    if (siteSettings.value) {
      setMetaFromSettings(siteSettings.value)
      console.log('✅ Site settings loaded from CMS and meta tags updated')
    }
  } catch (error) {
    console.error('❌ Failed to load site settings:', error)
    // Set fallback title if settings fail
    document.title = 'Portfolio - Loading...'
  }
})

// CRITICAL: Reset meta tags on route change (Nov 4, 2025)
watch(
  () => route.path,
  (newPath, oldPath) => {
    // Only reset if coming from detail pages to homepage/list pages
    const fromDetailPage = oldPath?.includes('/projects/') || oldPath?.includes('/blog/')
    const toListPage = newPath === '/' || newPath === '/projects' || newPath === '/blog'
    
    if (fromDetailPage && toListPage) {
      console.log('🔄 Route changed from detail to list page, resetting meta tags')
      
      // Reset page-specific meta tags
      resetPageMeta()
      
      // Re-apply global site settings
      if (siteSettings.value) {
        setTimeout(() => {
          setMetaFromSettings(siteSettings.value)
          console.log('✅ Global meta tags restored')
        }, 100) // Small delay to ensure cleanup first
      }
    }
  }
)
</script>
