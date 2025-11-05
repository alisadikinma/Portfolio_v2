<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Page Header -->
    <section class="relative pt-32 pb-16 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-5xl md:text-6xl font-display font-bold text-gray-900 dark:text-white mb-6">
            Awards & <span class="text-gradient">Honors</span>
          </h1>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Celebrating milestones and industry recognition
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

        <!-- Awards Grid - NEW SQUARE DESIGN -->
        <div v-else-if="awards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="award in awards"
            :key="award.id"
            class="award-card-square group relative bg-white dark:bg-gray-900 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-2xl transition-all duration-300"
          >
            <!-- Square Hero Image Section -->
            <div class="relative aspect-square overflow-hidden bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-800 dark:to-gray-900">
              <!-- Achievement Photo (1024x1024 square) -->
              <img
                v-if="award.thumbnail"
                :src="award.thumbnail"
                :alt="award.award_title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                @error="handleThumbnailError"
              />
              
              <!-- Fallback Icon if no thumbnail -->
              <div
                v-else
                class="w-full h-full bg-gradient-to-br from-primary-500 via-secondary-500 to-primary-700 flex items-center justify-center"
              >
                <svg class="w-32 h-32 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
              </div>
              
              <!-- Year Badge Overlay (Top-Right) -->
              <div class="absolute top-4 right-4 px-4 py-2 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm rounded-full shadow-lg">
                <span class="text-lg font-bold text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-secondary-600">
                  {{ formatYear(award.award_date) }}
                </span>
              </div>
              
              <!-- Photo Count Badge (Bottom-Left) -->
              <div
                v-if="award.total_photos > 0"
                class="absolute bottom-4 left-4 px-3 py-1.5 bg-black/80 backdrop-blur-sm rounded-lg flex items-center gap-2"
              >
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="text-sm font-semibold text-white">{{ award.total_photos }}</span>
              </div>
            </div>

            <!-- Content Section -->
            <div class="p-6">
              <!-- Award Title -->
              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2 min-h-[3.5rem]">
                {{ award.award_title }}
              </h3>
              
              <!-- Organization -->
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-primary-600 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 14l9-5-9-5-9 5 9 5z"></path>
                  <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path>
                </svg>
                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400 truncate">
                  {{ award.issuing_organization }}
                </span>
              </div>
              
              <!-- Credential ID (if exists) -->
              <div v-if="award.credential_id" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                  </svg>
                  <span class="font-mono truncate">{{ award.credential_id }}</span>
                </div>
              </div>
              
              <!-- CTA Button -->
              <button
                v-if="award.total_photos > 0"
                @click.stop.prevent="openGalleryModal(award)"
                class="w-full px-6 py-3 bg-gradient-to-r from-primary-600 to-secondary-600 text-white font-bold rounded-xl hover:from-primary-700 hover:to-secondary-700 hover:shadow-lg hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2 group/btn"
              >
                <svg class="w-5 h-5 group-hover/btn:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                VIEW GALLERY
              </button>
              
              <!-- No Gallery State -->
              <div v-else class="w-full px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 font-semibold rounded-xl text-center">
                No Gallery
              </div>
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
      </div>
    </section>

    <!-- Gallery Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryModal && selectedAward"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/95 backdrop-blur-md"
          @click.self="closeGalleryModal"
        >
          <div class="relative w-full max-w-5xl max-h-[90vh] bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Header -->
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
                <button
                  @click="closeGalleryModal"
                  class="flex-shrink-0 p-2 bg-gray-100 dark:bg-gray-800 hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition-all duration-200"
                >
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6">
              <div v-if="loadingGallery" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

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
                  />
                </div>
              </div>

              <div v-else class="text-center py-20">
                <p class="text-gray-500">No photos available</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="showLightbox" class="fixed inset-0 z-[60] flex flex-col bg-black">
          <div class="flex-shrink-0 flex items-center justify-between px-6 py-4 bg-black/50">
            <span class="text-white">{{ currentPhotoIndex + 1 }} / {{ galleryPhotos.length }}</span>
            <button @click="closeLightbox" class="text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="flex-1 flex items-center justify-center p-8">
            <button v-if="currentPhotoIndex > 0" @click="previousPhoto" class="absolute left-6 p-4 bg-white/10 rounded-full">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
              </svg>
            </button>
            <img :src="galleryPhotos[currentPhotoIndex]?.image" class="max-w-full max-h-[70vh] object-contain" />
            <button v-if="currentPhotoIndex < galleryPhotos.length - 1" @click="nextPhoto" class="absolute right-6 p-4 bg-white/10 rounded-full">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
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

const { awards, isLoading, error } = useAwards()

const showGalleryModal = ref(false)
const selectedAward = ref(null)
const galleryPhotos = ref([])
const loadingGallery = ref(false)
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

const formatYear = (date) => {
  if (!date) return ''
  return new Date(date).getFullYear()
}

const stripHtml = (html) => {
  if (!html) return ''
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

const openGalleryModal = async (award) => {
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    const response = await api.get(`/awards/${award.id}/galleries`)
    if (response.data.success && response.data.data.galleries) {
      const allPhotos = []
      response.data.data.galleries.forEach(gallery => {
        if (gallery.items && gallery.items.length > 0) {
          allPhotos.push(...gallery.items)
        }
      })
      galleryPhotos.value = allPhotos
    }
  } catch (err) {
    console.error('Failed to load gallery:', err)
  } finally {
    loadingGallery.value = false
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

const handleThumbnailError = (event) => {
  event.target.style.display = 'none'
  const parent = event.target.parentElement
  if (parent) {
    parent.innerHTML = `
      <div class="w-full h-full bg-gradient-to-br from-primary-500 via-secondary-500 to-primary-700 flex items-center justify-center">
        <svg class="w-32 h-32 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
        </svg>
      </div>
    `
  }
}

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
  margin: 0 auto;
  padding: 0 1rem;
}

@media (min-width: 640px) {
  .container-custom {
    padding: 0 1.5rem;
  }
}

@media (min-width: 1024px) {
  .container-custom {
    padding: 0 2rem;
  }
}

.award-card-square {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.award-card-square:hover {
  transform: translateY(-8px);
}

.aspect-square {
  aspect-ratio: 1 / 1;
}

.award-card-square:hover img {
  filter: brightness(1.05) contrast(1.05);
}

.min-h-\[3\.5rem\] {
  min-height: 3.5rem;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.modal-enter-active,
.modal-leave-active,
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to,
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
