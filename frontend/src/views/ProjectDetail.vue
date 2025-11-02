<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
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
                :srcset="`/storage/projects/${project.slug}-600.webp`"
                media="(max-width: 767px)"
                type="image/webp">
              
              <!-- Tablet: 900px WebP -->
              <source 
                :srcset="`/storage/projects/${project.slug}-900.webp`"
                media="(max-width: 1023px)"
                type="image/webp">
              
              <!-- Desktop: 1200px WebP -->
              <source 
                :srcset="`/storage/projects/${project.slug}-1200.webp`"
                type="image/webp">
              
              <!-- Fallback: JPEG for old browsers -->
              <img 
                :src="`/storage/projects/${project.slug}-1200.jpg`"
                :alt="project.title"
                loading="lazy"
                class="w-full h-auto"
                @error="handleImageError">
            </picture>
          </div>

        </div>

        <!-- Dynamic CTA Section -->
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

        <!-- Related Projects Section -->
        <div 
          v-if="project.related_projects && project.related_projects.length > 0" 
          class="mt-16"
        >
          <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-8 text-center">
              🔗 Related Projects
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="relatedProject in project.related_projects"
                :key="relatedProject.id"
                class="group cursor-pointer bg-white dark:bg-neutral-800 rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700"
                @click="navigateToProject(relatedProject.slug)"
              >
                <!-- Image -->
                <div class="relative h-48 bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                  <img
                    v-if="relatedProject.featured_image"
                    :src="relatedProject.featured_image"
                    :alt="relatedProject.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div 
                    v-else 
                    class="w-full h-full flex items-center justify-center text-neutral-400 text-4xl"
                  >
                    📷
                  </div>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-2">
                  <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                    {{ relatedProject.title }}
                  </h3>
                  <p class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-2">
                    {{ relatedProject.description }}
                  </p>
                  <div class="pt-2">
                    <span class="text-sm font-medium text-primary-600 dark:text-primary-400 group-hover:underline">
                      View Project →
                    </span>
                  </div>
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
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

// State
const project = ref(null)
const loading = ref(true)
const error = ref(null)

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

// Handle image loading errors
const handleImageError = (event) => {
  console.error('[ProjectDetail] Image failed to load:', event.target.src)
}

// Navigate to related project
const navigateToProject = (slug) => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
  router.push(`/projects/${slug}`)
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
</script>
