<template>
  <div class="min-h-screen flex flex-col bg-white dark:bg-neutral-900">
    <!-- Skip Navigation Link -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg focus:shadow-lg"
    >
      Skip to main content
    </a>

    <!-- Navigation -->
    <TheNavigation />

    <!-- Main Content -->
    <main id="main-content" class="flex-1">
      <router-view />
    </main>

    <!-- Footer -->
    <TheFooter />

    <!-- Global Toast -->
    <BaseToast ref="toastRef" position="top-right" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'
import TheNavigation from '@/components/TheNavigation.vue'
import TheFooter from '@/components/TheFooter.vue'
import { BaseToast } from '@/components/base'

const themeStore = useThemeStore()
const uiStore = useUIStore()
const toastRef = ref(null)

onMounted(() => {
  // Initialize theme
  themeStore.initTheme()
  themeStore.listenToSystemTheme()

  // Connect UI store to toast component
  // This allows stores to trigger toasts
  if (toastRef.value) {
    uiStore.toastRef = toastRef.value
  }
})
</script>
