<template>
  <!-- Gallery Items Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-xl"
        @click.self="$emit('close')"
      >
        <div class="relative w-full max-w-7xl bg-white dark:bg-neutral-900 rounded-3xl shadow-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800">
          <!-- Modal Header -->
          <div class="relative p-8 border-b border-neutral-200 dark:border-neutral-800 bg-gradient-to-r from-primary-50 to-accent-50 dark:from-primary-950/50 dark:to-accent-950/50">
            <div class="flex items-start justify-between">
              <div class="flex-1 pr-12">
                <div v-if="company || period" class="flex items-center gap-3 mb-3">
                  <span v-if="company" class="px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm font-semibold">
                    {{ company }}
                  </span>
                  <span v-if="period" class="text-sm text-neutral-500 dark:text-neutral-400">
                    {{ period }}
                  </span>
                </div>
                <h3 class="text-3xl font-bold text-neutral-900 dark:text-white mb-2">
                  {{ title }}
                </h3>
                <p v-if="description" class="text-neutral-600 dark:text-neutral-400">
                  {{ stripHtml(description) }}
                </p>
              </div>
              <button
                @click="$emit('close')"
                class="flex-shrink-0 p-3 hover:bg-white/50 dark:hover:bg-neutral-800 rounded-2xl transition-colors group"
              >
                <svg class="w-6 h-6 text-neutral-400 group-hover:text-neutral-900 dark:group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-8 max-h-[65vh] overflow-y-auto custom-scrollbar">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-20">
              <div class="relative">
                <div class="w-16 h-16 border-4 border-primary-200 dark:border-primary-900 rounded-full"></div>
                <div class="absolute inset-0 w-16 h-16 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>
            </div>

            <!-- Gallery Grid -->
            <div v-else-if="items.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div
                v-for="(item, index) in items"
                :key="item.id || index"
                class="relative group cursor-pointer aspect-square rounded-2xl overflow-hidden bg-neutral-100 dark:bg-neutral-800"
                :style="{ animationDelay: `${index * 30}ms` }"
                @click="$emit('open-lightbox', index)"
              >
                <img
                  :src="getItemImageUrl(item)"
                  :alt="item.title || `Image ${index + 1}`"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                  @error="handleImageError"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <div class="absolute bottom-0 left-0 right-0 p-4">
                    <p v-if="item.title" class="text-white text-sm font-semibold truncate">
                      {{ item.title }}
                    </p>
                  </div>
                </div>
                <!-- Zoom Icon -->
                <div class="absolute top-3 right-3 w-8 h-8 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-20">
              <div class="inline-flex items-center justify-center w-20 h-20 bg-neutral-100 dark:bg-neutral-800 rounded-full mb-6">
                <svg class="w-10 h-10 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <p class="text-xl text-neutral-500 dark:text-neutral-400">{{ emptyMessage }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  company: {
    type: String,
    default: ''
  },
  period: {
    type: String,
    default: ''
  },
  items: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  emptyMessage: {
    type: String,
    default: 'No photos available'
  }
})

defineEmits(['close', 'open-lightbox'])

// Strip HTML tags
const stripHtml = (html) => {
  if (!html) return ''
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

// Get image URL with multiple format support
const getItemImageUrl = (item) => {
  const url = item.file_url || item.image || item.file_path || ''
  
  if (!url) return ''
  if (url.startsWith('http')) return url
  if (url.startsWith('/storage/')) {
    return import.meta.env.VITE_API_URL.replace('/api', '') + url
  }
  return import.meta.env.VITE_API_URL.replace('/api', '') + '/storage/' + url
}

const handleImageError = (event) => {
  console.error('Image failed to load:', event.target.src)
  event.target.style.display = 'none'
}
</script>

<style scoped>
/* Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.3);
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>
