<template>
  <div>
    <!-- Trigger Button -->
    <div 
      @click="isOpen = !isOpen"
      class="relative cursor-pointer p-4 border-2 rounded-lg transition-all"
      :class="[
        isOpen || selectedGallery
          ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' 
          : 'border-neutral-200 dark:border-neutral-700 hover:border-primary-300 dark:hover:border-primary-700'
      ]"
    >
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="font-medium text-neutral-900 dark:text-neutral-100">
              Link Gallery
            </span>
            <span v-if="selectedGallery" class="px-2 py-0.5 text-xs font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 rounded-full">
              1 selected
            </span>
          </div>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            {{ isOpen ? 'Select one gallery to link with this award' : 'Click to select a gallery or create new' }}
          </p>
        </div>
        <svg 
          class="w-5 h-5 text-neutral-400 transition-transform"
          :class="{ 'rotate-180': isOpen }"
          fill="none" 
          stroke="currentColor" 
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>

    <!-- Gallery Selection Panel -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
    >
      <div v-if="isOpen" class="mt-3 p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg bg-neutral-50 dark:bg-neutral-800/50">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-8">
          <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <!-- Gallery List -->
        <div v-else-if="galleries.length > 0" class="space-y-2 max-h-80 overflow-y-auto">
          <label
            v-for="gallery in galleries"
            :key="gallery.id"
            class="flex items-start gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-white dark:hover:bg-neutral-800 cursor-pointer transition-colors"
            :class="{
              'bg-white dark:bg-neutral-800 ring-2 ring-primary-500': isGallerySelected(gallery.id)
            }"
          >
            <input
              type="radio"
              name="gallery-select"
              :value="gallery.id"
              v-model="selectedGallery"
              class="mt-1 w-4 h-4 text-primary-600 border-neutral-300 focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-700"
            />
            <div class="flex-1 min-w-0">
              <div class="flex items-start gap-2">
                <img
                  v-if="gallery.thumbnail"
                  :src="getImageUrl(gallery.thumbnail)"
                  :alt="gallery.title"
                  class="w-12 h-12 object-cover rounded flex-shrink-0"
                />
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-neutral-900 dark:text-neutral-100 truncate">
                    {{ gallery.title }}
                  </p>
                  <p v-if="gallery.description" class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-1 mt-0.5">
                    {{ gallery.description }}
                  </p>
                  <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    <span v-if="gallery.company">{{ gallery.company }}</span>
                    <span v-if="gallery.company && gallery.period">•</span>
                    <span v-if="gallery.period">{{ gallery.period }}</span>
                    <span v-if="gallery.items_count > 0">• {{ gallery.items_count }} photos</span>
                  </div>
                </div>
              </div>
            </div>
          </label>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8">
          <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
            No galleries available yet
          </p>
          <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
            Total in DB: {{ galleriesStore.galleries?.length || 0 }} | 
            Filtered (unlinked): {{ galleries.length }}
          </p>
          <p class="text-xs text-red-500 dark:text-red-400 mt-1" v-if="galleriesStore.galleries?.length > 0">
            All galleries are already linked to awards
          </p>
        </div>

        <!-- Create New Gallery Option -->
        <div class="mt-3 pt-3 border-t border-neutral-200 dark:border-neutral-700">
          <label class="flex items-center gap-3 p-3 rounded-lg border-2 border-dashed border-neutral-300 dark:border-neutral-600 hover:border-primary-400 dark:hover:border-primary-600 hover:bg-primary-50/50 dark:hover:bg-primary-900/10 cursor-pointer transition-colors">
            <input
              type="checkbox"
              v-model="createNewGallery"
              class="w-4 h-4 text-primary-600 border-neutral-300 rounded focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-700"
            />
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span class="font-medium text-neutral-900 dark:text-neutral-100">
                Create New Gallery After Award
              </span>
            </div>
          </label>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2 ml-7">
            Redirect to gallery creation page after award is saved
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2 mt-4">
          <button
            type="button"
            @click="clearSelection"
            class="px-3 py-1.5 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors"
          >
            Clear
          </button>
          <button
            type="button"
            @click="applySelection"
            class="px-4 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
          >
            Apply
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useGalleriesStore } from '@/stores/galleries'

const emit = defineEmits(['update:modelValue', 'create-new'])

const galleriesStore = useGalleriesStore()

const isOpen = ref(false)
const selectedGallery = ref(null) // Changed from array to single value
const createNewGallery = ref(false)
const loading = ref(false)

const galleries = computed(() => {
  // Debug: Check raw data
  console.log('[GallerySelector] Raw galleries from store:', galleriesStore.galleries)
  console.log('[GallerySelector] Galleries count:', galleriesStore.galleries?.length)
  
  // Filter out galleries that are already linked to an award
  const filtered = (galleriesStore.galleries || []).filter(gallery => {
    console.log(`[GallerySelector] Gallery "${gallery.title}" - award_id:`, gallery.award_id)
    return !gallery.award_id
  })
  
  console.log('[GallerySelector] Filtered galleries count:', filtered.length)
  return filtered
})

// Fetch galleries on mount
onMounted(async () => {
  loading.value = true
  try {
    console.log('[GallerySelector] Starting fetch galleries...')
    
    // Fetch all galleries without is_active filter
    // Filter on frontend if needed
    await galleriesStore.fetchGalleries(1, 100, {
      // Removed: is_active: true
      // This allows us to see all galleries
    })
    
    console.log('[GallerySelector] Fetch completed. Store galleries:', galleriesStore.galleries)
  } catch (error) {
    console.error('[GallerySelector] Failed to fetch galleries:', error)
    console.error('[GallerySelector] Error details:', error.response?.data)
  } finally {
    loading.value = false
    console.log('[GallerySelector] Loading finished. Final galleries count:', galleriesStore.galleries?.length)
  }
})

function isGallerySelected(galleryId) {
  return selectedGallery.value === galleryId
}

function getImageUrl(path) {
  if (!path) return ''
  if (path.startsWith('http')) return path
  return `${import.meta.env.VITE_API_URL.replace('/api', '')}${path}`
}

function clearSelection() {
  selectedGallery.value = null
  createNewGallery.value = false
}

function applySelection() {
  emit('update:modelValue', {
    galleryIds: selectedGallery.value ? [selectedGallery.value] : [], // Convert to array for backend compatibility
    createNew: createNewGallery.value
  })
  isOpen.value = false
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
