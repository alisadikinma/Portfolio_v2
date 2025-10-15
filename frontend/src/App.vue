<template>
  <component :is="layout">
    <router-view />
  </component>
</template>

<script setup>
import { computed, onMounted, onBeforeMount } from 'vue'
import { useRoute } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()
const themeStore = useThemeStore()

const layout = computed(() => {
  const layoutName = route.meta.layout || 'default'

  const layouts = {
    default: DefaultLayout,
    admin: AdminLayout,
    auth: AuthLayout
  }

  return layouts[layoutName] || DefaultLayout
})

// Initialize theme BEFORE component mounts (critical for preventing flash)
onBeforeMount(() => {
  console.log('🚀 App.vue beforeMount - early theme init')
  themeStore.initTheme()
})

// Setup system theme listener after mount
onMounted(() => {
  console.log('✅ App.vue mounted - setting up theme listener')
  themeStore.listenToSystemTheme()
  
  // Double-check theme is applied
  themeStore.applyTheme()
})
</script>
