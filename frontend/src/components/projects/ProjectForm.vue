<template>
  <form @submit.prevent="handleSubmit" class="space-y-6">
    <!-- Title and Slug -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <BaseInput
          v-model="formData.title"
          label="Project Title"
          type="text"
          placeholder="Enter project title"
          required
          :error="errors.title"
          @blur="validateField('title')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.slug"
          label="URL Slug"
          type="text"
          placeholder="auto-generated-from-title"
          :error="errors.slug"
          @blur="validateField('slug')"
        >
          <template #help>
            Auto-generated from title. Edit only if needed.
          </template>
        </BaseInput>
      </div>
    </div>

    <!-- Description -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Description <span class="text-red-500">*</span>
      </label>
      <RichTextEditor
        v-model="formData.description"
        placeholder="Write your project description..."
        :error="errors.description"
        @blur="validateField('description')"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.description }}
      </p>
    </div>

    <!-- Featured Image -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Featured Image
      </label>
      <ImageUploader
        v-model="formData.featured_image"
        :current-image="formData.current_featured_image"
        :error="errors.featured_image"
        @blur="validateField('featured_image')"
      />
      <p v-if="errors.featured_image" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.featured_image }}
      </p>
    </div>

    <!-- Technologies -->
    <div>
      <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
        Technologies
      </label>
      <div class="space-y-3">
        <!-- Technology Input -->
        <div class="flex gap-2">
          <BaseInput
            v-model="newTechnology"
            type="text"
            placeholder="e.g., Vue.js, Laravel, MySQL"
            @keydown.enter.prevent="addTechnology"
            class="flex-1"
          />
          <BaseButton
            type="button"
            @click="addTechnology"
            button-type="secondary"
            :disabled="!newTechnology.trim()"
          >
            Add
          </BaseButton>
        </div>

        <!-- Technology Chips -->
        <div v-if="formData.technologies.length > 0" class="flex flex-wrap gap-2">
          <div
            v-for="(tech, index) in formData.technologies"
            :key="index"
            class="inline-flex items-center gap-2 px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm"
          >
            <span>{{ tech }}</span>
            <button
              type="button"
              @click="removeTechnology(index)"
              class="hover:text-primary-900 dark:hover:text-primary-100 transition-colors"
              aria-label="Remove technology"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <p v-if="errors.technologies" class="text-sm text-red-600 dark:text-red-400">
          {{ errors.technologies }}
        </p>
      </div>
    </div>

    <!-- Client and URLs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <BaseInput
          v-model="formData.client_name"
          label="Client Name"
          type="text"
          placeholder="Client or company name"
          :error="errors.client_name"
          @blur="validateField('client_name')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.project_url"
          label="Project URL"
          type="url"
          placeholder="https://example.com"
          :error="errors.project_url"
          @blur="validateField('project_url')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.github_url"
          label="GitHub URL"
          type="url"
          placeholder="https://github.com/user/repo"
          :error="errors.github_url"
          @blur="validateField('github_url')"
        />
      </div>
    </div>

    <!-- Status and Dates -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div>
        <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
          Status <span class="text-red-500">*</span>
        </label>
        <select
          v-model="formData.status"
          class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          :class="{ 'border-red-500': errors.status }"
          @blur="validateField('status')"
        >
          <option value="planning">Planning</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
        </select>
        <p v-if="errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
          {{ errors.status }}
        </p>
      </div>

      <div>
        <BaseInput
          v-model="formData.start_date"
          label="Start Date"
          type="date"
          :error="errors.start_date"
          @blur="validateField('start_date')"
        />
      </div>

      <div>
        <BaseInput
          v-model="formData.end_date"
          label="End Date"
          type="date"
          :error="errors.end_date"
          @blur="validateField('end_date')"
        />
      </div>
    </div>

    <!-- Featured Checkbox -->
    <div class="flex items-center gap-3">
      <input
        id="featured"
        v-model="formData.is_featured"
        type="checkbox"
        class="w-4 h-4 text-primary-600 bg-white dark:bg-neutral-800 border-neutral-300 dark:border-neutral-600 rounded focus:ring-primary-500 focus:ring-2"
      />
      <label for="featured" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
        Feature this project (display prominently on homepage)
      </label>
    </div>

    <!-- SEO Settings (Collapsible) -->
    <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden">
      <button
        type="button"
        @click="showSeoSection = !showSeoSection"
        class="w-full px-4 py-3 bg-neutral-50 dark:bg-neutral-800 flex items-center justify-between hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
      >
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="font-medium text-neutral-900 dark:text-neutral-100">SEO Settings</span>
          <span class="text-sm text-neutral-500 dark:text-neutral-400">(Optional)</span>
        </div>
        <svg
          class="w-5 h-5 text-neutral-600 dark:text-neutral-400 transition-transform"
          :class="{ 'rotate-180': showSeoSection }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-show="showSeoSection" class="p-4 space-y-4 bg-white dark:bg-neutral-900">
        <!-- Meta Title -->
        <div>
          <BaseInput
            v-model="formData.meta_title"
            label="Meta Title"
            type="text"
            placeholder="Leave empty to use project title"
            :error="errors.meta_title"
            @blur="validateField('meta_title')"
          >
            <template #help>
              <span :class="metaTitleClass">
                {{ formData.meta_title.length }}/60 characters (optimal: 50-60)
              </span>
            </template>
          </BaseInput>
        </div>

        <!-- Meta Description -->
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Meta Description
          </label>
          <textarea
            v-model="formData.meta_description"
            rows="3"
            placeholder="Brief description for search engines (leave empty to auto-generate)"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            :class="{ 'border-red-500': errors.meta_description }"
            @blur="validateField('meta_description')"
          ></textarea>
          <div class="flex justify-between items-center mt-1">
            <p v-if="errors.meta_description" class="text-sm text-red-600 dark:text-red-400">
              {{ errors.meta_description }}
            </p>
            <p class="text-sm" :class="metaDescriptionClass">
              {{ formData.meta_description.length }}/160 characters (optimal: 150-160)
            </p>
          </div>
        </div>

        <!-- Focus Keyword -->
        <div>
          <BaseInput
            v-model="formData.focus_keyword"
            label="Focus Keyword"
            type="text"
            placeholder="Main SEO keyword/phrase"
            :error="errors.focus_keyword"
            @blur="validateField('focus_keyword')"
          />
        </div>

        <!-- Canonical URL -->
        <div>
          <BaseInput
            v-model="formData.canonical_url"
            label="Canonical URL"
            type="url"
            placeholder="https://example.com/projects/project-name"
            :error="errors.canonical_url"
            @blur="validateField('canonical_url')"
          >
            <template #help>
              Leave empty to use default URL. Set only if this content exists elsewhere.
            </template>
          </BaseInput>
        </div>
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
import { ref, computed, watch } from 'vue'
import BaseInput from '@/components/base/BaseInput.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import RichTextEditor from '@/components/blog/RichTextEditor.vue'
import ImageUploader from '@/components/blog/ImageUploader.vue'

const props = defineProps({
  project: {
    type: Object,
    default: null
  },
  submitLabel: {
    type: String,
    default: 'Create Project'
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
  slug: '',
  description: '',
  featured_image: null,
  current_featured_image: null,
  technologies: [],
  client_name: '',
  project_url: '',
  github_url: '',
  status: 'planning',
  start_date: '',
  end_date: '',
  is_featured: false,
  meta_title: '',
  meta_description: '',
  focus_keyword: '',
  canonical_url: ''
})

// Form errors
const errors = ref({})

// UI state
const showSeoSection = ref(false)
const newTechnology = ref('')

// Initialize form with project data if editing
if (props.project) {
  formData.value = {
    title: props.project.title || '',
    slug: props.project.slug || '',
    description: props.project.description || '',
    featured_image: null,
    current_featured_image: props.project.featured_image || null,
    technologies: props.project.technologies || [],
    client_name: props.project.client_name || '',
    project_url: props.project.project_url || '',
    github_url: props.project.github_url || '',
    status: props.project.status || 'planning',
    start_date: props.project.start_date || '',
    end_date: props.project.end_date || '',
    is_featured: props.project.is_featured || false,
    meta_title: props.project.meta_title || '',
    meta_description: props.project.meta_description || '',
    focus_keyword: props.project.focus_keyword || '',
    canonical_url: props.project.canonical_url || ''
  }
}

// Auto-generate slug from title
watch(() => formData.value.title, (newTitle) => {
  if (!props.project || formData.value.slug === generateSlug(props.project.title)) {
    formData.value.slug = generateSlug(newTitle)
  }
})

// Generate slug helper
function generateSlug(text) {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

// Meta title character count color
const metaTitleClass = computed(() => {
  const length = formData.value.meta_title.length
  if (length === 0) return 'text-neutral-500 dark:text-neutral-400'
  if (length < 50) return 'text-yellow-600 dark:text-yellow-400'
  if (length <= 60) return 'text-green-600 dark:text-green-400'
  return 'text-red-600 dark:text-red-400'
})

// Meta description character count color
const metaDescriptionClass = computed(() => {
  const length = formData.value.meta_description.length
  if (length === 0) return 'text-neutral-500 dark:text-neutral-400'
  if (length < 150) return 'text-yellow-600 dark:text-yellow-400'
  if (length <= 160) return 'text-green-600 dark:text-green-400'
  return 'text-red-600 dark:text-red-400'
})

// Add technology
function addTechnology() {
  const tech = newTechnology.value.trim()
  if (tech && !formData.value.technologies.includes(tech)) {
    formData.value.technologies.push(tech)
    newTechnology.value = ''
    errors.value.technologies = ''
  }
}

// Remove technology
function removeTechnology(index) {
  formData.value.technologies.splice(index, 1)
}

// Validate single field
function validateField(field) {
  errors.value[field] = ''

  switch (field) {
    case 'title':
      if (!formData.value.title.trim()) {
        errors.value.title = 'Project title is required'
      } else if (formData.value.title.length > 200) {
        errors.value.title = 'Title must be less than 200 characters'
      }
      break

    case 'slug':
      if (!formData.value.slug.trim()) {
        errors.value.slug = 'URL slug is required'
      } else if (!/^[a-z0-9-]+$/.test(formData.value.slug)) {
        errors.value.slug = 'Slug can only contain lowercase letters, numbers, and hyphens'
      }
      break

    case 'description':
      if (!formData.value.description.trim()) {
        errors.value.description = 'Project description is required'
      }
      break

    case 'status':
      if (!formData.value.status) {
        errors.value.status = 'Project status is required'
      }
      break

    case 'project_url':
      if (formData.value.project_url && !isValidUrl(formData.value.project_url)) {
        errors.value.project_url = 'Please enter a valid URL'
      }
      break

    case 'github_url':
      if (formData.value.github_url && !isValidUrl(formData.value.github_url)) {
        errors.value.github_url = 'Please enter a valid GitHub URL'
      }
      break

    case 'canonical_url':
      if (formData.value.canonical_url && !isValidUrl(formData.value.canonical_url)) {
        errors.value.canonical_url = 'Please enter a valid URL'
      }
      break

    case 'meta_title':
      if (formData.value.meta_title.length > 60) {
        errors.value.meta_title = 'Meta title should be 60 characters or less'
      }
      break

    case 'meta_description':
      if (formData.value.meta_description.length > 160) {
        errors.value.meta_description = 'Meta description should be 160 characters or less'
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
  validateField('slug')
  validateField('description')
  validateField('status')
  validateField('project_url')
  validateField('github_url')
  validateField('canonical_url')
  validateField('meta_title')
  validateField('meta_description')

  return Object.keys(errors.value).every(key => !errors.value[key])
}

// Handle submit
function handleSubmit() {
  if (!validateForm()) {
    return
  }

  // Prepare submission data
  const submissionData = new FormData()

  // Add all text fields
  submissionData.append('title', formData.value.title.trim())
  submissionData.append('slug', formData.value.slug.trim())
  submissionData.append('description', formData.value.description.trim())
  
  // Add technologies as array items
  formData.value.technologies.forEach((tech, index) => {
    submissionData.append(`technologies[${index}]`, tech)
  })
  
  submissionData.append('status', formData.value.status)
  submissionData.append('is_featured', formData.value.is_featured ? '1' : '0')

  // Add optional fields
  if (formData.value.client_name) {
    submissionData.append('client_name', formData.value.client_name.trim())
  }
  if (formData.value.project_url) {
    submissionData.append('project_url', formData.value.project_url.trim())
  }
  if (formData.value.github_url) {
    submissionData.append('github_url', formData.value.github_url.trim())
  }
  if (formData.value.start_date) {
    submissionData.append('start_date', formData.value.start_date)
  }
  if (formData.value.end_date) {
    submissionData.append('end_date', formData.value.end_date)
  }

  // Add SEO fields
  if (formData.value.meta_title) {
    submissionData.append('meta_title', formData.value.meta_title.trim())
  }
  if (formData.value.meta_description) {
    submissionData.append('meta_description', formData.value.meta_description.trim())
  }
  if (formData.value.focus_keyword) {
    submissionData.append('focus_keyword', formData.value.focus_keyword.trim())
  }
  if (formData.value.canonical_url) {
    submissionData.append('canonical_url', formData.value.canonical_url.trim())
  }

  // Add featured image if changed
  if (formData.value.featured_image) {
    submissionData.append('featured_image', formData.value.featured_image)
  }

  // Add _method for Laravel PUT spoofing (only if editing)
  if (props.project) {
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
