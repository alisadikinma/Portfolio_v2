<template>
  <footer class="pt-32 pb-8 relative overflow-hidden">
    <!-- Subtle top gradient fade -->
    <div class="absolute top-0 left-0 right-0 h-32 pointer-events-none" style="background: linear-gradient(to bottom, transparent, #050505);"></div>

    <div class="container-custom relative z-10">
      <!-- Main Footer Grid -->
      <div class="grid grid-cols-1 md:grid-cols-12 gap-12 md:gap-8 pb-16 border-b border-border-hairline">

        <!-- Brand Column — spans 5 cols -->
        <div class="md:col-span-5">
          <h3 class="text-3xl font-display font-bold text-gradient mb-5">{{ siteName }}</h3>
          <p class="text-fg-muted leading-relaxed mb-8 max-w-sm">
            {{ siteBio }}
          </p>
          <!-- Social Icons -->
          <div class="flex gap-3">
            <a
              v-for="(link, index) in displaySocialLinks"
              :key="index"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="w-10 h-10 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-accent-gold hover:border-accent-gold/30 hover:bg-accent-gold/5 transition-all duration-700 ease-spring"
              :aria-label="link.platform"
            >
              <i v-if="link.icon" :class="link.icon + ' text-sm'"></i>
              <svg v-else-if="link.platform.toLowerCase() === 'github'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
              </svg>
              <svg v-else-if="link.platform.toLowerCase() === 'linkedin'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg>
              <svg v-else-if="link.platform.toLowerCase() === 'twitter' || link.platform.toLowerCase() === 'x'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
              </svg>
              <svg v-else-if="link.platform.toLowerCase() === 'instagram'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
              </svg>
              <svg v-else-if="link.platform.toLowerCase() === 'youtube'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
              </svg>
              <svg v-else-if="link.platform.toLowerCase().includes('tiktok')" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/>
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" />
              </svg>
            </a>
          </div>
        </div>

        <!-- Quick Links Column — spans 2 cols -->
        <div class="md:col-span-2 md:col-start-7">
          <p class="eyebrow-tag text-fg-muted mb-6">Navigation</p>
          <ul class="space-y-3">
            <li v-for="item in quickLinks" :key="item.name">
              <router-link
                :to="item.path"
                class="text-sm text-fg-muted hover:text-accent-gold transition-all duration-700 ease-spring"
              >
                {{ item.name }}
              </router-link>
            </li>
          </ul>
        </div>

        <!-- Resources Column — spans 2 cols -->
        <div class="md:col-span-2">
          <p class="eyebrow-tag text-fg-muted mb-6">Resources</p>
          <ul class="space-y-3">
            <li v-for="item in resources" :key="item.name">
              <router-link
                :to="item.path"
                class="text-sm text-fg-muted hover:text-accent-gold transition-all duration-700 ease-spring"
              >
                {{ item.name }}
              </router-link>
            </li>
          </ul>
        </div>

        <!-- Contact Column — spans 3 cols -->
        <div class="md:col-span-3">
          <p class="eyebrow-tag text-fg-muted mb-6">Get in Touch</p>
          <ul class="space-y-4 text-sm text-fg-muted">
            <li>
              <a :href="`mailto:${siteEmail}`" class="flex items-center gap-3 hover:text-accent-gold transition-all duration-700 ease-spring group">
                <span class="w-8 h-8 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center group-hover:border-accent-gold/30 transition-all duration-700 ease-spring">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                  </svg>
                </span>
                {{ siteEmail }}
              </a>
            </li>
            <li v-if="sitePhone">
              <a :href="whatsappUrl" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 hover:text-accent-gold transition-all duration-700 ease-spring group">
                <span class="w-8 h-8 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center group-hover:border-accent-gold/30 transition-all duration-700 ease-spring">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                  </svg>
                </span>
                {{ sitePhone }}
              </a>
            </li>
            <li class="flex items-center gap-3">
              <span class="w-8 h-8 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                </svg>
              </span>
              <span>{{ siteLocation }}</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar — Minimal -->
      <div class="pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-xs text-fg-dim">
          &copy; {{ currentYear }} {{ siteName }}. All rights reserved.
        </p>
        <div class="flex gap-6">
          <a href="#" class="text-xs text-fg-dim hover:text-fg-muted transition-all duration-700 ease-spring">
            Privacy
          </a>
          <a href="#" class="text-xs text-fg-dim hover:text-fg-muted transition-all duration-700 ease-spring">
            Terms
          </a>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const aboutSettings = ref({})
const siteSettings = ref({})
const menuItems = ref([])
const currentYear = computed(() => new Date().getFullYear())

onMounted(async () => {
  try {
    const [aboutRes, siteRes, menuRes] = await Promise.all([
      api.get('/settings/about'),
      api.get('/settings/site'),
      api.get('/menu-items')
    ])

    if (aboutRes.data.success) aboutSettings.value = aboutRes.data.data
    if (siteRes.data.success) siteSettings.value = siteRes.data.data
    if (menuRes.data.success) menuItems.value = menuRes.data.data
  } catch (error) {
    console.error('Failed to fetch settings:', error)
  }
})

const siteName = computed(() => siteSettings.value.site_name || 'Ali Sadikin Ma')
const siteBio = computed(() => siteSettings.value.site_description || 'AI Generalist Expert specializing in prompt engineering, AI agents, and no-code product development.')
const siteEmail = computed(() => siteSettings.value.contact_email || 'contact@example.com')
const sitePhone = computed(() => siteSettings.value.contact_phone || '')
const siteLocation = computed(() => siteSettings.value.location || 'Jakarta, Indonesia')

const whatsappUrl = computed(() => {
  if (!sitePhone.value) return '#'
  const cleanPhone = sitePhone.value.replace(/\D/g, '')
  const phone = cleanPhone.startsWith('0') ? '62' + cleanPhone.substring(1) : cleanPhone
  return `https://wa.me/${phone}`
})

const displaySocialLinks = computed(() => {
  if (aboutSettings.value.social_links && Array.isArray(aboutSettings.value.social_links)) {
    return aboutSettings.value.social_links
  }
  return []
})

const quickLinks = computed(() => {
  return menuItems.value.slice(0, 4).map(item => ({
    name: item.title,
    path: item.url
  }))
})

const resources = computed(() => {
  return menuItems.value.slice(4, 7).map(item => ({
    name: item.title,
    path: item.url
  }))
})
</script>
