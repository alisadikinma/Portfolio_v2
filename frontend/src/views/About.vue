<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-accent-500 rounded-full blur-3xl"></div>
      </div>

      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            About <span class="text-gradient">{{ about?.name || 'Me' }}</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            {{ about?.title || 'Passionate developer crafting digital experiences' }}
          </p>
        </div>
      </div>
    </section>

    <!-- Introduction Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto">
          <div class="h-10"></div>
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="aspect-square bg-neutral-200 dark:bg-neutral-700 rounded-2xl overflow-hidden">
              <img
                v-if="about?.profile_photo"
                :src="getProfilePhotoUrl(about.profile_photo)"
                :alt="about.name || 'Profile Image'"
                class="w-full h-full object-cover"
                @error="handleImageError"
              />
              <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-32 h-32 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
            </div>
            <div>
              <h2 class="text-3xl md:text-4xl font-display font-bold mb-6">
                Hi, I'm {{ about?.name || '[Your Name]' }}
              </h2>
              <div v-if="about?.bio" v-html="about.bio" class="prose dark:prose-invert text-neutral-600 dark:text-neutral-400 mb-6 max-w-none"></div>
              <div v-else>
                <p class="text-neutral-600 dark:text-neutral-400 mb-4">
                  I'm a full-stack developer with over 5 years of experience building web applications and digital solutions.
                  I specialize in modern JavaScript frameworks, cloud architecture, and creating seamless user experiences.
                </p>
                <p class="text-neutral-600 dark:text-neutral-400 mb-6">
                  My passion lies in solving complex problems with elegant solutions and continuously learning new technologies
                  to stay at the forefront of web development.
                </p>
              </div>
              <BaseButton variant="primary" @click="$router.push('/contact')">
                Get in Touch
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
      <div class="h-10"></div>
    </section>

    <!-- Skills Section - COMPACT -->
    <section class="py-16 bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-8">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-3">Skills & Expertise</h2>
          <p class="text-neutral-600 dark:text-neutral-400 text-sm">Technologies and tools I work with</p>
        </div>

        <div v-if="about?.skills && Array.isArray(about.skills) && about.skills.length > 0" class="max-w-6xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in about.skills"
              :key="skill"
              class="px-3 py-1.5 bg-white dark:bg-neutral-800 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shadow-sm"
            >
              {{ skill }}
            </span>
          </div>
        </div>

        <div v-else class="max-w-6xl mx-auto">
          <div class="flex flex-wrap justify-center gap-2">
            <span
              v-for="skill in defaultSkills"
              :key="skill"
              class="px-3 py-1.5 bg-white dark:bg-neutral-800 rounded-lg text-xs font-medium text-neutral-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shadow-sm"
            >
              {{ skill }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- Experience Section - IMPROVED UI -->
    <section v-if="displayExperiences && displayExperiences.length > 0" class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Experience</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            My professional journey
          </p>
        </div>

        <div class="max-w-4xl mx-auto space-y-6">
          <div 
            v-for="(exp, index) in displayExperiences" 
            :key="index" 
            class="bg-neutral-50 dark:bg-neutral-900 rounded-xl p-6 hover:shadow-lg transition-shadow"
          >
            <div class="flex items-start gap-6">
              <!-- Company Logo -->
              <div v-if="exp.company_logo" class="flex-shrink-0">
                <img 
                  :src="exp.company_logo" 
                  :alt="exp.company"
                  class="w-16 h-16 object-contain rounded-lg bg-white dark:bg-neutral-800 p-2"
                  @error="handleLogoError"
                />
              </div>
              <div v-else class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-primary-500 to-secondary-500 rounded-lg flex items-center justify-center">
                <span class="text-2xl font-bold text-white">
                  {{ exp.company.charAt(0) }}
                </span>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                  <div>
                    <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                      {{ exp.title }}
                    </h3>
                    <a 
                      v-if="exp.company_url" 
                      :href="exp.company_url" 
                      target="_blank" 
                      rel="noopener noreferrer"
                      class="text-neutral-600 dark:text-neutral-400 font-medium hover:text-primary-600 dark:hover:text-primary-400 transition-colors inline-flex items-center gap-1"
                    >
                      {{ exp.company }}
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                      </svg>
                    </a>
                    <p v-else class="text-neutral-600 dark:text-neutral-400 font-medium">
                      {{ exp.company }}
                    </p>
                  </div>
                  <BaseBadge variant="info" size="sm">
                    {{ formatPeriod(exp.start_date, exp.end_date, exp.current) }}
                  </BaseBadge>
                </div>

                <!-- Location -->
                <p v-if="exp.location" class="text-sm text-neutral-500 dark:text-neutral-400 mb-3 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  </svg>
                  {{ exp.location }}
                </p>

                <!-- Description -->
                <div v-if="exp.description">
                  <p 
                    v-if="!expandedExperience[index]" 
                    class="text-neutral-600 dark:text-neutral-400 text-sm"
                  >
                    {{ truncateText(exp.description, 200) }}
                    <button 
                      v-if="exp.description.length > 200"
                      @click="toggleExperience(index)"
                      class="text-primary-600 dark:text-primary-400 hover:underline ml-1 font-medium"
                    >
                      See more
                    </button>
                  </p>
                  <div v-else>
                    <p class="text-neutral-600 dark:text-neutral-400 text-sm whitespace-pre-line">
                      {{ exp.description }}
                    </p>
                    <button 
                      @click="toggleExperience(index)"
                      class="text-primary-600 dark:text-primary-400 hover:underline mt-2 font-medium text-sm"
                    >
                      See less
                    </button>
                  </div>
                </div>

                <!-- Gallery Button -->
                <div v-if="exp.gallery_ids && exp.gallery_ids.length > 0" class="mt-4">
                  <button
                    @click="openWorkGallery(exp.gallery_ids)"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors text-sm font-medium"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    View Gallery ({{ exp.gallery_ids.length }})
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Education Section -->
    <section v-if="displayEducation && displayEducation.length > 0" class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Education</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Academic background
          </p>
        </div>

        <div class="max-w-4xl mx-auto grid md:grid-cols-2 gap-6">
          <BaseCard v-for="(edu, index) in displayEducation" :key="index">
            <div class="flex items-start justify-between mb-3">
              <h3 class="text-xl font-semibold">{{ edu.degree }}</h3>
              <BaseBadge variant="primary" size="sm">{{ edu.period }}</BaseBadge>
            </div>
            <p class="text-neutral-600 dark:text-neutral-400 font-medium mb-2">{{ edu.institution }}</p>
            <p v-if="edu.description" class="text-neutral-600 dark:text-neutral-400 text-sm">{{ edu.description }}</p>
          </BaseCard>
        </div>
      </div>
    </section>
    <div class="h-10"></div>

    <!-- Social Links Section -->
    <section v-if="displaySocialLinks && displaySocialLinks.length > 0" class="section bg-white dark:bg-neutral-800 pb-20">
      <div class="container-custom">
        <div class="text-center mb-6">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-2">Connect With Me</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Find me on social media
          </p>
        </div>

        <div class="flex flex-wrap justify-center gap-6 max-w-3xl mx-auto">
          <a
            v-for="(link, index) in displaySocialLinks"
            :key="index"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-3 px-6 py-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors group"
          >
            <!-- Use icon class from DB if available -->
            <i v-if="link.icon" :class="link.icon + ' text-2xl text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600'"></i>
            <!-- Fallback SVG icons -->
            <svg v-else-if="link.platform.toLowerCase() === 'github'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
            </svg>
            <svg v-else-if="link.platform.toLowerCase() === 'linkedin'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
            </svg>
            <svg v-else-if="link.platform.toLowerCase() === 'twitter' || link.platform.toLowerCase() === 'x'" class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
            </svg>
            <svg v-else class="w-6 h-6 text-neutral-600 dark:text-neutral-400 group-hover:text-primary-600" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm0 22c-5.514 0-10-4.486-10-10s4.486-10 10-10 10 4.486 10 10-4.486 10-10 10zm1-16h-2v7h7v-2h-5z"/>
            </svg>
            <span class="font-medium text-neutral-700 dark:text-neutral-300 group-hover:text-primary-600">
              {{ link.platform.charAt(0).toUpperCase() + link.platform.slice(1) }}
            </span>
          </a>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="section bg-gradient-to-r from-primary-600 to-accent-600 text-white">
      <div class="h-10"></div>
      <div class="container-custom text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Let's Work Together</h2>
        <p class="text-lg md:text-xl mb-8 opacity-90 max-w-2xl mx-auto">
          Interested in collaborating? I'd love to hear about your project.
        </p>
        <BaseButton variant="secondary" size="lg" @click="$router.push('/contact')">
          Contact Me
        </BaseButton>
      </div>
      <div class="h-10"></div>
    </section>

    <!-- Work Gallery Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showWorkGalleryModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeWorkGalleryModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                Work Gallery
              </h3>
              <button
                @click="closeWorkGalleryModal"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
              >
                <svg class="w-6 h-6 text-gray-400 hover:text-gray-900 dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Modal Body - Gallery Grid -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
              <!-- Loading State -->
              <div v-if="loadingWorkGallery" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

              <!-- Gallery Grid -->
              <div v-else-if="workGalleryItems.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="item in workGalleryItems"
                  :key="item.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                >
                  <img
                    :src="item.file_url || getImageUrl(item.file_path)"
                    :alt="item.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="absolute bottom-0 left-0 right-0 p-3">
                      <p class="text-white text-sm font-semibold truncate">
                        {{ item.title }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="text-center py-20">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="mt-4 text-gray-500 dark:text-gray-400">No photos available.</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { BaseButton, BaseCard, BaseBadge } from '@/components/base'
import api from '@/services/api'

const about = ref(null)
const loading = ref(true)
const error = ref(null)
const expandedExperience = ref({})

// Gallery modal state
const showWorkGalleryModal = ref(false)
const workGalleryItems = ref([])
const loadingWorkGallery = ref(false)

// Default skills as fallback (flat array)
const defaultSkills = [
  'Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3',
  'Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB',
  'Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx'
]

// Computed properties for conditional rendering
const displayExperiences = computed(() => {
  if (about.value?.experience && Array.isArray(about.value.experience) && about.value.experience.length > 0) {
    // Sort by start_date descending (most recent first)
    return [...about.value.experience].sort((a, b) => {
      // Parse date strings like "Oct 2011" or "Jan 2020"
      const parseDate = (dateStr) => {
        if (!dateStr) return new Date(0) // Epoch for missing dates
        const date = new Date(dateStr)
        return isNaN(date.getTime()) ? new Date(0) : date
      }
      
      const dateA = parseDate(a.start_date)
      const dateB = parseDate(b.start_date)
      
      return dateB - dateA // Descending (newest first)
    })
  }
  return []
})

const displayEducation = computed(() => {
  if (about.value?.education && Array.isArray(about.value.education) && about.value.education.length > 0) {
    return about.value.education
  }
  return []
})

const displaySocialLinks = computed(() => {
  if (about.value?.social_links && Array.isArray(about.value.social_links) && about.value.social_links.length > 0) {
    return about.value.social_links
  }
  return []
})

const formatPeriod = (startDate, endDate, isCurrent) => {
  if (!startDate) return ''
  const start = startDate
  const end = isCurrent ? 'Present' : (endDate || 'Present')
  return `${start} - ${end}`
}

const truncateText = (text, maxLength) => {
  if (!text || text.length <= maxLength) return text
  return text.substring(0, maxLength).trim() + '...'
}

const toggleExperience = (index) => {
  expandedExperience.value[index] = !expandedExperience.value[index]
}

const handleLogoError = (event) => {
  event.target.style.display = 'none'
}

const openWorkGallery = async (galleryIds) => {
  if (!galleryIds || galleryIds.length === 0) return
  
  showWorkGalleryModal.value = true
  loadingWorkGallery.value = true
  workGalleryItems.value = []

  try {
    // Fetch items from multiple galleries
    const promises = galleryIds.map(id => api.get(`/galleries/${id}/items`))
    const responses = await Promise.all(promises)
    
    // Combine all items
    const allItems = []
    responses.forEach(response => {
      if (response.data.success && response.data.data) {
        allItems.push(...response.data.data)
      }
    })
    
    workGalleryItems.value = allItems
  } catch (err) {
    console.error('Failed to load gallery items:', err)
  } finally {
    loadingWorkGallery.value = false
  }
}

const closeWorkGalleryModal = () => {
  showWorkGalleryModal.value = false
  workGalleryItems.value = []
}

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/storage/' + path
}

const getProfilePhotoUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/uploads/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/uploads/' + path
}

const handleImageError = (event) => {
  console.warn('Failed to load profile photo:', event.target.src)
  event.target.style.display = 'none'
}

onMounted(async () => {
  await fetchAboutData()
})

async function fetchAboutData() {
  loading.value = true
  error.value = null

  try {
    const response = await api.get('/settings/about')

    if (response.data.success && response.data.data) {
      about.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to fetch about data:', err)
    error.value = err.response?.data?.message || 'Failed to load about information'
    // Continue with default data on error
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
