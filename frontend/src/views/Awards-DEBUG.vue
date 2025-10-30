<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-8">
    <h1 class="text-3xl mb-8">Awards Gallery Debug</h1>
    
    <!-- Simple Modal Test -->
    <button 
      @click="testModal"
      class="px-6 py-3 bg-blue-600 text-white rounded-lg"
    >
      Test Modal (Award ID 8)
    </button>

    <!-- Debug Output -->
    <div class="mt-8 p-4 bg-white rounded-lg">
      <h3 class="font-bold mb-4">Debug Output:</h3>
      <div class="space-y-2 text-sm font-mono">
        <p>showGalleryModal: {{ showGalleryModal }}</p>
        <p>loadingGallery: {{ loadingGallery }}</p>
        <p>galleryPhotos.length: {{ galleryPhotos.length }}</p>
        <div v-if="galleryPhotos.length > 0" class="mt-4 space-y-1">
          <p class="font-bold">Photos:</p>
          <div v-for="(photo, idx) in galleryPhotos" :key="idx" class="pl-4">
            <p>{{ idx + 1 }}. {{ photo.title }}</p>
            <p class="text-xs text-gray-600">{{ photo.image }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Simple Modal -->
    <div
      v-if="showGalleryModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
      @click="showGalleryModal = false"
    >
      <div 
        class="bg-white p-8 rounded-xl max-w-4xl w-full max-h-[90vh] overflow-auto"
        @click.stop
      >
        <h2 class="text-2xl font-bold mb-6">Gallery Modal</h2>
        
        <!-- Loading -->
        <div v-if="loadingGallery" class="text-center py-8">
          Loading...
        </div>

        <!-- Photos Grid -->
        <div v-else-if="galleryPhotos.length > 0" class="grid grid-cols-4 gap-4">
          <div
            v-for="(photo, index) in galleryPhotos"
            :key="photo.id"
            class="border rounded-lg overflow-hidden"
          >
            <img
              :src="photo.image"
              :alt="photo.title"
              class="w-full h-48 object-cover"
              @error="handleImageError($event, photo)"
            />
            <p class="p-2 text-xs truncate">{{ photo.title }}</p>
          </div>
        </div>

        <!-- Empty -->
        <div v-else class="text-center py-8 text-gray-500">
          No photos (galleryPhotos.length = {{ galleryPhotos.length }})
        </div>

        <button
          @click="showGalleryModal = false"
          class="mt-6 px-6 py-2 bg-gray-600 text-white rounded-lg"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const showGalleryModal = ref(false)
const loadingGallery = ref(false)
const galleryPhotos = ref([])

const testModal = async () => {
  console.log('[DEBUG] Test modal clicked')
  
  showGalleryModal.value = true
  loadingGallery.value = true
  galleryPhotos.value = []

  try {
    console.log('[DEBUG] Fetching award 8 galleries...')
    const response = await api.get('/awards/8/galleries')
    console.log('[DEBUG] Response:', response.data)
    
    if (response.data.success && response.data.data.galleries) {
      const allPhotos = []
      
      response.data.data.galleries.forEach(gallery => {
        console.log('[DEBUG] Gallery:', gallery.title, '- Items:', gallery.items?.length)
        
        if (gallery.items && gallery.items.length > 0) {
          allPhotos.push(...gallery.items)
        }
      })
      
      console.log('[DEBUG] Total photos:', allPhotos.length, allPhotos)
      galleryPhotos.value = allPhotos
    }
  } catch (err) {
    console.error('[DEBUG] Error:', err)
    alert('Error: ' + err.message)
  } finally {
    loadingGallery.value = false
    console.log('[DEBUG] Final galleryPhotos.value:', galleryPhotos.value)
  }
}

const handleImageError = (event, photo) => {
  console.error('[DEBUG] Image failed to load:', photo.image)
  event.target.src = 'https://via.placeholder.com/400x300/cccccc/666666?text=Image+Not+Found'
}
</script>
