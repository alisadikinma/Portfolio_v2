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
        v-model="formData.image"
        :current-image="formData.current_image"
        :error="errors.image"
        @blur="validateField('image')"
      />
      <p v-if="errors.image" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.image }}
      </p>
    </div>

    <!-- Credential Info and Date -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <BaseInput
          v-model="formData.credential_id"
          label="Credential ID"
          type="text"
          placeholder="Certificate/credential ID"
          :error="errors.credential_id"
          @blur="validateField('credential_id')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.credential_url"
          label="Credential URL"
          type="url"
          placeholder="https://example.com/verify"
          :error="errors.credential_url"
          @blur="validateField('credential_url')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.received_at"
          label="Award Date"
          type="date"
          required
          :error="errors.received_at"
          @blur="validateField('received_at')"
        />
      </div>
    </div>

    <!-- Order/Sort -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model.number="formData.order"
          label="Display Order"
          type="number"
          placeholder="0"
          min="0"
          :error="errors.order"
          @blur="validateField('order')"
        >
          <template #help>
            Lower numbers appear first (0 = first)
          </template>
        </BaseInput>
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
import { ref } from 'vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import RichTextEditor from '@/components/blog/RichTextEditor.vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'

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
  image: null,
  current_image: null,
  credential_id: '',
  credential_url: '',
  received_at: '',
  order: 0,
  featured_gallery_id: null
})

// Form errors
const errors = ref({})

// Initialize form with award data if editing
if (props.award) {
  formData.value = {
    title: props.award.title || '',
    organization: props.award.organization || '',
    description: props.award.description || '',
    image: null,
    current_image: props.award.image || null,
    credential_id: props.award.credential_id || '',
    credential_url: props.award.credential_url || '',
    received_at: props.award.received_at || '',
    order: props.award.order || 0,
    featured_gallery_id: props.award.featured_gallery_id || null
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
      if (!formData.value.received_at) {
        errors.value.received_at = 'Award date is required'
      }
      break

    case 'credential_url':
      if (formData.value.credential_url && !isValidUrl(formData.value.credential_url)) {
        errors.value.credential_url = 'Please enter a valid URL'
      }
      break

    case 'order':
      if (formData.value.order < 0) {
        errors.value.order = 'Order must be 0 or greater'
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

  validateField('title')
  validateField('organization')
  validateField('received_at')
  validateField('credential_url')
  validateField('order')

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
  submissionData.append('received_at', formData.value.received_at)

  // Add optional fields
  if (formData.value.description) {
    submissionData.append('description', formData.value.description.trim())
  }
  if (formData.value.credential_id) {
    submissionData.append('credential_id', formData.value.credential_id.trim())
  }
  if (formData.value.credential_url) {
    submissionData.append('credential_url', formData.value.credential_url.trim())
  }
  if (formData.value.order !== null && formData.value.order !== undefined) {
    submissionData.append('order', formData.value.order)
  }
  if (formData.value.featured_gallery_id) {
    submissionData.append('featured_gallery_id', formData.value.featured_gallery_id)
  }

  // Add award image if changed
  if (formData.value.image) {
    submissionData.append('image', formData.value.image)
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
