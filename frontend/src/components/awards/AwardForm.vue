<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Award Title and Organization -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model="formData.title"
          label="Award Title"
          type="text"
          placeholder="Enter award title"
          required
          :error="errors.title"
          @blur="validateField('title')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.organization"
          label="Issuing Organization"
          type="text"
          placeholder="Organization name"
          required
          :error="errors.organization"
          @blur="validateField('organization')"
        />
      </div>
    </div>

    <!-- Description -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Description
      </label>
      <RichTextEditor
        v-model="formData.description"
        placeholder="Write your award description..."
        :error="errors.description"
        @blur="validateField('description')"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.description }}
      </p>
    </div>

    <!-- Award Image -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Award Image
      </label>
      <ImageUploader
        v-model="imageValue"
        :error="errors.image"
        @blur="validateField('image')"
      />
      <p v-if="errors.image" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.image }}
      </p>
    </div>

    <!-- Credential Info Box (only for new awards) -->
    <div v-if="!award" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="flex-1">
          <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-1">
            Auto-Generated Credentials
          </h4>
          <p class="text-xs text-blue-800 dark:text-blue-300">
            A unique <strong>Credential ID</strong> and <strong>Credential URL</strong> will be automatically generated for this award. 
            You can customize the Credential URL in the field below if needed.
          </p>
        </div>
      </div>
    </div>

    <!-- Award Date and Credential URL -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model="formData.received_at"
          label="Award Date"
          type="text"
          placeholder="e.g., January 2025, Q1 2025, 2025-01-15"
          required
          :error="errors.received_at"
          @blur="validateField('received_at')"
        >
          <template #help>
            Flexible format: "January 2025", "Q1 2025", or standard date
          </template>
        </BaseInput>
      </div>

      <div>
        <BaseInput
          v-model="formData.credential_url"
          label="Credential URL (Optional)"
          type="url"
          placeholder="Auto-generated from credential ID"
          :error="errors.credential_url"
          @blur="validateField('credential_url')"
        >
          <template #help>
            Leave empty to use auto-generated URL based on credential ID
          </template>
        </BaseInput>
      </div>
    </div>

    <!-- Order/Sort & Gallery Link -->
    <div class="grid grid-cols-1 gap-6">
      <div>
        <BaseInput
          v-model.number="formData.sort_order"
          label="Display Order"
          type="number"
          placeholder="0"
          min="0"
          :error="errors.sort_order"
          @blur="validateField('sort_order')"
        >
          <template #help>
            Lower numbers appear first (0 = first)
          </template>
        </BaseInput>
      </div>
      
      <div v-if="!award">
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
          Gallery Management
        </label>
        <GallerySelector v-model="formData.gallerySelection" />
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 pt-6 border-t border-neutral-200 dark:border-neutral-700">
      <BaseButton
        type="button"
        button-type="secondary"
        @click="handleCancel"
        :disabled="isSubmitting"
      >
        Cancel
      </BaseButton>

      <BaseButton
        type="submit"
        button-type="primary"
        :disabled="isSubmitting"
        :loading="isSubmitting"
      >
        {{ submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import RichTextEditor from '@/components/blog/RichTextEditor.vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'
import GallerySelector from '@/components/awards/GallerySelector.vue'

const props = defineProps({
  award: {
    type: Object,
    default: null
  },
  submitLabel: {
    type: String,
    default: 'Create Award'
  },
  isSubmitting: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'cancel'])

// Form data
const formData = ref({
  title: '',
  organization: '',
  description: '',
  credential_id: '',
  credential_url: '',
  received_at: '',
  sort_order: 0,
  featured_gallery_id: null,
  gallerySelection: {
    galleryIds: [],
    createNew: false
  }
})

// Separate image handling
const imageValue = ref(null)
const hasNewImage = ref(false)

// Helper to get full image URL
const getImageUrl = (imagePath) => {
  if (!imagePath) return null
  if (imagePath.startsWith('http')) return imagePath
  // Prepend backend URL for relative paths
  const backendUrl = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') || 'http://localhost/Portfolio_v2/backend/public'
  const cleanPath = imagePath.startsWith('/') ? imagePath : `/${imagePath}`
  return `${backendUrl}${cleanPath}`
}

// Auto-generate credential ID on mount (only for new awards)
const generateCredentialId = () => {
  const timestamp = Date.now()
  const random = Math.random().toString(36).substring(2, 8).toUpperCase()
  return `AWARD-${timestamp}-${random}`
}

// Auto-generate credential URL from credential ID
const generateCredentialUrl = (credentialId) => {
  if (!credentialId) return ''
  // Example: https://credentials.example.com/verify/AWARD-1234567890-ABC123
  return `https://credentials.portfolio.com/verify/${credentialId}`
}

onMounted(() => {
  if (!props.award) {
    // Auto-generate credential ID and URL for new awards
    const credentialId = generateCredentialId()
    formData.value.credential_id = credentialId
    // Only set credential_url if empty (allow user override)
    if (!formData.value.credential_url) {
      formData.value.credential_url = generateCredentialUrl(credentialId)
    }
  }
})

// Form errors
const errors = ref({})

// Initialize form with award data if editing
if (props.award) {
  formData.value = {
    title: props.award.title || '',
    organization: props.award.organization || '',
    description: props.award.description || '',
    credential_id: props.award.credential_id || '',
    credential_url: props.award.credential_url || '',
    received_at: props.award.received_at || '',
    sort_order: props.award.sort_order || 0,
    featured_gallery_id: props.award.featured_gallery_id || null
  }
  
  // Set existing image URL for preview
  if (props.award.image) {
    imageValue.value = getImageUrl(props.award.image)
  }
}

// Validate single field
function validateField(field) {
  errors.value[field] = ''

  switch (field) {
    case 'title':
      if (!formData.value.title.trim()) {
        errors.value.title = 'Award title is required'
      } else if (formData.value.title.length > 255) {
        errors.value.title = 'Title must be less than 255 characters'
      }
      break

    case 'organization':
      if (!formData.value.organization.trim()) {
        errors.value.organization = 'Issuing organization is required'
      } else if (formData.value.organization.length > 255) {
        errors.value.organization = 'Organization name must be less than 255 characters'
      }
      break

    case 'received_at':
      if (!formData.value.received_at || !formData.value.received_at.trim()) {
        errors.value.received_at = 'Award date is required'
      } else if (formData.value.received_at.length > 100) {
        errors.value.received_at = 'Award date must be less than 100 characters'
      }
      break

    case 'credential_url':
      // Optional field - only validate if provided
      if (formData.value.credential_url && formData.value.credential_url.trim() && !isValidUrl(formData.value.credential_url)) {
        errors.value.credential_url = 'Please enter a valid URL'
      }
      break

    case 'sort_order':
      if (formData.value.sort_order < 0) {
        errors.value.sort_order = 'Order must be 0 or greater'
      }
      break
  }
}

// Validate URL
function isValidUrl(url) {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

// Validate entire form
function validateForm() {
  errors.value = {}

  // Validate required fields
  validateField('title')
  validateField('organization')
  validateField('received_at')
  
  // Validate optional fields
  validateField('credential_url')
  validateField('sort_order')

  return Object.keys(errors.value).every(key => !errors.value[key])
}

// Handle submit
function handleSubmit() {
  if (!validateForm()) {
    return
  }

  // Prepare submission data
  const submissionData = new FormData()

  // Add all required fields
  submissionData.append('title', formData.value.title.trim())
  submissionData.append('organization', formData.value.organization.trim())
  submissionData.append('received_at', formData.value.received_at.trim())

  // Add optional fields
  if (formData.value.description) {
    submissionData.append('description', formData.value.description.trim())
  }
  
  // Add auto-generated or custom credential ID
  if (formData.value.credential_id) {
    submissionData.append('credential_id', formData.value.credential_id.trim())
  }
  
  if (formData.value.credential_url) {
    submissionData.append('credential_url', formData.value.credential_url.trim())
  }
  
  if (formData.value.sort_order !== null && formData.value.sort_order !== undefined) {
    submissionData.append('sort_order', formData.value.sort_order)
  }
  
  if (formData.value.featured_gallery_id) {
    submissionData.append('featured_gallery_id', formData.value.featured_gallery_id)
  }

  // Add award image if changed (only add if it's a File object)
  if (imageValue.value && imageValue.value instanceof File) {
    submissionData.append('image', imageValue.value)
    hasNewImage.value = true
  }

  // Add gallery selection data (for frontend routing, not backend)
  // Backend will handle gallery linking separately via API
  if (formData.value.gallerySelection) {
    if (formData.value.gallerySelection.galleryIds?.length > 0) {
      // Add each gallery ID
      formData.value.gallerySelection.galleryIds.forEach(id => {
        submissionData.append('gallery_ids[]', id)
      })
    }
    // Add createNew flag
    submissionData.append('create_new_gallery', formData.value.gallerySelection.createNew)
  }

  // Add _method for Laravel PUT spoofing (only if editing)
  if (props.award) {
    submissionData.append('_method', 'PUT')
  }

  emit('submit', submissionData)
}

// Handle cancel
function handleCancel() {
  emit('cancel')
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
