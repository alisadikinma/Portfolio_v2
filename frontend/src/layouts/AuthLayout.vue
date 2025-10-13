<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50 dark:from-neutral-900 dark:to-neutral-800 px-4">
    <div class="w-full max-w-md">
      <router-view />
    </div>

    <!-- Global Toast -->
    <BaseToast ref="toastRef" position="top-right" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'
import { BaseToast } from '@/components/base'

const themeStore = useThemeStore()
const uiStore = useUIStore()
const toastRef = ref(null)

onMounted(() => {
  themeStore.initTheme()
  themeStore.listenToSystemTheme()

  if (toastRef.value) {
    uiStore.toastRef = toastRef.value
  }
})
</script>
