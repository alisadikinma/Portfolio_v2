<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Page Header -->
    <section class="relative pt-32 pb-16 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-5xl md:text-6xl font-display font-bold text-gray-900 dark:text-white mb-6">
            Awards & <span class="text-gradient">Recognition</span>
          </h1>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Innovative solutions that drive real business impact and transformation
          </p>
        </div>
      </div>
    </section>

    <!-- Awards Grid -->
    <section class="py-16 pb-32 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
          <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="awards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- DEBUG: Show raw data structure -->
          <div class="col-span-full bg-yellow-100 dark:bg-yellow-900/20 border border-yellow-400 rounded-lg p-4 mb-4">
            <h4 class="font-bold text-yellow-900 dark:text-yellow-200 mb-2">DEBUG: First Award Data</h4>
            <pre class="text-xs overflow-auto">{{ JSON.stringify(awards[0], null, 2) }}</pre>
          </div>

          <div
            v-for="award in awards"
            :key="award.id"
            class="award-card group relative bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-xl transition-all duration-300"
          >
            <!-- Award Icon/Image -->
            <div class="relative mb-6">
              <div v-if="award.image" class="w-24 h-24 rounded-xl overflow-hidden bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800 p-3">
                <img :src="award.image" :alt="award.award_title" class="w-full h-full object-contain" />
              </div>
              <div v-else class="w-24 h-24 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-xl flex items-center justify-center">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
              </div>
            </div>

            <!-- Award Info -->
            <div class="mb-6">
              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ award.award_title }}
              </h3>
              <p class="text-sm text-primary-600 dark:text-primary-400 font-semibold mb-3 uppercase tracking-wide">
                {{ award.issuing_organization }} • {{ formatYear(award.award_date) }}
              </p>
              <p v-if="award.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-3 mb-4">
                {{ stripHtml(award.description) }}
              </p>
              <p v-else class="text-sm text-gray-500 dark:text-gray-500 line-clamp-3 mb-4 italic">
                No description available
              </p>

              <!-- Credential Info -->
              <div class="flex items-center gap-4 text-xs text-gray-500 mb-4">
                <div v-if="award.credential_id" class="flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                  ID: {{ award.credential_id }}
                </div>
                <div v-if="award.total_photos > 0" class="flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                  {{ award.total_photos }} {{ award.total_photos === 1 ? 'Photo' : 'Photos' }}
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3">
              <button
                v-if="award.total_photos > 0"
                @click.stop.prevent="openGalleryModal(award)"
                class="w-full px-4 py-2.5 bg-gradient-to-r from-primary-600 to-secondary-600 text-white font-semibold rounded-lg hover:from-primary-700 hover:to-secondary-700 transition-all duration-300 flex items-center justify-center gap-2 group/btn"
              >
                <svg class="w-4 h-4 group-hover/btn:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                VIEW GALLERY
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20">
          <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">
            No Awards Yet
          </h3>
          <p class="text-gray-500 dark:text-gray-400">
            Check back later for updates on achievements and recognition.
          </p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="text-center py-20">
          <div class="w-24 h-24 mx-auto mb-6 bg-red-900/20 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-300 mb-2">
            Failed to Load Awards
          </h3>
          <p class="text-gray-500 mb-4">
            {{ error }}
          </p>
          <button
            @click="fetchAwards()"
            class="px-6 py-3 bg-gradient-to-r from-primary-600 to-secondary-600 text-white font-semibold rounded-xl hover:from-primary-700 hover:to-secondary-700 transition-all duration-300"
          >
            Try Again
          </button>
        </div>
      </div>
    </section>

    <!-- Gallery Modal - REDESIGNED -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryModal && selectedAward"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/95 backdrop-blur-md"
          @click.self="closeGalleryModal"
        >
          <div class="relative w-full max-w-5xl max-h-[90vh] bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Header - Fixed -->
            <div class="flex-shrink-0 px-6 py-5 border-b border-gray-200 dark:border-gray-800 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 line-clamp-1">
                    {{ selectedAward.award_title }}
                  </h3>
                  <p v-if="selectedAward.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                    {{ stripHtml(selectedAward.description) }}
                  </p>
                </div>
                
                <!-- Close Button - Prominent -->
                <button
                  @click="closeGalleryModal"
                  class="flex-shrink-0 p-2 bg-gray-100 dark:bg-gray-800 hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition-all duration-200 group"
                  title="Close (ESC)"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body - Scrollable -->
            <div class="flex-1 overflow-y-auto p-6">
              <!-- Loading State -->
              <div v-if="loadingGallery" class="flex items-center justify-center py-20">
                <div class="text-center">
                  <svg class="animate-spin h-12 w-12 text-primary-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <p class="text-gray-500 dark:text-gray-400">Loading gallery...</p>
                </div>
              </div>

              <!-- Gallery Grid - 3 Columns -->
              <div v-else-if="galleryPhotos.length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div
                  v-for="(photo, index) in galleryPhotos"
                  :key="photo.id"
                  class="relative group cursor-pointer aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 hover:ring-4 hover:ring-primary-500/50 transition-all duration-300"
                  @click="openLightbox(index)"
                >
                  <img
                    :src="photo.image"
                    :alt="photo.title || 'Gallery photo'"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                    @error="handleImageError"
                  />
                  
                  <!-- Overlay with title -->
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <div class="absolute bottom-0 left-0 right-0 p-3">
                      <p class="text-white text-xs font-medium line-clamp-2">
                        {{ photo.title || `Photo ${index + 1}` }}
                      </p>
                    </div>
                  </div>
                  
                  <!-- Zoom Icon -->
                  <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <div class="p-3 bg-white/90 dark:bg-gray-900/90 rounded-full shadow-lg">
                      <svg class="w-6 h-6 text-gray-900 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div v-else class="text-center py-20">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
                  <svg class="h-8 w-8 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No Photos Yet</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">This gallery doesn't have any photos.</p>
              </div>
            </div>

            <!-- Footer - Photo Count -->
            <div v-if="galleryPhotos.length > 0" class="flex-shrink-0 px-6 py-3 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
              <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                <span class="font-semibold text-gray-900 dark:text-white">{{ galleryPhotos.length }}</span> {{ galleryPhotos.length === 1 ? 'photo' : 'photos' }}
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Lightbox - REDESIGNED -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showLightbox"
          class="fixed inset-0 z-[60] flex flex-col bg-black"
        >
          <!-- Header Bar -->
          <div class="flex-shrink-0 flex items-center justify-between px-6 py-4 bg-black/50 backdrop-blur-sm">
            <div class="flex items-center gap-3">
              <p class="text-white font-semibold text-lg">
                {{ galleryPhotos[currentPhotoIndex]?.title || `Photo ${currentPhotoIndex + 1}` }}
              </p>
              <span class="px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-white text-sm">
                {{ currentPhotoIndex + 1 }} / {{ galleryPhotos.length }}
              </span>
            </div>
            
            <button
              @click="closeLightbox"
              class="p-2 bg-white/10 hover:bg-red-500/20 hover:text-red-400 rounded-lg transition-all duration-200"
              title="Close (ESC)"
            >
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Main Image Area -->
          <div class="flex-1 flex items-center justify-center p-8 relative" @click.self="closeLightbox">
            <!-- Previous Button -->
            <button
              v-if="currentPhotoIndex > 0"
              @click="previousPhoto"
              class="absolute left-6 p-4 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full transition-all duration-200 group"
              title="Previous (←)"
            >
              <svg class="w-8 h-8 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
              </svg>
            </button>

            <!-- Image -->
            <img
              :src="galleryPhotos[currentPhotoIndex]?.image"
              :alt="galleryPhotos[currentPhotoIndex]?.title"
              class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-2xl"
            />

            <!-- Next Button -->
            <button
              v-if="currentPhotoIndex < galleryPhotos.length - 1"
              @click="nextPhoto"
              class="absolute right-6 p-4 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-full transition-all duration-200 group"
              title="Next (→)"
            >
              <svg class="w-8 h-8 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>

          <!-- Thumbnail Strip -->
          <div class="flex-shrink-0 px-6 py-4 bg-black/50 backdrop-blur-sm overflow-x-auto">
            <div class="flex gap-3 justify-center">
              <button
                v-for="(photo, index) in galleryPhotos"
                :key="photo.id"
                @click="currentPhotoIndex = index"
                :class="[
                  'relative flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden transition-all duration-200',
                  currentPhotoIndex === index
                    ? 'ring-4 ring-primary-500 scale-110'
                    : 'opacity-60 hover:opacity-100 hover:scale-105'
                ]"
              >
                <img
                  :src="photo.image"
                  :alt="photo.title"
                  class="w-full h-full object-cover"
                />
                <div v-if="currentPhotoIndex === index" class="absolute inset-0 bg-primary-500/20"></div>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useAwards } from '@/composables/useAwards'
import api from '@/services/api'

const { awards, isLoading, error, fetchAwards } = useAwards()

// Gallery modal state
const showGalleryModal = ref(false)
const selectedAward = ref(null)
const galleryPhotos = ref([])
const loadingGallery = ref(false)

// Lightbox state
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

const formatYear = (date) => {
  if (!date) return ''
  return new Date(date).getFullYear()
}

const stripHtml = (html) => {
  if (!html) {
    return ''
  }
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  const result = tmp.textContent || tmp.innerText || ''
  return result
}

const openGalleryModal = async (award) => {
  console.log('[Awards] openGalleryModal called with award:', award)
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    console.log('[Awards] Fetching galleries for award ID:', award.id)
    const response = await api.get(`/awards/${award.id}/galleries`)
    console.log('[Awards] API response:', response.data)
    
    if (response.data.success && response.data.data.galleries) {
      // Extract all photos from all galleries
      const allPhotos = []
      response.data.data.galleries.forEach(gallery => {
        console.log('[Awards] Gallery:', gallery.title, '- Items:', gallery.items?.length)
        if (gallery.items && gallery.items.length > 0) {
          allPhotos.push(...gallery.items)
        }
      })
      console.log('[Awards] Total photos extracted:', allPhotos.length, allPhotos)
      galleryPhotos.value = allPhotos
    }
  } catch (err) {
    console.error('Failed to load gallery:', err)
  } finally {
    loadingGallery.value = false
    console.log('[Awards] Loading complete. galleryPhotos.value:', galleryPhotos.value)
  }
}

const closeGalleryModal = () => {
  showGalleryModal.value = false
  selectedAward.value = null
  galleryPhotos.value = []
}

const openLightbox = (index) => {
  currentPhotoIndex.value = index
  showLightbox.value = true
}

const closeLightbox = () => {
  showLightbox.value = false
}

const nextPhoto = () => {
  if (currentPhotoIndex.value < galleryPhotos.value.length - 1) {
    currentPhotoIndex.value++
  }
}

const previousPhoto = () => {
  if (currentPhotoIndex.value > 0) {
    currentPhotoIndex.value--
  }
}

const handleImageError = (event) => {
  console.error('[Awards] Image failed to load:', event.target.src)
  event.target.src = 'https://via.placeholder.com/400x300/e5e7eb/6b7280?text=Image+Not+Found'
}

// Keyboard navigation for lightbox
const handleKeydown = (e) => {
  if (showLightbox.value) {
    if (e.key === 'ArrowRight') nextPhoto()
    if (e.key === 'ArrowLeft') previousPhoto()
    if (e.key === 'Escape') closeLightbox()
  } else if (showGalleryModal.value && e.key === 'Escape') {
    closeGalleryModal()
  }
}

onMounted(() => {
  // TanStack Query auto-fetches on mount, no need to call fetchAwards()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.text-gradient {
  background: linear-gradient(to right, #fbbf24, #f97316);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.container-custom {
  max-width: 80rem;
  margin-left: auto;
  margin-right: auto;
  padding-left: 1rem;
  padding-right: 1rem;
}

@media (min-width: 640px) {
  .container-custom {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

@media (min-width: 1024px) {
  .container-custom {
    padding-left: 2rem;
    padding-right: 2rem;
  }
}

.award-card {
  transition: all 0.3s ease;
}

.award-card:hover {
  transform: translateY(-4px);
}

/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .modal-content,
.modal-leave-active .modal-content {
  transition: transform 0.3s ease;
}

.modal-enter-from .modal-content,
.modal-leave-to .modal-content {
  transform: scale(0.9);
}

/* Fade transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Line clamp utility */
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
