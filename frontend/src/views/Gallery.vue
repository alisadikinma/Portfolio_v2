<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            <span class="text-gradient">Gallery</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            A visual showcase of creative work and inspiration
          </p>
        </div>
      </div>
    </section>

    <!-- Gallery Grid Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <BaseLoader v-if="loading" text="Loading gallery..." />

        <div v-else-if="error" class="text-center py-12">
          <p class="text-red-600 dark:text-red-400">{{ error }}</p>
          <BaseButton variant="outline" @click="loadGalleries" class="mt-4">
            Retry
          </BaseButton>
        </div>

        <div v-else-if="galleries.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">No gallery items found.</p>
        </div>

        <div v-else>
          <!-- Masonry Grid -->
          <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
            <div
              v-for="item in galleries"
              :key="item.id"
              class="break-inside-avoid"
            >
              <BaseCard
                hover
                class="cursor-pointer overflow-hidden"
                @click="openGalleryModal(item)"
              >
                <div class="relative bg-neutral-200 dark:bg-neutral-700 rounded-lg overflow-hidden" style="aspect-ratio: 16/9;">
                  <img
                    v-if="item.thumbnail"
                    :src="item.thumbnail"
                    :alt="item.title"
                    class="w-full h-full object-contain"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center text-neutral-400">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div v-if="item.items_count" class="absolute top-2 right-2 bg-black/70 text-white px-2 py-1 rounded-lg text-sm">
                    {{ item.items_count }} items
                  </div>
                </div>
                <div class="p-4">
                  <div class="flex items-center gap-2 mb-2">
                    <BaseBadge v-if="item.company" :variant="getCategoryVariant(item.company)" size="sm">
                      {{ item.company }}
                    </BaseBadge>
                    <span v-if="item.period" class="text-sm text-neutral-500">{{ item.period }}</span>
                  </div>
                  <h3 class="font-semibold mb-1">{{ item.title }}</h3>
                  <p class="text-neutral-600 dark:text-neutral-400 text-sm line-clamp-2">
                    {{ item.description }}
                  </p>
                </div>
              </BaseCard>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Gallery Items Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showGalleryModal && selectedGallery"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
          @click.self="closeGalleryModal"
        >
          <div class="relative w-full max-w-6xl bg-white dark:bg-neutral-900 rounded-2xl shadow-2xl overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-neutral-200 dark:border-neutral-800">
              <div>
                <h3 class="text-2xl font-bold text-neutral-900 dark:text-white">
                  {{ selectedGallery.title }}
                </h3>
                <p class="text-sm text-primary-600 dark:text-primary-400 mt-1">
                  <span v-if="selectedGallery.company">{{ selectedGallery.company }}</span>
                  <span v-if="selectedGallery.company && selectedGallery.period"> • </span>
                  <span v-if="selectedGallery.period">{{ selectedGallery.period }}</span>
                </p>
              </div>
              <button
                @click="closeGalleryModal"
                class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
              >
                <svg class="w-6 h-6 text-neutral-400 hover:text-neutral-900 dark:hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>

            <!-- Modal Body - Gallery Grid -->
            <div class="p-6 max-h-[70vh] overflow-y-auto">
              <!-- Loading State -->
              <div v-if="loadingItems" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

              <!-- Gallery Grid -->
              <div v-else-if="galleryItems.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div
                  v-for="(item, index) in galleryItems"
                  :key="item.id"
                  class="relative group cursor-pointer aspect-square rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800"
                  @click="openLightbox(index)"
                >
                  <img
                    :src="item.file_url || getImageUrl(item.file_path)"
                    :alt="item.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                    @error="handleImageError"
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
                <svg class="mx-auto h-16 w-16 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <p class="mt-4 text-neutral-500 dark:text-neutral-400">No photos available in this gallery.</p>
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
              :src="galleryItems[currentPhotoIndex]?.file_url || getImageUrl(galleryItems[currentPhotoIndex]?.file_path)"
              :alt="galleryItems[currentPhotoIndex]?.title"
              class="max-w-full max-h-[90vh] object-contain"
            />
            <p v-if="galleryItems[currentPhotoIndex]?.title" class="text-center text-white mt-4 text-lg">
              {{ galleryItems[currentPhotoIndex].title }}
            </p>
          </div>

          <button
            v-if="currentPhotoIndex < galleryItems.length - 1"
            @click="nextPhoto"
            class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors"
          >
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>

          <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-sm">
            {{ currentPhotoIndex + 1 }} / {{ galleryItems.length }}
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'
import { useGallery } from '@/composables/useGallery'

const { galleries, loading, error, fetchGalleries, fetchGalleryItems } = useGallery()

// Modal state
const showGalleryModal = ref(false)
const selectedGallery = ref(null)
const galleryItems = ref([])
const loadingItems = ref(false)

// Lightbox state
const showLightbox = ref(false)
const currentPhotoIndex = ref(0)

const getCategoryVariant = (company) => {
  if (!company) return 'outline'
  // Simple hash-based color assignment
  const hash = company.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  const variants = ['primary', 'success', 'warning', 'info']
  return variants[hash % variants.length]
}

const getImageUrl = (path) => {
  if (!path) return ''
  // If path already starts with http/https, return as is
  if (path.startsWith('http')) return path
  // If path starts with /storage/, return as is (already public URL)
  if (path.startsWith('/storage/')) return import.meta.env.VITE_API_URL.replace('/api', '') + path
  // Otherwise, prepend storage path
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
  event.target.src = 'https://via.placeholder.com/400x300/e5e7eb/6b7280?text=Image+Not+Found'
}

const loadGalleries = async () => {
  await fetchGalleries({
    is_active: 1,
    order_by: 'sort_order',
    order_dir: 'asc'
  })
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
  loadGalleries()
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
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

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
