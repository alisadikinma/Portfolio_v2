<template>
  <component :is="layout">
    <router-view />
  </component>
</template>

<script setup>
import { computed, onMounted, onBeforeMount } from 'vue'
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
const { setMetaFromSettings } = useMetaTags()

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
</script>
