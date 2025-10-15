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
        <BaseLoader v-if="isLoading" text="Loading awards..." />

        <div v-else-if="awards.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="award in awards"
            :key="award.id"
            class="award-card group relative bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:shadow-xl transition-all duration-300"
          >
            <!-- Award Icon/Image -->
            <div class="relative mb-6">
              <div v-if="award.image" class="w-20 h-20 rounded-xl overflow-hidden bg-gradient-to-br from-primary-400 to-secondary-400 p-1">
                <img :src="award.image" :alt="award.award_title" class="w-full h-full object-cover rounded-lg" />
              </div>
              <div v-else class="w-20 h-20 bg-gradient-to-br from-primary-400 to-secondary-400 rounded-xl flex items-center justify-center">
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
              <p class="text-sm text-gray-400 line-clamp-3 mb-4">
                {{ award.description || 'Recognized for outstanding achievement and excellence in the field.' }}
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
                @click="openGalleryModal(award)"
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

    <!-- Gallery Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryModal && selectedAward"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeGalleryModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
              <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ selectedAward.award_title }}
                </h3>
                <p class="text-sm text-primary-600 dark:text-primary-400 mt-1">
                  {{ selectedAward.issuing_organization }} • {{ formatYear(selectedAward.award_date) }}
                </p>
              </div>
              <button
                @click="closeGalleryModal"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
              >
                <svg class="w-6 h-6 text-gray-400 hover:text-gray-900 dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Modal Body - Gallery Grid -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
              <BaseLoader v-if="loadingGallery" text="Loading gallery..." class="py-20" />

              <div v-else-if="galleryPhotos.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="(photo, index) in galleryPhotos"
                  :key="photo.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800"
                  @click="openLightbox(index)"
                >
                  <img
                    :src="photo.image"
                    :alt="photo.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="absolute bottom-0 left-0 right-0 p-3">
                      <p class="text-white text-sm font-semibold truncate">
                        {{ photo.title }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-20">
                <p class="text-gray-400">No photos available in this gallery.</p>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Lightbox for full image view -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showLightbox"
          class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black"
          @click.self="closeLightbox"
        >
          <button
            @click="closeLightbox"
            class="absolute top-4 right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>

          <button
            v-if="currentPhotoIndex > 0"
            @click="previousPhoto"
            class="absolute left-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
          </button>

          <div class="max-w-6xl max-h-full">
            <img
              :src="galleryPhotos[currentPhotoIndex]?.image"
              :alt="galleryPhotos[currentPhotoIndex]?.title"
              class="max-w-full max-h-[90vh] object-contain"
            />
            <p v-if="galleryPhotos[currentPhotoIndex]?.title" class="text-center text-white mt-4 text-lg">
              {{ galleryPhotos[currentPhotoIndex].title }}
            </p>
          </div>

          <button
            v-if="currentPhotoIndex < galleryPhotos.length - 1"
            @click="nextPhoto"
            class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm">
            {{ currentPhotoIndex + 1 }} / {{ galleryPhotos.length }}
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
import { BaseLoader } from '@/components/base'

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

const openGalleryModal = async (award) => {
  selectedAward.value = award
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    const response = await api.get(`/awards/${award.id}/galleries`)
    if (response.data.success && response.data.data.galleries) {
      galleryPhotos.value = response.data.data.galleries
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

onMounted(async () => {
  await fetchAwards()
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
