<template>
  <!-- Floating Glass Pill Navigation — hidden at top, reveals on scroll -->
  <nav class="fixed top-0 left-0 right-0 z-40 flex justify-center pointer-events-none">
    <div
      class="pointer-events-auto mt-5 mx-4 transition-all duration-700 ease-spring"
      :class="[
        isScrolled || menuOpen
          ? 'w-full max-w-3xl opacity-100 translate-y-0'
          : 'w-full max-w-2xl opacity-0 -translate-y-4'
      ]"
    >
      <!-- Outer Bezel Shell -->
      <div class="bezel-shell-sm">
        <!-- Inner Core — the glass pill -->
        <div
          class="bezel-core-sm px-4 md:px-6 py-3 flex items-center justify-between"
          :class="[
            isScrolled ? 'shadow-glass-lg' : ''
          ]"
        >
          <!-- Logo -->
          <router-link
            :to="`/${route.params.lang || locale || 'en'}`"
            class="flex items-center gap-2.5 group"
          >
            <div v-if="siteLogo" class="w-8 h-8 rounded-xl overflow-hidden transition-all duration-700 ease-spring group-hover:shadow-glow-gold">
              <img :src="siteLogo" :alt="siteName" class="w-full h-full object-cover" />
            </div>
            <div v-else class="w-8 h-8 rounded-xl bg-gradient-to-br from-accent-gold to-accent-cyan flex items-center justify-center transition-all duration-700 ease-spring group-hover:shadow-glow-gold">
              <span class="text-bg-deep font-bold text-sm">{{ siteName.charAt(0).toUpperCase() }}</span>
            </div>
            <span class="text-xs sm:text-sm font-display font-bold text-gradient">
              {{ siteName }}
            </span>
          </router-link>

          <!-- Desktop Navigation Links -->
          <div class="hidden md:flex items-center gap-1">
            <router-link
              v-for="item in navItems"
              :key="item.name"
              :to="item.path"
              class="relative px-3.5 py-1.5 rounded-full text-sm font-medium text-fg-muted transition-all duration-700 ease-spring flex items-center gap-1.5"
              :class="[
                isActive(item.path)
                  ? 'text-accent-gold bg-accent-gold/8'
                  : 'hover:text-fg-primary hover:bg-white/5'
              ]"
            >
              <IconDisplay v-if="item.icon" :name="item.icon" class="w-3.5 h-3.5" />
              {{ item.name }}
            </router-link>
          </div>

          <!-- Language Switcher (desktop) -->
          <div class="hidden md:flex items-center ml-2">
            <LanguageSwitcher />
          </div>

          <!-- Hamburger Morph Button (mobile) -->
          <button
            @click="toggleMenu"
            class="md:hidden relative w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/5 transition-all duration-700 ease-spring"
            aria-label="Toggle menu"
          >
            <span
              class="absolute w-5 h-[1.5px] bg-fg-primary rounded-full transition-all duration-500 ease-spring"
              :class="[
                menuOpen
                  ? 'rotate-45 translate-y-0'
                  : '-translate-y-[5px]'
              ]"
            />
            <span
              class="absolute w-5 h-[1.5px] bg-fg-primary rounded-full transition-all duration-500 ease-spring"
              :class="[
                menuOpen ? 'opacity-0 scale-x-0' : 'opacity-100'
              ]"
            />
            <span
              class="absolute w-5 h-[1.5px] bg-fg-primary rounded-full transition-all duration-500 ease-spring"
              :class="[
                menuOpen
                  ? '-rotate-45 translate-y-0'
                  : 'translate-y-[5px]'
              ]"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Menu — Full-screen Glass Overlay with Staggered Reveal -->
    <Transition
      enter-active-class="transition-all duration-500 ease-spring"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-all duration-300 ease-spring"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="menuOpen"
        class="md:hidden fixed inset-0 bg-bg-deep/95 backdrop-blur-3xl z-50 pointer-events-auto"
      >
        <!-- Close button -->
        <button
          @click="closeMenu"
          class="absolute top-6 right-6 w-10 h-10 flex items-center justify-center rounded-full bg-white/5 border border-white/10 text-fg-primary hover:bg-white/10 transition-all duration-300 z-10"
          aria-label="Close menu"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <div class="flex flex-col items-center justify-center h-full px-8 gap-3">
          <TransitionGroup
            appear
            enter-active-class="transition-all duration-600 ease-spring"
            enter-from-class="opacity-0 translate-y-8"
            enter-to-class="opacity-100 translate-y-0"
          >
            <router-link
              v-for="(item, index) in navItems"
              :key="item.name"
              :to="item.path"
              @click="closeMenu"
              class="w-full max-w-xs flex items-center justify-center gap-3 px-8 py-5 rounded-2xl text-2xl font-display font-semibold text-fg-muted transition-all duration-700 ease-spring"
              :class="[
                isActive(item.path)
                  ? 'text-accent-gold bg-white/5 border border-accent-gold/20'
                  : 'hover:text-fg-primary hover:bg-white/5'
              ]"
              :style="{ transitionDelay: `${100 + index * 80}ms` }"
            >
              <IconDisplay v-if="item.icon" :name="item.icon" class="w-5 h-5" />
              {{ item.name }}
            </router-link>
          </TransitionGroup>
          <!-- Language Switcher (mobile) -->
          <div class="mt-6">
            <LanguageSwitcher />
          </div>
        </div>
      </div>
    </Transition>
  </nav>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUIStore } from '@/stores/ui'
import { useMenuItems } from '@/composables/useMenuItems'
import { useSiteSettings } from '@/composables/useSiteSettings'
import IconDisplay from '@/components/admin/IconDisplay.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { locale } = useI18n()

const route = useRoute()
const uiStore = useUIStore()
const { menuItems, fetchActiveMenuItems } = useMenuItems()
const { siteName, siteLogo, fetchSiteSettings } = useSiteSettings()

const isScrolled = ref(false)
const menuOpen = ref(false)

const navItems = computed(() => {
  const lang = route.params.lang || locale.value || 'en'
  return menuItems.value.map(item => ({
    name: item.title,
    path: `/${lang}${item.url}`,
    icon: item.icon
  }))
})

function isActive(path) {
  if (route.path === path) return true
  if (route.path.startsWith(path + '/')) return true
  // Also match if path is just the lang root (e.g., /en) and we're on home
  const lang = route.params.lang || locale.value || 'en'
  return path === `/${lang}` && route.path === `/${lang}`
}

function toggleMenu() {
  menuOpen.value = !menuOpen.value
  uiStore.isMobileMenuOpen = menuOpen.value
}

function closeMenu() {
  menuOpen.value = false
  uiStore.isMobileMenuOpen = false
}

const isHomePage = computed(() => {
  const lang = route.params.lang || locale.value || 'en'
  return route.path === '/' || route.path === `/${lang}`
})

const handleScroll = () => {
  if (isHomePage.value) {
    // Home page only: hide at top, reveal after scrolling past hero
    isScrolled.value = window.scrollY > window.innerHeight * 0.8
  } else {
    // All other pages: always visible
    isScrolled.value = true
  }
}

// When route changes, immediately update visibility
watch(isHomePage, (isHome) => {
  if (!isHome) {
    isScrolled.value = true
  } else {
    handleScroll()
  }
})

onMounted(async () => {
  // Set initial state before scroll listener
  if (!isHomePage.value) {
    isScrolled.value = true
  }
  window.addEventListener('scroll', handleScroll, { passive: true })
  await Promise.all([
    fetchActiveMenuItems(),
    fetchSiteSettings(true)
  ])
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>
