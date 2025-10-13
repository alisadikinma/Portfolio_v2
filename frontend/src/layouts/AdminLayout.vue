<template>
  <div class="min-h-screen bg-neutral-100 dark:bg-neutral-900">
    <div class="flex">
      <!-- Sidebar -->
      <aside
        class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-neutral-800 border-r border-neutral-200 dark:border-neutral-700 transform transition-transform duration-300"
        :class="{ '-translate-x-full': !uiStore.isSidebarOpen, 'translate-x-0': uiStore.isSidebarOpen }"
      >
        <div class="h-full flex flex-col">
          <!-- Sidebar Header -->
          <div class="h-16 flex items-center justify-between px-6 border-b border-neutral-200 dark:border-neutral-700">
            <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100">Admin</h2>
            <button
              @click="uiStore.toggleSidebar"
              class="lg:hidden text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
            >
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Sidebar Navigation -->
          <nav class="flex-1 overflow-y-auto p-4">
            <router-link
              to="/admin"
              class="flex items-center px-4 py-3 mb-2 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700"
            >
              Dashboard
            </router-link>
          </nav>

          <!-- Sidebar Footer -->
          <div class="p-4 border-t border-neutral-200 dark:border-neutral-700">
            <button
              @click="handleLogout"
              class="w-full px-4 py-2 text-sm text-error-600 hover:bg-error-50 dark:hover:bg-error-900/20 rounded-lg"
            >
              Logout
            </button>
          </div>
        </div>
      </aside>

      <!-- Main Content -->
      <div class="flex-1 lg:ml-64">
        <!-- Top Bar -->
        <header class="h-16 bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between px-6">
          <button
            @click="uiStore.toggleSidebar"
            class="text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300"
          >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div class="flex items-center gap-4">
            <button
              @click="themeStore.toggleDark"
              class="p-2 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700"
            >
              <span v-if="themeStore.isDark">☀️</span>
              <span v-else>🌙</span>
            </button>
          </div>
        </header>

        <!-- Page Content -->
        <main class="p-6">
          <router-view />
        </main>
      </div>
    </div>

    <!-- Global Toast -->
    <BaseToast ref="toastRef" position="top-right" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { BaseToast } from '@/components/base'

const router = useRouter()
const themeStore = useThemeStore()
const uiStore = useUIStore()
const authStore = useAuthStore()
const toastRef = ref(null)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}

onMounted(() => {
  themeStore.initTheme()
  themeStore.listenToSystemTheme()

  if (toastRef.value) {
    uiStore.toastRef = toastRef.value
  }
})
</script>
