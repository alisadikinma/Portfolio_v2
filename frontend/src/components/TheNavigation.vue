<template>
  <!-- Floating Glass Pill Navigation — hidden at top, reveals on scroll -->
  <nav class="fixed top-0 left-0 right-0 z-40 flex justify-center pointer-events-none">
    <div
      class="pointer-events-auto mt-5 mx-4 transition-all duration-700 ease-spring"
      :class="[
        isScrolled || menuOpen
          ? 'w-full max-w-6xl opacity-100 translate-y-0'
          : 'w-full max-w-4xl opacity-0 -translate-y-4'
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
            <span class="whitespace-nowrap text-xs sm:text-sm font-display font-bold text-gradient">
              {{ siteName }}
            </span>
          </router-link>

          <!-- Desktop Navigation Links — homepage section anchors -->
          <div class="hidden md:flex items-center gap-1">
            <a
              v-for="item in sectionLinks"
              :key="item.id"
              :href="`#${item.id}`"
              @click.prevent="goToSection(item.id)"
              class="relative whitespace-nowrap px-3 py-1.5 rounded-full text-sm font-medium text-fg-muted transition-all duration-700 ease-spring cursor-pointer"
              :class="[
                isActive(item.id)
                  ? 'text-accent-gold bg-accent-gold/15 ring-1 ring-accent-gold/50 font-semibold shadow-glow-gold'
                  : 'hover:text-fg-primary hover:bg-white/5'
              ]"
            >
              {{ item.name }}
            </a>
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
            <a
              v-for="(item, index) in sectionLinks"
              :key="item.id"
              :href="`#${item.id}`"
              @click.prevent="goToSection(item.id)"
              class="w-full max-w-xs flex items-center justify-center gap-3 px-8 py-5 rounded-2xl text-2xl font-display font-semibold text-fg-muted transition-all duration-700 ease-spring cursor-pointer"
              :class="[
                isActive(item.id)
                  ? 'text-accent-gold bg-accent-gold/10 border border-accent-gold/50 shadow-glow-gold'
                  : 'hover:text-fg-primary hover:bg-white/5'
              ]"
              :style="{ transitionDelay: `${100 + index * 80}ms` }"
            >
              {{ item.name }}
            </a>
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
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUIStore } from '@/stores/ui'
import { useSiteSettings } from '@/composables/useSiteSettings'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { locale } = useI18n()

const route = useRoute()
const router = useRouter()
const uiStore = useUIStore()
const { siteName, siteLogo, fetchSiteSettings } = useSiteSettings()

const isScrolled = ref(false)
const menuOpen = ref(false)
const activeSection = ref('')
const reducedMotion = ref(false)

// The header menu IS the homepage section spine. Each item smooth-scrolls to its
// section on the homepage, or routes home + hash from any other page (the router
// scrollBehavior resolves the hash with a smooth scroll on arrival).
const sectionLinks = [
  { name: 'Who I Am', id: 'who-i-am' },
  { name: 'What I Solve', id: 'what-i-solve' },
  { name: 'Receipts', id: 'receipts' },
  { name: 'Awards', id: 'international-stages' },
  { name: 'My Projects', id: 'selected-work' },
  { name: 'Blogs', id: 'latest-writing' },
  { name: 'Contact', id: 'join-the-build' },
]

function goToSection(id) {
  closeMenu()
  if (isHomePage.value) {
    const el = document.getElementById(id)
    if (el) el.scrollIntoView({ behavior: reducedMotion.value ? 'auto' : 'smooth' })
  } else {
    const lang = route.params.lang || locale.value || 'en'
    router.push({ name: 'home', params: { lang }, hash: `#${id}` })
  }
}

function isActive(id) {
  return isHomePage.value && activeSection.value === id
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

// Scroll-spy: highlight the nav item for the section currently under a reference
// line ~38% down the viewport. Queries section positions LIVE on each scroll, so
// it's robust to mount timing (no one-shot observer that misses late-rendered
// sections) and naturally lands on the last section (Contact) at page bottom.
function updateActiveSection() {
  if (!isHomePage.value) return
  const line = window.innerHeight * 0.38
  let current = ''
  // sectionLinks is in document order → the last section whose top has crossed
  // above the line is the one occupying the viewport's reading zone.
  for (const l of sectionLinks) {
    const el = document.getElementById(l.id)
    if (!el) continue
    if (el.getBoundingClientRect().top <= line) current = l.id
  }
  // Near the very bottom, make sure the final section wins even if its top never
  // reaches the line (short last section / tall viewport).
  if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
    const last = [...sectionLinks].reverse().find((l) => document.getElementById(l.id))
    if (last) current = last.id
  }
  if (!current) current = sectionLinks[0].id
  if (activeSection.value !== current) activeSection.value = current
}

let scrollRaf = null
const handleScroll = () => {
  if (isHomePage.value) {
    // Home page only: hide at top, reveal after scrolling past hero
    isScrolled.value = window.scrollY > window.innerHeight * 0.8
  } else {
    // All other pages: always visible
    isScrolled.value = true
  }
  // rAF-throttle the active-section recompute (scroll fires very frequently).
  if (scrollRaf) return
  scrollRaf = requestAnimationFrame(() => {
    scrollRaf = null
    updateActiveSection()
  })
}

// When route changes, immediately update visibility
watch(isHomePage, (isHome) => {
  if (!isHome) {
    isScrolled.value = true
  } else {
    handleScroll()
    // Sections need a tick to mount after the route resolves.
    clearTimeout(activeSectionTimer)
    activeSectionTimer = setTimeout(updateActiveSection, 400)
  }
})

let mqMotion
let activeSectionTimer
function syncMotion(e) { reducedMotion.value = e.matches }

onMounted(async () => {
  // Set initial state before scroll listener
  if (!isHomePage.value) {
    isScrolled.value = true
  }
  window.addEventListener('scroll', handleScroll, { passive: true })

  mqMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
  reducedMotion.value = mqMotion.matches
  mqMotion.addEventListener?.('change', syncMotion)

  // Initial active-section highlight. Recompute once immediately and again after
  // sections have had a tick to render (homepage data + section v-if resolve).
  updateActiveSection()
  activeSectionTimer = setTimeout(updateActiveSection, 400)
  // Lazy media above the viewport can shift section offsets after load while the
  // user is stationary — recompute once everything has loaded so the highlight
  // isn't left stale until the next scroll.
  if (document.readyState === 'complete') {
    setTimeout(updateActiveSection, 1200)
  } else {
    window.addEventListener('load', updateActiveSection, { once: true })
  }

  await fetchSiteSettings(true)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  mqMotion?.removeEventListener?.('change', syncMotion)
  clearTimeout(activeSectionTimer)
  if (scrollRaf) cancelAnimationFrame(scrollRaf)
})
</script>
