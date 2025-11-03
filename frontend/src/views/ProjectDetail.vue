<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
    <!-- Floating Share Button -->
    <div 
      v-if="project" 
      class="fixed right-4 top-1/2 -translate-y-1/2 z-50 flex flex-col gap-3"
    >
      <!-- Main Share Button -->
      <button
        @click="toggleShareMenu"
        class="group relative w-14 h-14 bg-primary-600 hover:bg-primary-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center"
        :class="{ 'rotate-45': showShareMenu }"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
        </svg>
        <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-sm rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
          Share
        </span>
      </button>

      <!-- Share Options -->
      <transition name="share-menu">
        <div v-if="showShareMenu" class="flex flex-col gap-2">
          <!-- WhatsApp -->
          <a
            :href="shareLinks.whatsapp"
            target="_blank"
            rel="noopener noreferrer"
            class="group relative w-12 h-12 bg-green-500 hover:bg-green-600 text-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              WhatsApp
            </span>
          </a>

          <!-- Facebook -->
          <a
            :href="shareLinks.facebook"
            target="_blank"
            rel="noopener noreferrer"
            class="group relative w-12 h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              Facebook
            </span>
          </a>

          <!-- Twitter -->
          <a
            :href="shareLinks.twitter"
            target="_blank"
            rel="noopener noreferrer"
            class="group relative w-12 h-12 bg-sky-500 hover:bg-sky-600 text-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
            </svg>
            <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              Twitter
            </span>
          </a>

          <!-- LinkedIn -->
          <a
            :href="shareLinks.linkedin"
            target="_blank"
            rel="noopener noreferrer"
            class="group relative w-12 h-12 bg-blue-700 hover:bg-blue-800 text-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
            </svg>
            <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              LinkedIn
            </span>
          </a>

          <!-- Copy Link -->
          <button
            @click="copyLink"
            class="group relative w-12 h-12 bg-neutral-700 hover:bg-neutral-800 text-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center"
          >
            <svg v-if="!linkCopied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="absolute right-full mr-3 px-3 py-1 bg-neutral-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
              {{ linkCopied ? 'Copied!' : 'Copy Link' }}
            </span>
          </button>
        </div>
      </transition>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-4">Project Not Found</h1>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">{{ error }}</p>
        <router-link
          to="/projects"
          class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
        >
          ← Back to Projects
        </router-link>
      </div>
    </div>

    <!-- Project Content -->
    <template v-else>
      <!-- Breadcrumb Navigation -->
      <div class="bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 py-4 mt-20">
        <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
          <nav class="flex items-center space-x-2 text-sm font-medium">
            <router-link 
              to="/" 
              class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              🏠 Home
            </router-link>
            <span class="text-neutral-400 dark:text-neutral-600">/</span>
            <router-link 
              to="/projects" 
              class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              💼 Projects
            </router-link>
            <template v-if="project">
              <span class="text-neutral-400 dark:text-neutral-600">/</span>
              <span class="text-neutral-900 dark:text-neutral-100 font-semibold truncate max-w-md">
                {{ project.title }}
              </span>
            </template>
          </nav>
        </div>
      </div>

      <!-- Main Content -->
      <div v-if="project" class="py-12">
        <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
          
          <!-- Project Image - Responsive WebP -->
          <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-lg overflow-hidden mb-12">
            <picture>
              <!-- Mobile: 600px WebP -->
              <source 
                :srcset="getImageUrl(project.slug, '600', 'webp')"
                media="(max-width: 767px)"
                type="image/webp">
              
              <!-- Tablet: 900px WebP -->
              <source 
                :srcset="getImageUrl(project.slug, '900', 'webp')"
                media="(max-width: 1023px)"
                type="image/webp">
              
              <!-- Desktop: 1200px WebP -->
              <source 
                :srcset="getImageUrl(project.slug, '1200', 'webp')"
                type="image/webp">
              
              <!-- Fallback: JPEG for old browsers -->
              <img 
                :src="getImageUrl(project.slug, '1200', 'jpg')"
                :alt="project.title"
                loading="lazy"
                class="w-full h-auto"
                @error="handleImageError">
            </picture>
          </div>

        </div>

        <!-- Related Projects Section (BEFORE CTA) -->
        <div 
          v-if="project.related_projects && project.related_projects.length > 0" 
          class="mt-16"
        >
          <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
            <!-- Section Header -->
            <h2 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-8">
              🔗 Related Projects
            </h2>

            <!-- Container dengan Background -->
            <div class="bg-white dark:bg-neutral-800 rounded-2xl p-8 shadow-lg border border-neutral-200 dark:border-neutral-700">
              <!-- Horizontal Scroll Container -->
              <div class="relative">
                <div class="overflow-x-auto scrollbar-hide pb-4">
                  <div class="flex gap-6" style="width: max-content;">
                    <div
                      v-for="relatedProject in project.related_projects"
                      :key="relatedProject.id"
                      class="group cursor-pointer bg-neutral-50 dark:bg-neutral-700/50 rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-300 border border-neutral-200 dark:border-neutral-600"
                      style="width: 280px; flex-shrink: 0;"
                      @click="navigateToProject(relatedProject.slug)"
                    >
                      <!-- Image -->
                      <div class="relative h-40 bg-neutral-200 dark:bg-neutral-600 overflow-hidden">
                        <img
                          :src="relatedProject.thumbnail || relatedProject.featured_image || getImageUrl(relatedProject.slug, '600', 'webp')"
                          :alt="relatedProject.title"
                          class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                          @error="handleRelatedImageError"
                        />
                      </div>

                      <!-- Content -->
                      <div class="p-4 space-y-2">
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                          {{ relatedProject.title }}
                        </h3>
                        <p class="text-xs text-neutral-600 dark:text-neutral-400 line-clamp-2">
                          {{ relatedProject.description }}
                        </p>
                        <div class="pt-1">
                          <span class="text-xs font-medium text-primary-600 dark:text-primary-400 group-hover:underline">
                            View Project →
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Scroll Indicator -->
                <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                  <svg class="w-4 h-4 animate-bounce-x" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                  </svg>
                  <span>Scroll for more</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dynamic CTA Section (AFTER Related Projects) -->
        <div class="mt-16">
          <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
            <div class="relative bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 rounded-3xl p-10 md:p-16 text-white shadow-2xl overflow-hidden">
              
              <!-- Animated background pattern -->
              <div class="absolute inset-0 opacity-10">
                <div class="absolute top-0 left-0 w-72 h-72 bg-white rounded-full mix-blend-overlay filter blur-3xl animate-pulse"></div>
                <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full mix-blend-overlay filter blur-3xl animate-pulse animation-delay-2000"></div>
              </div>

              <!-- Content -->
              <div class="relative z-10">
                <!-- Dynamic Headline -->
                <div class="flex items-start gap-4 mb-6">
                  <div class="text-5xl">{{ ctaData.icon }}</div>
                  <div>
                    <h2 class="text-3xl md:text-5xl font-bold mb-3 leading-tight">
                      {{ ctaData.headline }}
                    </h2>
                    <p class="text-lg md:text-2xl opacity-95 font-medium">
                      {{ ctaData.subheadline }}
                    </p>
                  </div>
                </div>

                <!-- Value Props -->
                <div class="grid md:grid-cols-3 gap-6 mb-10">
                  <div v-for="(benefit, index) in ctaData.benefits" :key="index" 
                       class="flex items-start gap-3 bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <span class="text-2xl">{{ benefit.icon }}</span>
                    <div>
                      <h4 class="font-bold text-lg mb-1">{{ benefit.title }}</h4>
                      <p class="text-sm opacity-90">{{ benefit.text }}</p>
                    </div>
                  </div>
                </div>

                <!-- CTA Description -->
                <p class="text-xl md:text-2xl opacity-95 mb-8 leading-relaxed max-w-4xl">
                  {{ ctaData.description }}
                </p>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4">
                  <a
                  :href="whatsappLink"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group inline-flex items-center justify-center gap-3 px-10 py-5 bg-white text-primary-600 hover:bg-neutral-50 rounded-2xl font-bold text-lg transition-all duration-300 shadow-xl hover:shadow-2xl hover:scale-105"
                  >
                  <span class="text-2xl">💬</span>
                  <span>WhatsApp Now</span>
                    <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                  </a>

                  <router-link
                    to="/contact"
                    class="inline-flex items-center justify-center gap-3 px-10 py-5 bg-white/20 hover:bg-white/30 backdrop-blur-sm border-2 border-white/30 rounded-2xl font-bold text-lg transition-all duration-300 hover:scale-105"
                  >
                    <span class="text-2xl">📧</span>
                    <span>Email Kami</span>
                  </router-link>
                </div>

                <!-- Urgency Indicator -->
                <div class="mt-8 flex items-center gap-3 text-sm">
                  <div class="flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-full">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span class="font-semibold">Available Now</span>
                  </div>
                  <span class="opacity-90">⚡ FREE Consultation - 24h Response Time</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useMetaTags } from '@/composables/useMetaTags'

const route = useRoute()
const router = useRouter()
const { updatePageMeta, updateMetaTag } = useMetaTags()

// State
const project = ref(null)
const loading = ref(true)
const error = ref(null)
const showShareMenu = ref(false)
const linkCopied = ref(false)

// Current URL for sharing
const currentUrl = computed(() => {
  if (typeof window === 'undefined') return ''
  return window.location.href
})

// Share text with thumbnail
const shareText = computed(() => {
  if (!project.value) return ''
  return `${project.value.title} - ${project.value.description}\n\nPortfolio: https://alisadikinma.com\nEmail: ali.sadikincom85@gmail.com\nWhatsApp: +6281380163758`
})

// Thumbnail URL for Open Graph (from project meta tags)
const thumbnailUrl = computed(() => {
  if (!project.value) return ''
  
  // Priority: og_image > featured_image > constructed URL
  if (project.value.og_image) {
    // If og_image is full URL, use it
    if (project.value.og_image.startsWith('http')) {
      return project.value.og_image
    }
    // If og_image is relative path, construct full URL
    const backendUrl = import.meta.env.VITE_API_BASE_URL.replace('/api', '')
    return `${backendUrl}${project.value.og_image}`
  }
  
  // Fallback to featured_image from API
  if (project.value.featured_image) {
    return project.value.featured_image
  }
  
  // Last resort: construct from slug
  return getImageUrl(project.value.slug, '600', 'webp')
})

// Share links with thumbnail
const shareLinks = computed(() => {
  const url = encodeURIComponent(currentUrl.value)
  const text = encodeURIComponent(shareText.value)
  const image = encodeURIComponent(thumbnailUrl.value)
  
  return {
    whatsapp: `https://wa.me/?text=${text}%0A${url}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
    twitter: `https://twitter.com/intent/tweet?url=${url}&text=${text}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
  }
})

// Toggle share menu
function toggleShareMenu() {
  showShareMenu.value = !showShareMenu.value
}

// Copy link to clipboard
async function copyLink() {
  try {
    await navigator.clipboard.writeText(currentUrl.value)
    linkCopied.value = true
    setTimeout(() => {
      linkCopied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

// Dynamic CTA data based on project title/slug
const ctaData = computed(() => {
  if (!project.value) return getDefaultCta()
  
  const title = project.value.title.toLowerCase()
  const slug = project.value.slug.toLowerCase()
  
  // AI/ML Projects
  if (title.includes('ai') || title.includes('inspection') || title.includes('counting')) {
    return {
      icon: '🤖',
      headline: 'Interested in Our AI Solution?',
      subheadline: 'Automate Your Business with Computer Vision Technology',
      description: 'Save up to 80% time and achieve 99.5% accuracy with custom AI systems designed specifically for your business needs.',
      benefits: [
        { icon: '⚡', title: '80% Faster', text: 'Automated manual processes' },
        { icon: '🎯', title: '99.5% Accuracy', text: 'Real-time high-precision detection' },
        { icon: '💰', title: '6-Month ROI', text: 'Reduced operational costs' }
      ]
    }
  }
  
  // IoT/Smart Systems
  if (title.includes('iot') || title.includes('smart') || title.includes('monitoring')) {
    return {
      icon: '📡',
      headline: 'Need IoT & Monitoring System?',
      subheadline: 'Real-time Monitoring for Complete Operational Control',
      description: 'Real-time dashboards, automated notifications, and remote control for maximum efficiency. Integrate all your devices in one platform.',
      benefits: [
        { icon: '📊', title: 'Real-time Data', text: '24/7 non-stop monitoring' },
        { icon: '🔔', title: 'Auto Alerts', text: 'Instant notifications via app' },
        { icon: '🌐', title: 'Remote Control', text: 'Control from anywhere' }
      ]
    }
  }
  
  // Web/Mobile Apps
  if (title.includes('apps') || title.includes('system') || title.includes('platform') || title.includes('mysatnusa')) {
    return {
      icon: '📱',
      headline: 'Want a Custom System Like This?',
      subheadline: 'Powerful and User-Friendly Web & Mobile Apps',
      description: 'Digitalize your business processes with custom applications that are scalable, secure, and easy to use. From concept to deployment, we handle everything.',
      benefits: [
        { icon: '🚀', title: 'Fast Development', text: '2-3 months from concept to live' },
        { icon: '🔒', title: 'Enterprise Security', text: 'Guaranteed data protection' },
        { icon: '📈', title: 'Scalable', text: 'Grows with your business' }
      ]
    }
  }
  
  // Dashboard/Analytics
  if (title.includes('dashboard') || title.includes('analytics') || title.includes('report')) {
    return {
      icon: '📊',
      headline: 'Need a Dashboard to Visualize Your Data?',
      subheadline: 'Business Intelligence for Better Decision Making',
      description: 'Transform complex data into actionable insights. Interactive dashboards with real-time visualization for monitoring KPIs and business performance.',
      benefits: [
        { icon: '💡', title: 'Actionable Insights', text: 'Data-driven decisions' },
        { icon: '⚡', title: 'Real-time Updates', text: 'Live data refresh' },
        { icon: '📱', title: 'Mobile Ready', text: 'Access from smartphone' }
      ]
    }
  }
  
  // Default for other projects
  return getDefaultCta()
})

function getDefaultCta() {
  return {
    icon: '🚀',
    headline: 'Have a Similar Project?',
    subheadline: "Let's Bring Your Digital Solution to Life",
    description: 'From idea to implementation, we are ready to help realize the perfect digital solution for your business. FREE consultation with no commitment.',
    benefits: [
      { icon: '✨', title: 'Custom Solution', text: 'Tailored to your needs' },
      { icon: '⚙️', title: 'Full Support', text: 'Maintenance & training included' },
      { icon: '💼', title: '16+ Years Experience', text: 'Trusted by companies' }
    ]
  }
}

// WhatsApp message with project title
const whatsappLink = computed(() => {
  if (!project.value) return 'https://wa.me/6281380163758'
  
  const message = `Hi Ali Ma, I'm interested in your solution: ${project.value.title}`
  const encodedMessage = encodeURIComponent(message)
  return `https://wa.me/6281380163758?text=${encodedMessage}`
})

// Fetch project from API
const fetchProject = async (slug) => {
  if (!slug) {
    error.value = 'No project slug provided'
    loading.value = false
    return
  }

  try {
    loading.value = true
    error.value = null
    
    const response = await api.get(`/projects/${slug}`)
    
    if (response.data && response.data.data) {
      project.value = response.data.data
    } else if (response.data && !response.data.data) {
      project.value = response.data
    } else {
      error.value = 'Project not found'
    }
  } catch (err) {
    console.error('[ProjectDetail] Error:', err)
    error.value = err.response?.data?.message || 'Failed to load project'
  } finally {
    loading.value = false
  }
}

// Helper to construct backend image URL
const getImageUrl = (slug, size, format) => {
  const backendUrl = import.meta.env.VITE_API_BASE_URL.replace('/api', '')
  return `${backendUrl}/storage/projects/${slug}-${size}.${format}`
}

// Handle image loading errors
const handleImageError = (event) => {
  console.error('[ProjectDetail] Image failed to load:', event.target.src)
}

// Handle related project image errors
const handleRelatedImageError = (event) => {
  console.error('[ProjectDetail] Related image failed to load:', event.target.src)
  // Set placeholder or default image
  event.target.src = '/placeholder.png'
}

// Navigate to related project
const navigateToProject = (slug) => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
  router.push(`/projects/${slug}`)
}

// Update meta tags for social media sharing (from project meta fields)
const updateMetaTags = () => {
  if (!project.value) return

  // Use project-specific meta tags from database
  updatePageMeta({
    title: project.value.meta_title || `${project.value.title} | Portfolio`,
    description: project.value.meta_description || project.value.description,
    image: thumbnailUrl.value,
    url: currentUrl.value,
    type: 'article',
    keywords: project.value.focus_keyword || project.value.technologies?.join(', ')
  })

  // Additional OG tags from project
  if (project.value.og_title) {
    updateMetaTag('property', 'og:title', project.value.og_title)
  }
  if (project.value.og_description) {
    updateMetaTag('property', 'og:description', project.value.og_description)
  }
  
  console.log('✅ Meta tags updated from project:', {
    title: project.value.meta_title || project.value.title,
    og_image: thumbnailUrl.value
  })
}

// Watch route changes
watch(
  () => route.params.slug,
  (newSlug) => {
    if (newSlug) {
      fetchProject(newSlug)
    }
  },
  { immediate: true }
)

// Watch project changes to update meta tags
watch(
  () => project.value,
  (newProject) => {
    if (newProject) {
      updateMetaTags()
    }
  }
)

// Cleanup on unmount
onUnmounted(() => {
  // Meta tags will be reset by next page load
})
</script>

<style scoped>
/* Hide scrollbar for horizontal scroll */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Horizontal bounce animation */
@keyframes bounce-x {
  0%, 100% {
    transform: translateX(0);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: translateX(25%);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-x {
  animation: bounce-x 1s infinite;
}
</style>
