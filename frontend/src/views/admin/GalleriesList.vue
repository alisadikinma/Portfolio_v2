<template>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Gallery Management
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Manage your photo gallery collection
        </p>
      </div>
      <div class="flex items-center gap-3">
        <BaseButton
          v-if="selectedItems.length > 0"
          variant="danger"
          @click="handleBulkDelete"
          :disabled="isDeleting"
        >
          <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          Delete Selected ({{ selectedItems.length }})
        </BaseButton>
        <BaseButton @click="showUploadModal = true">
          <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Upload Images
        </BaseButton>
      </div>
    </div>

    <!-- Filters -->
    <BaseCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Search
          </label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search by title or description..."
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @input="debouncedSearch"
          />
        </div>

        <!-- Category Filter -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Category
          </label>
          <select
            v-model="filters.category"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @change="fetchGalleries"
          >
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
        </div>

        <!-- Sort -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Sort By
          </label>
          <select
            v-model="filters.order_by"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @change="fetchGalleries"
          >
            <option value="order">Order (Default)</option>
            <option value="created_at">Date Added</option>
            <option value="title">Title</option>
          </select>
        </div>
      </div>
    </BaseCard>

    <!-- Loading State -->
    <div v-if="isLoading && !gallery.length" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Gallery Grid -->
    <div v-else-if="gallery.length > 0" class="space-y-6">
      <!-- Select All -->
      <div class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
        <input
          type="checkbox"
          :checked="isAllSelected"
          @change="toggleSelectAll"
          class="rounded border-neutral-300 dark:border-neutral-600 text-primary-600 focus:ring-primary-500"
        />
        <span>Select All ({{ gallery.length }} items)</span>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="item in gallery"
          :key="item.id"
          class="relative group bg-white dark:bg-neutral-800 rounded-lg border-2 border-neutral-200 dark:border-neutral-700 overflow-hidden transition-all duration-200"
          :class="{ 'border-primary-500 ring-2 ring-primary-500': isSelected(item.id) }"
        >
          <!-- Select Checkbox -->
          <div class="absolute top-2 left-2 z-10">
            <input
              type="checkbox"
              :checked="isSelected(item.id)"
              @change="toggleSelect(item.id)"
              class="w-5 h-5 rounded border-neutral-300 dark:border-neutral-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-neutral-800"
            />
          </div>

          <!-- Image -->
          <div class="aspect-square bg-neutral-100 dark:bg-neutral-900 overflow-hidden">
            <img
              :src="item.image"
              :alt="item.title"
              class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
            />
          </div>

          <!-- Info -->
          <div class="p-3">
            <h3 class="font-medium text-neutral-900 dark:text-neutral-100 truncate text-sm">
              {{ item.title }}
            </h3>
            <p v-if="item.category" class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
              {{ item.category }}
            </p>
          </div>

          <!-- Actions Overlay -->
          <div class="absolute inset-0 bg-neutral-900/70 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center gap-2">
            <button
              @click="handleEdit(item)"
              class="p-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors"
              title="Edit"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="handleDelete(item.id)"
              class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
              :disabled="isDeleting"
              title="Delete"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2 mt-8">
        <button
          @click="changePage(pagination.currentPage - 1)"
          :disabled="pagination.currentPage === 1"
          class="px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
        >
          Previous
        </button>
        <span class="text-sm text-neutral-600 dark:text-neutral-400">
          Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
        </span>
        <button
          @click="changePage(pagination.currentPage + 1)"
          :disabled="pagination.currentPage === pagination.lastPage"
          class="px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <BaseCard v-else>
      <div class="text-center py-12">
        <svg class="w-16 h-16 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Gallery Items Found
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          {{ filters.search || filters.category ? 'Try adjusting your filters' : 'Get started by uploading your first images' }}
        </p>
        <BaseButton @click="showUploadModal = true">
          <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Upload Images
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Upload Modal -->
    <BaseModal v-model="showUploadModal" title="Upload Images" size="lg">
      <form @submit.prevent="handleUpload" class="space-y-4">
        <!-- Upload Type -->
        <div class="flex gap-4 p-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="uploadType"
              type="radio"
              value="single"
              class="text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Single Image</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="uploadType"
              type="radio"
              value="bulk"
              class="text-primary-600 focus:ring-primary-500"
            />
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Bulk Upload (Max 20)</span>
          </label>
        </div>

        <!-- Image Upload -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            {{ uploadType === 'single' ? 'Image' : 'Images' }} *
          </label>
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
            :multiple="uploadType === 'bulk'"
            @change="handleFileChange"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            required
          />
          <p class="text-xs text-neutral-500 dark:text-neutral-500 mt-1">
            Accepted: JPG, PNG, GIF, WEBP • Max size: 5MB per image
          </p>
        </div>

        <!-- Preview -->
        <div v-if="previewUrls.length > 0" class="grid grid-cols-3 gap-2">
          <div v-for="(url, index) in previewUrls" :key="index" class="aspect-square rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-900">
            <img :src="url" alt="Preview" class="w-full h-full object-cover" />
          </div>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Category *
          </label>
          <div class="flex gap-2">
            <select
              v-model="uploadForm.category"
              class="flex-1 px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              required
            >
              <option value="">Select category...</option>
              <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
              <option value="__new__">+ Add New Category</option>
            </select>
          </div>
          <input
            v-if="uploadForm.category === '__new__'"
            v-model="newCategory"
            type="text"
            placeholder="Enter new category name"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent mt-2"
            required
          />
        </div>

        <!-- Title (Single only) -->
        <div v-if="uploadType === 'single'">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Title *
          </label>
          <input
            v-model="uploadForm.title"
            type="text"
            placeholder="Enter image title"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            required
          />
        </div>

        <!-- Description (Single only) -->
        <div v-if="uploadType === 'single'">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Description
          </label>
          <textarea
            v-model="uploadForm.description"
            rows="3"
            placeholder="Enter image description (optional)"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
          <BaseButton
            type="button"
            variant="secondary"
            @click="showUploadModal = false"
            :disabled="isUploading"
          >
            Cancel
          </BaseButton>
          <BaseButton
            type="submit"
            :disabled="isUploading || !uploadForm.images"
          >
            <svg v-if="isUploading" class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isUploading ? 'Uploading...' : 'Upload' }}
          </BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- Edit Modal -->
    <BaseModal v-model="showEditModal" title="Edit Gallery Item" size="md">
      <form @submit.prevent="handleUpdate" class="space-y-4">
        <!-- Current Image -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Current Image
          </label>
          <div class="aspect-video rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-900">
            <img :src="editForm.image" :alt="editForm.title" class="w-full h-full object-cover" />
          </div>
        </div>

        <!-- Title -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Title *
          </label>
          <input
            v-model="editForm.title"
            type="text"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            required
          />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Description
          </label>
          <textarea
            v-model="editForm.description"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          ></textarea>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Category *
          </label>
          <select
            v-model="editForm.category"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            required
          >
            <option value="">Select category...</option>
            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
          <BaseButton
            type="button"
            variant="secondary"
            @click="showEditModal = false"
            :disabled="isUpdating"
          >
            Cancel
          </BaseButton>
          <BaseButton
            type="submit"
            :disabled="isUpdating"
          >
            <svg v-if="isUpdating" class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isUpdating ? 'Updating...' : 'Update' }}
          </BaseButton>
        </div>
      </form>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useGallery } from '@/composables/useGallery'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseModal from '@/components/base/BaseModal.vue'

const {
  gallery,
  isLoading,
  pagination,
  fetchGallery,
  uploadImage,
  bulkUploadImages,
  updateGalleryItem,
  deleteGalleryItem,
  bulkDeleteGalleryItems
} = useGallery()

const uiStore = useUiStore()

// State
const filters = ref({
  search: '',
  category: '',
  order_by: 'order',
  order_dir: 'asc'
})

const selectedItems = ref([])
const showUploadModal = ref(false)
const showEditModal = ref(false)
const uploadType = ref('single')
const isUploading = ref(false)
const isUpdating = ref(false)
const isDeleting = ref(false)
const fileInput = ref(null)
const previewUrls = ref([])
const newCategory = ref('')

// Categories (hardcoded for now, could be dynamic)
const categories = ref([
  'Award Ceremonies',
  'Projects',
  'Events',
  'Behind The Scenes',
  'Team',
  'Office',
  'Other'
])

const uploadForm = ref({
  images: null,
  title: '',
  description: '',
  category: ''
})

const editForm = ref({
  id: null,
  title: '',
  description: '',
  category: '',
  image: ''
})

// Computed
const isAllSelected = computed(() => {
  return gallery.value.length > 0 && selectedItems.value.length === gallery.value.length
})

// Methods
onMounted(() => {
  fetchGalleries()
})

async function fetchGalleries() {
  const params = {
    ...filters.value,
    page: pagination.value.currentPage
  }
  await fetchGallery(params)
}

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.value.currentPage = 1
    fetchGalleries()
  }, 500)
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.lastPage) {
    pagination.value.currentPage = page
    fetchGalleries()
  }
}

function isSelected(id) {
  return selectedItems.value.includes(id)
}

function toggleSelect(id) {
  const index = selectedItems.value.indexOf(id)
  if (index > -1) {
    selectedItems.value.splice(index, 1)
  } else {
    selectedItems.value.push(id)
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedItems.value = []
  } else {
    selectedItems.value = gallery.value.map(item => item.id)
  }
}

function handleFileChange(event) {
  const files = Array.from(event.target.files)
  uploadForm.value.images = files

  // Generate previews
  previewUrls.value = []
  files.forEach(file => {
    const reader = new FileReader()
    reader.onload = (e) => {
      previewUrls.value.push(e.target.result)
    }
    reader.readAsDataURL(file)
  })
}

async function handleUpload() {
  if (!uploadForm.value.images) return

  // Use new category if selected
  const categoryValue = uploadForm.value.category === '__new__' ? newCategory.value : uploadForm.value.category

  if (!categoryValue) {
    uiStore.showError('Please select or enter a category')
    return
  }

  isUploading.value = true

  try {
    const formData = new FormData()

    if (uploadType.value === 'single') {
      formData.append('image', uploadForm.value.images[0])
      formData.append('title', uploadForm.value.title)
      formData.append('description', uploadForm.value.description || '')
      formData.append('category', categoryValue)

      const result = await uploadImage(formData)
      if (result.success) {
        uiStore.showSuccess('Image uploaded successfully')
      } else {
        uiStore.showError(result.error)
      }
    } else {
      // Bulk upload
      uploadForm.value.images.forEach((file, index) => {
        formData.append('images[]', file)
      })
      formData.append('category', categoryValue)

      const result = await bulkUploadImages(formData)
      if (result.success) {
        uiStore.showSuccess(`${uploadForm.value.images.length} images uploaded successfully`)
      } else {
        uiStore.showError(result.error)
      }
    }

    // Reset and close
    showUploadModal.value = false
    resetUploadForm()
    await fetchGalleries()
  } catch (error) {
    uiStore.showError('Upload failed. Please try again.')
  } finally {
    isUploading.value = false
  }
}

function resetUploadForm() {
  uploadForm.value = {
    images: null,
    title: '',
    description: '',
    category: ''
  }
  previewUrls.value = []
  newCategory.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleEdit(item) {
  editForm.value = {
    id: item.id,
    title: item.title,
    description: item.description || '',
    category: item.category,
    image: item.image
  }
  showEditModal.value = true
}

async function handleUpdate() {
  isUpdating.value = true

  try {
    const formData = new FormData()
    formData.append('title', editForm.value.title)
    formData.append('description', editForm.value.description)
    formData.append('category', editForm.value.category)

    const result = await updateGalleryItem(editForm.value.id, formData)
    if (result.success) {
      uiStore.showSuccess('Gallery item updated successfully')
      showEditModal.value = false
      await fetchGalleries()
    } else {
      uiStore.showError(result.error)
    }
  } catch (error) {
    uiStore.showError('Update failed. Please try again.')
  } finally {
    isUpdating.value = false
  }
}

async function handleDelete(id) {
  if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
    return
  }

  isDeleting.value = true

  try {
    const result = await deleteGalleryItem(id)
    if (result.success) {
      uiStore.showSuccess('Gallery item deleted successfully')
      await fetchGalleries()
    } else {
      uiStore.showError(result.error)
    }
  } catch (error) {
    uiStore.showError('Delete failed. Please try again.')
  } finally {
    isDeleting.value = false
  }
}

async function handleBulkDelete() {
  if (selectedItems.value.length === 0) return

  if (!confirm(`Are you sure you want to delete ${selectedItems.value.length} selected images? This action cannot be undone.`)) {
    return
  }

  isDeleting.value = true

  try {
    const result = await bulkDeleteGalleryItems(selectedItems.value)
    if (result.success) {
      uiStore.showSuccess(`${selectedItems.value.length} gallery items deleted successfully`)
      selectedItems.value = []
      await fetchGalleries()
    } else {
      uiStore.showError(result.error)
    }
  } catch (error) {
    uiStore.showError('Bulk delete failed. Please try again.')
  } finally {
    isDeleting.value = false
  }
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
