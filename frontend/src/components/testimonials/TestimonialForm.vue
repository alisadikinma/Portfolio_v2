<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Client Name and Company -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model="formData.client_name"
          label="Client Name"
          type="text"
          placeholder="Enter client name"
          required
          :error="errors.client_name"
          @blur="validateField('client_name')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.company_name"
          label="Company Name"
          type="text"
          placeholder="Company name"
          :error="errors.company_name"
          @blur="validateField('company_name')"
        />
      </div>
    </div>

    <!-- Job Title -->
    <div>
      <BaseInput
        v-model="formData.job_title"
        label="Job Title"
        type="text"
        placeholder="e.g., CEO, Product Manager"
        :error="errors.job_title"
        @blur="validateField('job_title')"
      />
    </div>

    <!-- Testimonial Text -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Testimonial Text <span class="text-red-500">*</span>
      </label>
      <RichTextEditor
        v-model="formData.testimonial_text"
        placeholder="Write the testimonial..."
        :error="errors.testimonial_text"
        @blur="validateField('testimonial_text')"
      />
      <p v-if="errors.testimonial_text" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.testimonial_text }}
      </p>
    </div>

    <!-- Client Photo -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Client Photo
      </label>
      <ImageUploader
        v-model="formData.client_photo"
        :current-image="formData.current_photo"
        :error="errors.client_photo"
        @blur="validateField('client_photo')"
      />
      <p v-if="errors.client_photo" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.client_photo }}
      </p>
    </div>

    <!-- Star Rating -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Star Rating <span class="text-red-500">*</span>
      </label>
      <div class="flex items-center gap-2">
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          @click="setRating(star)"
          class="transition-colors duration-150"
          :class="star <= formData.star_rating ? 'text-yellow-400 hover:text-yellow-500' : 'text-neutral-300 dark:text-neutral-600 hover:text-neutral-400'"
        >
          <svg
            class="w-8 h-8"
            :fill="star <= formData.star_rating ? 'currentColor' : 'none'"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
            />
          </svg>
        </button>
        <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">
          {{ formData.star_rating }}/5
        </span>
      </div>
      <p v-if="errors.star_rating" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.star_rating }}
      </p>
    </div>

    <!-- Active Status and Sort Order -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="formData.is_active"
            type="checkbox"
            class="w-4 h-4 text-primary-600 bg-neutral-100 border-neutral-300 rounded focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-neutral-800 focus:ring-2 dark:bg-neutral-700 dark:border-neutral-600"
          />
          <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
            Active (visible on website)
          </span>
        </label>
      </div>

      <div>
        <BaseInput
          v-model.number="formData.sort_order"
          label="Sort Order"
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
  testimonial: {
    type: Object,
    default: null
  },
  submitLabel: {
    type: String,
    default: 'Create Testimonial'
  },
  isSubmitting: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'cancel'])

// Form data
const formData = ref({
  client_name: '',
  company_name: '',
  job_title: '',
  testimonial_text: '',
  client_photo: null,
  current_photo: null,
  star_rating: 5,
  is_active: true,
  sort_order: 0
})

// Form errors
const errors = ref({})

// Initialize form with testimonial data if editing
if (props.testimonial) {
  formData.value = {
    client_name: props.testimonial.client_name || '',
    company_name: props.testimonial.company_name || '',
    job_title: props.testimonial.job_title || '',
    testimonial_text: props.testimonial.testimonial_text || '',
    client_photo: null,
    current_photo: props.testimonial.client_photo || null,
    star_rating: props.testimonial.star_rating || 5,
    is_active: props.testimonial.is_active !== undefined ? props.testimonial.is_active : true,
    sort_order: props.testimonial.sort_order || 0
  }
}

// Set star rating
function setRating(rating) {
  formData.value.star_rating = rating
  validateField('star_rating')
}

// Validate single field
function validateField(field) {
  errors.value[field] = ''

  switch (field) {
    case 'client_name':
      if (!formData.value.client_name.trim()) {
        errors.value.client_name = 'Client name is required'
      } else if (formData.value.client_name.length > 255) {
        errors.value.client_name = 'Client name must be less than 255 characters'
      }
      break

    case 'company_name':
      if (formData.value.company_name && formData.value.company_name.length > 255) {
        errors.value.company_name = 'Company name must be less than 255 characters'
      }
      break

    case 'job_title':
      if (formData.value.job_title && formData.value.job_title.length > 255) {
        errors.value.job_title = 'Job title must be less than 255 characters'
      }
      break

    case 'testimonial_text':
      if (!formData.value.testimonial_text.trim()) {
        errors.value.testimonial_text = 'Testimonial text is required'
      }
      break

    case 'star_rating':
      if (formData.value.star_rating < 1 || formData.value.star_rating > 5) {
        errors.value.star_rating = 'Star rating must be between 1 and 5'
      }
      break

    case 'sort_order':
      if (formData.value.sort_order < 0) {
        errors.value.sort_order = 'Sort order must be 0 or greater'
      }
      break
  }
}

// Validate entire form
function validateForm() {
  errors.value = {}

  validateField('client_name')
  validateField('company_name')
  validateField('job_title')
  validateField('testimonial_text')
  validateField('star_rating')
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
  submissionData.append('client_name', formData.value.client_name.trim())
  submissionData.append('testimonial_text', formData.value.testimonial_text.trim())
  submissionData.append('star_rating', formData.value.star_rating)

  // Add optional fields
  if (formData.value.company_name) {
    submissionData.append('company_name', formData.value.company_name.trim())
  }
  if (formData.value.job_title) {
    submissionData.append('job_title', formData.value.job_title.trim())
  }

  submissionData.append('is_active', formData.value.is_active ? 1 : 0)

  if (formData.value.sort_order !== null && formData.value.sort_order !== undefined) {
    submissionData.append('sort_order', formData.value.sort_order)
  }

  // Add client photo if changed
  if (formData.value.client_photo) {
    submissionData.append('client_photo', formData.value.client_photo)
  }

  // Add _method for Laravel PUT spoofing (only if editing)
  if (props.testimonial) {
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
