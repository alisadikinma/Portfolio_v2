<template>
  <nav
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
    :class="[
      isScrolled
        ? 'glass shadow-lg py-3'
        : 'bg-white/50 dark:bg-gray-950/50 backdrop-blur-sm py-4'
    ]"
  >
    <div class="container-custom">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <router-link
          to="/"
          class="flex items-center space-x-2 group"
        >
          <!-- Dynamic Logo -->
          <div v-if="siteLogo" class="w-10 h-10 rounded-xl overflow-hidden shadow-lg group-hover:shadow-xl transition-shadow">
            <img :src="siteLogo" :alt="siteName" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
            <span class="text-white font-bold text-xl">{{ siteName.charAt(0).toUpperCase() }}</span>
          </div>
          
          <!-- Dynamic Site Name - Show on all screens -->
          <span class="text-xl font-display font-bold text-gradient">
            {{ siteName }}
          </span>
        </router-link>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-1">
          <router-link
            v-for="item in navItems"
            :key="item.name"
            :to="item.path"
            class="relative px-4 py-2 rounded-lg font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group flex items-center gap-2"
            active-class="text-primary-600 dark:text-primary-400"
          >
            <IconDisplay v-if="item.icon" :name="item.icon" class="w-4 h-4" />
            {{ item.name }}
            <!-- Active Indicator -->
            <span
              class="absolute bottom-0 left-1/2 -translate-x-1/2 w-0 h-0.5 bg-gradient-to-r from-primary-500 to-secondary-500 group-hover:w-full transition-all duration-300 rounded-full"
            ></span>
          </router-link>
        </div>

        <!-- Mobile Menu Button -->
        <button
          @click="uiStore.toggleMobileMenu"
          class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300"
          aria-label="Toggle menu"
        >
          <svg v-if="!uiStore.isMobileMenuOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile Menu -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="uiStore.isMobileMenuOpen"
        class="md:hidden mt-4 glass rounded-2xl p-4 mx-4"
      >
        <router-link
          v-for="item in navItems"
          :key="item.name"
          :to="item.path"
          @click="uiStore.closeMobileMenu"
          class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors"
          active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400"
        >
          <IconDisplay v-if="item.icon" :name="item.icon" class="w-5 h-5" />
          {{ item.name }}
        </router-link>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'
import { useMenuItems } from '@/composables/useMenuItems'
import { useSiteSettings } from '@/composables/useSiteSettings'
import IconDisplay from '@/components/admin/IconDisplay.vue'

const themeStore = useThemeStore()
const uiStore = useUIStore()
const { menuItems, isLoading, fetchActiveMenuItems } = useMenuItems()
const { siteName, siteLogo, fetchSiteSettings } = useSiteSettings()

const isScrolled = ref(false)

// Computed property to transform menu items to navigation format
const navItems = computed(() => {
  return menuItems.value.map(item => ({
    name: item.title,
    path: item.url,
    icon: item.icon
  }))
})

const handleScroll = () => {
  isScrolled.value = window.scrollY > 20
}

onMounted(async () => {
  window.addEventListener('scroll', handleScroll)

  // Fetch active menu items and site settings from API (force refresh)
  await Promise.all([
    fetchActiveMenuItems(),
    fetchSiteSettings(true) // Force refresh to get latest data
  ])
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>
