<template>
  <div class="min-h-screen bg-gradient-to-br from-neutral-50 via-white to-primary-50/30 dark:from-neutral-950 dark:via-neutral-900 dark:to-primary-950/30">
    <!-- Hero Section - Minimalist -->
    <section class="relative overflow-hidden pt-32 pb-20">
      <!-- Animated Background Blobs -->
      <div class="absolute inset-0 opacity-30 dark:opacity-20">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary-500 rounded-full blur-[120px] animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-accent-500 rounded-full blur-[120px] animate-blob animation-delay-2000"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-secondary-500 rounded-full blur-[120px] animate-blob animation-delay-4000"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-3xl mx-auto text-center">
          <div class="inline-flex items-center gap-2 px-4 py-2 bg-primary-100 dark:bg-primary-900/30 rounded-full mb-6 animate-fade-in-up">
            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="currentColor" viewBox="0 0 20 20">
              <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
            </svg>
            <span class="text-sm font-semibold text-primary-700 dark:text-primary-300">Visual Showcase</span>
          </div>
          
          <h1 class="text-5xl md:text-7xl font-display font-bold mb-6 animate-fade-in-up animation-delay-100">
            <span class="bg-gradient-to-r from-primary-600 via-secondary-600 to-accent-600 bg-clip-text text-transparent">
              Gallery
            </span>
          </h1>
          
          <p class="text-xl text-neutral-600 dark:text-neutral-400 animate-fade-in-up animation-delay-200">
            A curated collection of achievements, moments, and creative excellence
          </p>
        </div>
      </div>
    </section>

    <!-- Gallery Bento Grid Section -->
    <section class="pb-20">
      <div class="container-custom">
        <BaseLoader v-if="loading" text="Loading gallery..." class="py-20" />

        <div v-else-if="error" class="text-center py-20">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full mb-4">
            <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-red-600 dark:text-red-400 mb-4">{{ error }}</p>
          <BaseButton variant="outline" @click="fetchGalleries({ is_active: 1, order_by: 'sort_order', order_dir: 'asc' })">
            Try Again
          </BaseButton>
        </div>

        <div v-else-if="galleries.length === 0" class="text-center py-20">
          <div class="inline-flex items-center justify-center w-20 h-20 bg-neutral-100 dark:bg-neutral-800 rounded-full mb-6">
            <svg class="w-10 h-10 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <p class="text-xl text-neutral-600 dark:text-neutral-400">No gallery items yet</p>
        </div>

        <!-- Bento Grid Layout -->
        <div v-else class="bento-grid">
          <div
            v-for="(item, index) in galleries"
            :key="item.id"
            :class="getBentoClass(index)"
            class="gallery-card group cursor-pointer"
            :style="{ animationDelay: `${index * 50}ms` }"
            @click="openGalleryModal(item)"
          >
            <!-- Glassmorphism Card -->
            <div class="relative h-full rounded-3xl overflow-hidden bg-white/70 dark:bg-neutral-900/70 backdrop-blur-xl border border-white/20 dark:border-neutral-700/30 shadow-xl hover:shadow-2xl transition-all duration-500 group-hover:scale-[1.02] group-hover:border-primary-500/50">
              
              <!-- Image Container with Gradient Overlay -->
              <div class="relative h-full overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500/20 via-secondary-500/20 to-accent-500/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10"></div>
                
                <img
                  v-if="item.thumbnail"
                  :src="item.thumbnail"
                  :alt="item.title"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                  loading="lazy"
                  @error="handleImageError"
                />
                
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-neutral-200 to-neutral-300 dark:from-neutral-800 dark:to-neutral-900">
                  <svg class="w-16 h-16 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>

                <!-- Content Overlay - MINIMIZED by default, FULL on hover -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-40 group-hover:opacity-90 transition-opacity duration-500"></div>
                
                <div class="absolute inset-0 p-6 flex flex-col justify-end z-20">
                  <!-- Badges - Always visible -->
                  <div class="flex items-center gap-2 mb-3 transform translate-y-0 transition-transform duration-500">
                    <div v-if="item.items_count" class="px-3 py-1.5 bg-white/20 backdrop-blur-md rounded-full text-white text-xs font-semibold border border-white/30">
                      <svg class="w-3 h-3 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
                      </svg>
                      {{ item.items_count }} items
                    </div>
                    <div v-if="item.company" :class="`px-3 py-1.5 bg-gradient-to-r ${getCompanyGradient(item.company)} rounded-full text-white text-xs font-semibold`">
                      {{ item.company }}
                    </div>
                  </div>

                  <!-- Title - Always visible but smaller by default -->
                  <h3 class="text-lg md:text-xl group-hover:text-2xl font-bold text-white mb-0 group-hover:mb-2 transform translate-y-0 transition-all duration-500">
                    {{ item.title }}
                  </h3>

                  <!-- Description - HIDDEN by default, VISIBLE on hover -->
                  <p v-if="item.description" class="text-white/80 text-sm line-clamp-2 opacity-0 group-hover:opacity-100 max-h-0 group-hover:max-h-20 overflow-hidden transform translate-y-2 group-hover:translate-y-0 transition-all duration-500 delay-75">
                    {{ item.description }}
                  </p>

                  <!-- Period - HIDDEN by default, VISIBLE on hover -->
                  <p v-if="item.period" class="text-white/60 text-xs mt-0 group-hover:mt-2 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-500 delay-100">
                    {{ item.period }}
                  </p>

                  <!-- View Arrow -->
                  <div class="absolute bottom-6 right-6 w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transform translate-x-4 group-hover:translate-x-0 transition-all duration-500 border border-white/30">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="h-10"></div>
    <!-- CTA Section (Component) -->
    <CTASection
      v-if="showCTASection"
      heading="Let's Work Together"
      description="Ready to transform your business with <strong>AI Automation & Custom Development</strong>? Let's discuss how I can help you achieve your goals."
      whatsapp-message="Hi! I saw your portfolio and I'd like to discuss a project with you."
      :social-links="about?.social_links"
      :show-social-links="true"
    />    

    <!-- Gallery Items Modal -->
    <BaseGalleryModal
      :show="showGalleryModal"
      :title="selectedGallery?.title || ''"
      :description="selectedGallery?.description || ''"
      :company="selectedGallery?.company || ''"
      :period="selectedGallery?.period || ''"
      :items="galleryItems"
      :loading="loadingItems"
      @close="closeGalleryModal"
      @open-lightbox="openLightbox"
    />

    <!-- Lightbox -->
    <BaseLightbox
      :show="showLightbox"
      :current-image="galleryItems[currentPhotoIndex]?.file_url || getImageUrl(galleryItems[currentPhotoIndex]?.file_path)"
      :current-title="galleryItems[currentPhotoIndex]?.title"
      :current-index="currentPhotoIndex"
      :total-items="galleryItems.length"
      @close="closeLightbox"
      @prev="previousPhoto"
      @next="nextPhoto"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { BaseButton, BaseLoader, BaseGalleryModal, BaseLightbox } from '@/components/base'
import CTASection from '@/components/CTASection.vue'
import { useGallery } from '@/composables/useGallery'
import { usePageSections } from '@/composables/usePageSections'
import api from '@/services/api'

const { galleries, loading, error, fetchGalleries, fetchGalleryItems } = useGallery({
  is_active: 1,
  order_by: 'sort_order', 
  order_dir: 'asc'
})
const { sections, fetchActiveSections } = usePageSections()

// About data for social links
const about = ref(null)

// CTA visibility
const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// Modal state
const showGalleryModal = ref(false)
const selectedGallery = ref(null)
const galleryItems = ref([])
const loadingItems = ref(false)

// Lightbox state
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

// Bento Grid Layout Logic
const getBentoClass = (index) => {
  const pattern = [
    'bento-large', // 0: Large featured
    'bento-medium', // 1: Medium
    'bento-small', // 2: Small
    'bento-small', // 3: Small
    'bento-medium', // 4: Medium
    'bento-large', // 5: Large featured
  ]
  
  const cycleIndex = index % pattern.length
  return pattern[cycleIndex]
}

// Dynamic gradient based on company name
const getCompanyGradient = (company) => {
  if (!company) return 'from-primary-500 to-primary-600'
  
  const gradients = [
    'from-blue-500 to-indigo-600',
    'from-purple-500 to-pink-600',
    'from-green-500 to-emerald-600',
    'from-orange-500 to-red-600',
    'from-cyan-500 to-blue-600',
    'from-violet-500 to-purple-600',
  ]
  
  const hash = company.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return gradients[hash % gradients.length]
}

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http')) return path
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/storage/' + path
}

const openGalleryModal = async (gallery) => {
  selectedGallery.value = gallery
  showGalleryModal.value = true
  loadingItems.value = true
  galleryItems.value = []

  try {
    const items = await fetchGalleryItems(gallery.id)
    if (items && items.length > 0) {
      galleryItems.value = items
    }
  } catch (err) {
    console.error('Failed to load gallery items:', err)
  } finally {
    loadingItems.value = false
  }
}

const closeGalleryModal = () => {
  showGalleryModal.value = false
  selectedGallery.value = null
  galleryItems.value = []
}

const openLightbox = (index) => {
  currentPhotoIndex.value = index
  showLightbox.value = true
}

const closeLightbox = () => {
  showLightbox.value = false
}

const nextPhoto = () => {
  if (currentPhotoIndex.value < galleryItems.value.length - 1) {
    currentPhotoIndex.value++
  }
}

const previousPhoto = () => {
  if (currentPhotoIndex.value > 0) {
    currentPhotoIndex.value--
  }
}

const handleImageError = (event) => {
  console.error('Image failed to load:', event.target.src)
  event.target.style.display = 'none'
}

// Keyboard navigation
const handleKeydown = (e) => {
  if (showLightbox.value) {
    if (e.key === 'ArrowRight') nextPhoto()
    if (e.key === 'ArrowLeft') previousPhoto()
    if (e.key === 'Escape') closeLightbox()
  } else if (showGalleryModal.value && e.key === 'Escape') {
    closeGalleryModal()
  }
}

onMounted(async () => {
  await fetchActiveSections('gallery')
  await fetchAboutData()
  
  // ✅ Direct call fetchGalleries untuk leverage cache
  await fetchGalleries({
    is_active: 1,
    order_by: 'sort_order',
    order_dir: 'asc'
  })
  
  window.addEventListener('keydown', handleKeydown)
})

async function fetchAboutData() {
  try {
    const response = await api.get('/settings/about', {
      params: { _t: Date.now() }
    })

    if (response.data.success && response.data.data) {
      about.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to fetch about data:', err)
  }
}

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
/* Bento Grid Layout with Fixed Heights */
.bento-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  grid-auto-flow: dense;
}

@media (min-width: 768px) {
  .bento-grid {
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: 250px; /* ✅ Fixed base height */
  }
  
  .bento-large {
    grid-column: span 2;
    grid-row: span 2;
    height: 100%; /* ✅ Force height */
  }
  
  .bento-medium {
    grid-column: span 2;
    grid-row: span 1;
    height: 100%; /* ✅ Force height */
  }
  
  .bento-small {
    grid-column: span 1;
    grid-row: span 1;
    height: 100%; /* ✅ Force height */
  }
}

/* ✅ Force consistent image sizing */
.gallery-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

/* Animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes blob {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  25% {
    transform: translate(20px, -50px) scale(1.1);
  }
  50% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  75% {
    transform: translate(50px, 50px) scale(1.05);
  }
}

.animate-fade-in-up {
  animation: fadeInUp 0.6s ease-out forwards;
}

.animation-delay-100 {
  animation-delay: 100ms;
}

.animation-delay-200 {
  animation-delay: 200ms;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.animate-blob {
  animation: blob 7s infinite;
}

.gallery-card {
  animation: fadeInUp 0.6s ease-out forwards;
  opacity: 0;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Line Clamp */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
