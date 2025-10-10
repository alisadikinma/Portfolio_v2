<template>
  <nav class="bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 sticky top-0 z-40">
    <div class="container-custom">
      <div class="flex items-center justify-between h-16">
        <!-- Logo -->
        <router-link to="/" class="flex items-center space-x-2">
          <span class="text-2xl font-display font-bold text-gradient">Portfolio</span>
        </router-link>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-8">
          <router-link
            v-for="item in navItems"
            :key="item.name"
            :to="item.path"
            class="text-neutral-700 dark:text-neutral-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors"
            active-class="text-primary-600 dark:text-primary-400"
          >
            {{ item.name }}
          </router-link>

          <!-- Theme Toggle -->
          <button
            @click="themeStore.toggleDark"
            class="p-2 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            aria-label="Toggle dark mode"
          >
            <svg v-if="themeStore.isDark" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
            </svg>
            <svg v-else class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
            </svg>
          </button>
        </div>

        <!-- Mobile Menu Button -->
        <button
          @click="uiStore.toggleMobileMenu"
          @keydown.escape="uiStore.closeMobileMenu"
          :aria-expanded="uiStore.isMobileMenuOpen"
          aria-controls="mobile-menu"
          class="md:hidden p-2 rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700"
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
    <Transition name="mobile-menu">
      <div
        v-if="uiStore.isMobileMenuOpen"
        id="mobile-menu"
        role="navigation"
        aria-label="Mobile navigation"
        @keydown.escape="uiStore.closeMobileMenu"
        class="md:hidden border-t border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800"
      >
        <div class="container-custom py-4 space-y-2">
          <router-link
            v-for="item in navItems"
            :key="item.name"
            :to="item.path"
            @click="uiStore.closeMobileMenu"
            class="block px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 font-medium transition-colors"
            active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400"
          >
            {{ item.name }}
          </router-link>

          <div class="pt-2 border-t border-neutral-200 dark:border-neutral-700">
            <button
              @click="handleThemeToggle"
              aria-label="Toggle dark mode"
              class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700"
            >
              <span>Theme</span>
              <span aria-hidden="true">{{ themeStore.isDark ? '☀️' : '🌙' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme'
import { useUIStore } from '@/stores/ui'

const themeStore = useThemeStore()
const uiStore = useUIStore()

const navItems = [
  { name: 'Home', path: '/' },
  { name: 'About', path: '/about' },
  { name: 'Projects', path: '/projects' },
  { name: 'Blog', path: '/blog' },
  { name: 'Gallery', path: '/gallery' },
  { name: 'Contact', path: '/contact' }
]

const handleThemeToggle = () => {
  themeStore.toggleDark()
  uiStore.closeMobileMenu()
}
</script>

<style scoped>
.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition: all 0.3s ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
