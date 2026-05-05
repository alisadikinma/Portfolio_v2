<template>
  <!-- Gallery Items Modal - FIXED: proper max-height, sticky close button -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-xl"
        @click.self="$emit('close')"
      >
        <!-- FIXED: max-h-[90vh] + flex column layout -->
        <div class="relative w-full max-w-7xl max-h-[90vh] bg-white dark:bg-neutral-900 rounded-3xl shadow-2xl overflow-hidden border border-neutral-200 dark:border-neutral-800 flex flex-col">
          <!-- Close Button - FIXED: Sticky position, always visible -->
          <button
            @click="$emit('close')"
            class="absolute top-4 right-4 z-50 p-3 bg-white dark:bg-neutral-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-full shadow-lg transition-all hover:scale-110 group"
            title="Close (ESC)"
          >
            <svg class="w-6 h-6 text-gray-500 group-hover:text-red-600 dark:text-gray-400 dark:group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>

          <!-- Modal Header - FIXED: shrink-0 to prevent compression -->
          <div class="shrink-0 p-8 pr-16 border-b border-neutral-200 dark:border-neutral-800 bg-gradient-to-r from-primary-50 to-accent-50 dark:from-primary-950/50 dark:to-accent-950/50">
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
            <!-- FIXED: Description with max 3 lines, show "Read more" if truncated -->
            <p v-if="description" class="text-neutral-600 dark:text-neutral-400 line-clamp-3">
              {{ stripHtml(description) }}
            </p>
          </div>

          <!-- Tab strip — shown only when galleries[] carries 2+ award groups -->
          <div
            v-if="hasGalleryTabs"
            class="shrink-0 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/50 overflow-x-auto custom-scrollbar"
            role="tablist"
            :aria-label="`${galleries.length} awards in this experience`"
          >
            <div class="flex items-stretch px-2 min-w-max">
              <button
                v-for="(g, idx) in galleries"
                :key="g.id"
                type="button"
                role="tab"
                :aria-selected="activeTab === idx"
                :tabindex="activeTab === idx ? 0 : -1"
                @click="activeTab = idx"
                @keydown="handleTabKey($event, idx)"
                class="px-4 py-3 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-inset"
                :class="activeTab === idx
                  ? 'border-primary-600 text-primary-700 dark:text-primary-300'
                  : 'border-transparent text-neutral-500 dark:text-neutral-400 hover:text-primary-700 dark:hover:text-primary-300'"
              >
                {{ g.title }}
                <span class="ml-1.5 text-xs opacity-70">· {{ g.items_count }}</span>
              </button>
            </div>
          </div>

          <!-- Modal Body - FIXED: flex-1 + overflow for proper scrolling -->
          <div class="flex-1 p-8 overflow-y-auto custom-scrollbar">
            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-20">
              <div class="relative">
                <div class="w-16 h-16 border-4 border-primary-200 dark:border-primary-900 rounded-full"></div>
                <div class="absolute inset-0 w-16 h-16 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>
            </div>

            <!-- Gallery Grid -->
            <div v-else-if="displayItems.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div
                v-for="(item, index) in displayItems"
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
import { computed, ref, watch } from 'vue'

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
  // Optional per-award tab structure. When length >= 2, the modal renders
  // a tab strip and switches body content per active tab. Each entry shape:
  // { id, title, items_count, items: [{id, title, file_path|file_url|image}] }
  galleries: {
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

// Tab state (only relevant when galleries[] has 2+ entries)
const activeTab = ref(0)
const hasGalleryTabs = computed(() => Array.isArray(props.galleries) && props.galleries.length >= 2)

// Reset to first tab whenever the modal opens (and any time galleries change shape)
watch(() => props.show, (open) => {
  if (open) activeTab.value = 0
})
watch(() => props.galleries, () => {
  activeTab.value = 0
})

// Active dataset: tab content when in multi-gallery mode, else the flat items[]
const displayItems = computed(() => {
  if (hasGalleryTabs.value) {
    const g = props.galleries[activeTab.value]
    return Array.isArray(g?.items) ? g.items : []
  }
  return props.items
})

// Keyboard navigation: ←/→ cycles tabs with wraparound, Home/End jump to ends
const handleTabKey = (event, idx) => {
  const total = props.galleries.length
  if (!total) return
  let next = null
  switch (event.key) {
    case 'ArrowLeft':
      next = (idx - 1 + total) % total; break
    case 'ArrowRight':
      next = (idx + 1) % total; break
    case 'Home':
      next = 0; break
    case 'End':
      next = total - 1; break
    default:
      return
  }
  event.preventDefault()
  activeTab.value = next
}

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

/* Description truncation */
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
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
