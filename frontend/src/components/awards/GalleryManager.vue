<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
          Linked Galleries
        </h3>
        <p class="text-sm text-neutral-600 dark:text-neutral-400">
          Manage photo galleries associated with this award
        </p>
      </div>
      <button
        @click="showLinkModal = true"
        type="button"
        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors flex items-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Link Gallery
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Linked Galleries Grid -->
    <div v-else-if="linkedGalleries.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="gallery in linkedGalleries"
        :key="gallery.id"
        class="relative group bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden hover:shadow-md transition-shadow"
      >
        <!-- Image -->
        <div class="aspect-video bg-neutral-100 dark:bg-neutral-900 overflow-hidden">
          <img
            v-if="gallery.image"
            :src="gallery.image"
            :alt="gallery.title"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex items-center justify-center">
            <svg class="w-12 h-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        </div>

        <!-- Info -->
        <div class="p-3">
          <h4 class="font-medium text-neutral-900 dark:text-neutral-100 truncate">
            {{ gallery.title }}
          </h4>
          <p v-if="gallery.category" class="text-sm text-neutral-600 dark:text-neutral-400">
            {{ gallery.category }}
          </p>
        </div>

        <!-- Unlink Button -->
        <button
          @click="handleUnlink(gallery.id)"
          type="button"
          class="absolute top-2 right-2 p-2 bg-red-500 hover:bg-red-600 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"
          :disabled="unlinking === gallery.id"
          aria-label="Unlink gallery"
        >
          <svg v-if="unlinking !== gallery.id" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12 bg-neutral-50 dark:bg-neutral-800/50 rounded-lg border-2 border-dashed border-neutral-300 dark:border-neutral-700">
      <svg class="w-16 h-16 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <p class="text-neutral-600 dark:text-neutral-400 mb-4">
        No galleries linked to this award yet
      </p>
      <button
        @click="showLinkModal = true"
        type="button"
        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors"
      >
        Link Your First Gallery
      </button>
    </div>

    <!-- Link Gallery Modal -->
    <BaseModal v-model="showLinkModal" title="Link Gallery to Award" size="lg">
      <template #default>
        <!-- Loading available galleries -->
        <div v-if="loadingAvailable" class="flex items-center justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        </div>

        <!-- Available galleries list -->
        <div v-else-if="availableGalleries.length > 0" class="space-y-2 max-h-96 overflow-y-auto">
          <button
            v-for="gallery in availableGalleries"
            :key="gallery.id"
            @click="handleLink(gallery.id)"
            type="button"
            class="w-full flex items-center gap-4 p-3 hover:bg-neutral-50 dark:hover:bg-neutral-800 rounded-lg transition-colors text-left"
            :disabled="linking === gallery.id"
          >
            <!-- Thumbnail -->
            <div class="w-20 h-20 bg-neutral-100 dark:bg-neutral-900 rounded-lg overflow-hidden flex-shrink-0">
              <img
                v-if="gallery.image"
                :src="gallery.image"
                :alt="gallery.title"
                class="w-full h-full object-cover"
              />
              <div v-else class="w-full h-full flex items-center justify-center">
                <svg class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <h4 class="font-medium text-neutral-900 dark:text-neutral-100 truncate">
                {{ gallery.title }}
              </h4>
              <p v-if="gallery.description" class="text-sm text-neutral-600 dark:text-neutral-400 truncate">
                {{ gallery.description }}
              </p>
              <p v-if="gallery.category" class="text-xs text-neutral-500 dark:text-neutral-500 mt-1">
                {{ gallery.category }}
              </p>
            </div>

            <!-- Link icon -->
            <svg v-if="linking !== gallery.id" class="w-5 h-5 text-primary-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg v-else class="w-5 h-5 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </button>
        </div>

        <!-- No available galleries -->
        <div v-else class="text-center py-8">
          <p class="text-neutral-600 dark:text-neutral-400 mb-4">
            No more galleries available to link
          </p>
          <router-link
            to="/admin/gallery"
            class="text-primary-600 hover:text-primary-700 font-medium"
          >
            Create a new gallery
          </router-link>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import BaseModal from '@/components/base/BaseModal.vue'
import { useUiStore } from '@/stores/ui'

const props = defineProps({
  awardId: {
    type: [String, Number],
    required: true
  }
})

const emit = defineEmits(['updated'])

const uiStore = useUiStore()

const linkedGalleries = ref([])
const allGalleries = ref([])
const loading = ref(false)
const loadingAvailable = ref(false)
const showLinkModal = ref(false)
const linking = ref(null)
const unlinking = ref(null)

// Compute available galleries (not yet linked)
const availableGalleries = computed(() => {
  const linkedIds = linkedGalleries.value.map(g => g.id)
  return allGalleries.value.filter(g => !linkedIds.includes(g.id))
})

onMounted(() => {
  fetchLinkedGalleries()
})

// Fetch linked galleries
async function fetchLinkedGalleries() {
  loading.value = true

  try {
    const response = await api.get(`/awards/${props.awardId}/galleries`)
    linkedGalleries.value = response.data.data.galleries || []
  } catch (error) {
    console.error('Failed to fetch linked galleries:', error)
    uiStore.showError('Failed to load linked galleries')
  } finally {
    loading.value = false
  }
}

// Fetch all available galleries
async function fetchAvailableGalleries() {
  loadingAvailable.value = true

  try {
    const response = await api.get('/gallery')
    // Handle both { data: [...] } and { data: { data: [...] } } formats
    allGalleries.value = Array.isArray(response.data.data) 
      ? response.data.data 
      : (response.data || [])
  } catch (error) {
    console.error('Failed to fetch available galleries:', error)
    uiStore.showError('Failed to load available galleries')
  } finally {
    loadingAvailable.value = false
  }
}

// Open modal and fetch available galleries
function openLinkModal() {
  showLinkModal.value = true
  if (allGalleries.value.length === 0) {
    fetchAvailableGalleries()
  }
}

// Watch modal state
import { watch } from 'vue'
watch(showLinkModal, (newValue) => {
  if (newValue && allGalleries.value.length === 0) {
    fetchAvailableGalleries()
  }
})

// Link gallery to award
async function handleLink(galleryId) {
  linking.value = galleryId

  try {
    await api.post(`/admin/awards/${props.awardId}/galleries`, {
      gallery_id: galleryId,
      sort_order: linkedGalleries.value.length
    })

    uiStore.showSuccess('Gallery linked successfully')

    // Refresh linked galleries
    await fetchLinkedGalleries()

    showLinkModal.value = false
    emit('updated')
  } catch (error) {
    console.error('Failed to link gallery:', error)
    uiStore.showError(
      error.response?.data?.message || 'Failed to link gallery'
    )
  } finally {
    linking.value = null
  }
}

// Unlink gallery from award
async function handleUnlink(galleryId) {
  if (!confirm('Are you sure you want to unlink this gallery?')) {
    return
  }

  unlinking.value = galleryId

  try {
    await api.delete(`/admin/awards/${props.awardId}/galleries/${galleryId}`)

    uiStore.showSuccess('Gallery unlinked successfully')

    // Remove from local state
    linkedGalleries.value = linkedGalleries.value.filter(g => g.id !== galleryId)

    emit('updated')
  } catch (error) {
    console.error('Failed to unlink gallery:', error)
    uiStore.showError(
      error.response?.data?.message || 'Failed to unlink gallery'
    )
  } finally {
    unlinking.value = null
  }
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
