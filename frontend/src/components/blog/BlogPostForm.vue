<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import RichTextEditor from './RichTextEditor.vue'
import ImageUploader from './ImageUploader.vue'
import CategorySelect from './CategorySelect.vue'

const props = defineProps({
  post: {
    type: Object,
    default: null
  },
  submitLabel: {
    type: String,
    default: 'Publish Post'
  },
  cancelLabel: {
    type: String,
    default: 'Cancel'
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
  excerpt: '',
  content: '',
  featured_image: null,
  category_id: null,
  status: 'draft',
  published_at: null,
  // SEO fields
  meta_title: '',
  meta_description: '',
  focus_keyword: '',
  canonical_url: ''
})

// Form errors
const errors = ref({})

// Other state
const isAutoSlug = ref(true)
const showAdvancedSeo = ref(false)
const uploadedImageFile = ref(null) // Store the File object separately

// Validation rules
const validationRules = {
  title: { required: true, minLength: 3, maxLength: 255 },
  slug: { required: true, minLength: 3, maxLength: 255, pattern: /^[a-z0-9]+(?:-[a-z0-9]+)*$/ },
  excerpt: { maxLength: 500 },
  content: { required: true, minLength: 100 },
  category_id: { required: true },
  meta_title: { maxLength: 60 },
  meta_description: { maxLength: 160 },
  focus_keyword: { maxLength: 100 },
  canonical_url: { pattern: /^https?:\/\/.+/ }
}

// Generate slug from title
const generateSlug = (text) => {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-')
    .replace(/^-+/, '')
    .replace(/-+$/, '')
}

// Watch title and auto-generate slug
watch(() => formData.value.title, (newTitle) => {
  if (isAutoSlug.value) {
    formData.value.slug = generateSlug(newTitle)
  }
})

// Convert File to base64 when image changes
const handleImageChange = (file) => {
  uploadedImageFile.value = file
  
  if (file instanceof File) {
    const reader = new FileReader()
    reader.onload = (e) => {
      formData.value.featured_image = e.target.result
    }
    reader.readAsDataURL(file)
  } else if (typeof file === 'string') {
    // Already a URL or base64
    formData.value.featured_image = file
  } else {
    // Null or undefined
    formData.value.featured_image = null
  }
}

// Validate field
const validateField = (field, value) => {
  const rules = validationRules[field]
  if (!rules) return null

  // Required
  if (rules.required && (!value || (typeof value === 'string' && value.trim() === ''))) {
    return `${field.replace('_', ' ')} is required`
  }

  // Min length
  if (rules.minLength && value && value.length < rules.minLength) {
    return `${field.replace('_', ' ')} must be at least ${rules.minLength} characters`
  }

  // Max length
  if (rules.maxLength && value && value.length > rules.maxLength) {
    return `${field.replace('_', ' ')} must not exceed ${rules.maxLength} characters`
  }

  // Pattern
  if (rules.pattern && value && !rules.pattern.test(value)) {
    if (field === 'slug') {
      return 'Slug must contain only lowercase letters, numbers, and hyphens'
    }
    if (field === 'canonical_url') {
      return 'Must be a valid URL starting with http:// or https://'
    }
    return `${field.replace('_', ' ')} format is invalid`
  }

  return null
}

// Validate all fields
const validateForm = () => {
  const newErrors = {}

  Object.keys(validationRules).forEach(field => {
    const error = validateField(field, formData.value[field])
    if (error) {
      newErrors[field] = error
    }
  })

  errors.value = newErrors
  return Object.keys(newErrors).length === 0
}

// Character count for fields
const titleCount = computed(() => formData.value.title.length)
const excerptCount = computed(() => formData.value.excerpt.length)
const metaTitleCount = computed(() => formData.value.meta_title.length)
const metaDescriptionCount = computed(() => formData.value.meta_description.length)

// Character count colors
const getCountColor = (current, max) => {
  const percentage = (current / max) * 100
  if (percentage >= 90) return 'text-red-600 dark:text-red-400'
  if (percentage >= 75) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-gray-500 dark:text-gray-400'
}

// Handle form submission
const handleSubmit = async (status = 'draft') => {
  formData.value.status = status

  // Set published_at if publishing
  if (status === 'published' && !formData.value.published_at) {
    formData.value.published_at = new Date().toISOString()
  }

  // Validate form
  if (!validateForm()) {
    // Scroll to first error
    const firstErrorField = Object.keys(errors.value)[0]
    const errorElement = document.querySelector(`[name="${firstErrorField}"]`)
    if (errorElement) {
      errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
      errorElement.focus()
    }
    return
  }

  // Transform data to match backend structure
  const submitData = {
    category_id: formData.value.category_id,
    title: formData.value.title,
    slug: formData.value.slug,
    excerpt: formData.value.excerpt || null,
    content: formData.value.content,
    featured_image: formData.value.featured_image || null,
    tags: [],
    is_premium: false,
    published: status === 'published',
    published_at: status === 'published' ? (formData.value.published_at || new Date().toISOString()) : null,
    translations: [
      {
        language: 'en',
        title: formData.value.title,
        slug: formData.value.slug,
        excerpt: formData.value.excerpt || null,
        content: formData.value.content,
        meta_title: formData.value.meta_title || null,
        meta_description: formData.value.meta_description || null,
        og_title: formData.value.meta_title || formData.value.title,
        og_description: formData.value.meta_description || formData.value.excerpt || null,
        canonical_url: formData.value.canonical_url || null,
        ai_summary: null
      }
    ]
  }

  console.log('BlogPostForm submitting data:', submitData)
  console.log('Featured image length:', submitData.featured_image?.length || 0)

  // Emit submit event
  emit('submit', submitData)
}

// Handle cancel
const handleCancel = () => {
  emit('cancel')
}

// Handle slug manual edit
const handleSlugInput = () => {
  isAutoSlug.value = false
}

// Load existing post data
const loadPostData = (post) => {
  if (!post) return

  formData.value = {
    title: post.title || '',
    slug: post.slug || '',
    excerpt: post.excerpt || '',
    content: post.content || '',
    featured_image: post.featured_image || null,
    category_id: post.category_id || null,
    status: post.status || 'draft',
    published_at: post.published_at || null,
    meta_title: post.meta_title || '',
    meta_description: post.meta_description || '',
    focus_keyword: post.focus_keyword || '',
    canonical_url: post.canonical_url || ''
  }

  // Set uploaded image for preview (existing images are URLs)
  if (post.featured_image && typeof post.featured_image === 'string') {
    uploadedImageFile.value = post.featured_image
  }

  isAutoSlug.value = false // Disable auto-slug for existing posts
}

// Watch for post prop changes
watch(() => props.post, (newPost) => {
  loadPostData(newPost)
}, { immediate: true, deep: true })

// Initialize on mount
onMounted(() => {
  if (props.post) {
    loadPostData(props.post)
  }
})
</script>

<template>
  <form class="blog-post-form space-y-6" @submit.prevent="handleSubmit('draft')">
    <!-- Title -->
    <div>
      <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Title <span class="text-red-500">*</span>
      </label>
      <input
        id="title"
        v-model="formData.title"
        name="title"
        type="text"
        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
        :class="{
          'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.title,
          'border-red-500 bg-red-50 dark:bg-red-900/20': errors.title
        }"
        placeholder="Enter your post title..."
        :disabled="isSubmitting"
      />
      <div class="flex justify-between items-center mt-1">
        <p v-if="errors.title" class="text-sm text-red-600 dark:text-red-400">
          {{ errors.title }}
        </p>
        <p
          class="text-xs ml-auto"
          :class="getCountColor(titleCount, 255)"
        >
          {{ titleCount }} / 255
        </p>
      </div>
    </div>

    <!-- Slug -->
    <div>
      <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Slug <span class="text-red-500">*</span>
        <span v-if="isAutoSlug" class="text-xs text-blue-600 dark:text-blue-400 font-normal ml-2">
          (Auto-generated)
        </span>
      </label>
      <input
        id="slug"
        v-model="formData.slug"
        name="slug"
        type="text"
        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors font-mono text-sm"
        :class="{
          'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.slug,
          'border-red-500 bg-red-50 dark:bg-red-900/20': errors.slug
        }"
        placeholder="post-slug-example"
        :disabled="isSubmitting"
        @input="handleSlugInput"
      />
      <p v-if="errors.slug" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.slug }}
      </p>
      <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        URL-friendly version of the title. Only lowercase letters, numbers, and hyphens.
      </p>
    </div>

    <!-- Category -->
    <CategorySelect
      v-model="formData.category_id"
      label="Category"
      placeholder="Select a category"
      :required="true"
      :error="errors.category_id"
      :disabled="isSubmitting"
    />

    <!-- Featured Image -->
    <ImageUploader
      :model-value="uploadedImageFile"
      label="Featured Image"
      description="PNG, JPG, GIF or WEBP (max 5MB)"
      aspect-ratio="16:9"
      :disabled="isSubmitting"
      @update:model-value="handleImageChange"
    />

    <!-- Excerpt -->
    <div>
      <label for="excerpt" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Excerpt
      </label>
      <textarea
        id="excerpt"
        v-model="formData.excerpt"
        name="excerpt"
        rows="3"
        class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
        :class="{
          'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.excerpt,
          'border-red-500 bg-red-50 dark:bg-red-900/20': errors.excerpt
        }"
        placeholder="A brief summary of your post..."
        :disabled="isSubmitting"
      ></textarea>
      <div class="flex justify-between items-center mt-1">
        <p v-if="errors.excerpt" class="text-sm text-red-600 dark:text-red-400">
          {{ errors.excerpt }}
        </p>
        <p
          class="text-xs ml-auto"
          :class="getCountColor(excerptCount, 500)"
        >
          {{ excerptCount }} / 500
        </p>
      </div>
    </div>

    <!-- Content (Rich Text Editor) -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Content <span class="text-red-500">*</span>
      </label>
      <RichTextEditor
        v-model="formData.content"
        placeholder="Start writing your post content..."
        :disabled="isSubmitting"
        min-height="500px"
      />
      <p v-if="errors.content" class="mt-2 text-sm text-red-600 dark:text-red-400">
        {{ errors.content }}
      </p>
    </div>

    <!-- Advanced SEO Section (Collapsible) -->
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
      <button
        type="button"
        class="flex items-center justify-between w-full text-left"
        @click="showAdvancedSeo = !showAdvancedSeo"
      >
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center">
          <svg
            class="h-5 w-5 mr-2 text-gray-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
            />
          </svg>
          Advanced SEO Settings
        </h3>
        <svg
          class="h-5 w-5 text-gray-500 transition-transform"
          :class="{ 'rotate-180': showAdvancedSeo }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-show="showAdvancedSeo" class="mt-4 space-y-4">
          <!-- Meta Title -->
          <div>
            <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Meta Title
            </label>
            <input
              id="meta_title"
              v-model="formData.meta_title"
              name="meta_title"
              type="text"
              class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              :class="{
                'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.meta_title,
                'border-red-500 bg-red-50 dark:bg-red-900/20': errors.meta_title
              }"
              placeholder="SEO title for search engines..."
              :disabled="isSubmitting"
            />
            <div class="flex justify-between items-center mt-1">
              <p v-if="errors.meta_title" class="text-sm text-red-600 dark:text-red-400">
                {{ errors.meta_title }}
              </p>
              <p
                class="text-xs ml-auto"
                :class="getCountColor(metaTitleCount, 60)"
              >
                {{ metaTitleCount }} / 60
              </p>
            </div>
          </div>

          <!-- Meta Description -->
          <div>
            <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Meta Description
            </label>
            <textarea
              id="meta_description"
              v-model="formData.meta_description"
              name="meta_description"
              rows="2"
              class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
              :class="{
                'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.meta_description,
                'border-red-500 bg-red-50 dark:bg-red-900/20': errors.meta_description
              }"
              placeholder="SEO description for search engines..."
              :disabled="isSubmitting"
            ></textarea>
            <div class="flex justify-between items-center mt-1">
              <p v-if="errors.meta_description" class="text-sm text-red-600 dark:text-red-400">
                {{ errors.meta_description }}
              </p>
              <p
                class="text-xs ml-auto"
                :class="getCountColor(metaDescriptionCount, 160)"
              >
                {{ metaDescriptionCount }} / 160
              </p>
            </div>
          </div>

          <!-- Focus Keyword -->
          <div>
            <label for="focus_keyword" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Focus Keyword
            </label>
            <input
              id="focus_keyword"
              v-model="formData.focus_keyword"
              name="focus_keyword"
              type="text"
              class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              placeholder="Primary keyword for this post..."
              :disabled="isSubmitting"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              The main keyword you want this post to rank for
            </p>
          </div>

          <!-- Canonical URL -->
          <div>
            <label for="canonical_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Canonical URL
            </label>
            <input
              id="canonical_url"
              v-model="formData.canonical_url"
              name="canonical_url"
              type="url"
              class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              :class="{
                'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white': !errors.canonical_url,
                'border-red-500 bg-red-50 dark:bg-red-900/20': errors.canonical_url
              }"
              placeholder="https://example.com/original-post"
              :disabled="isSubmitting"
            />
            <p v-if="errors.canonical_url" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.canonical_url }}
            </p>
            <p v-else class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Optional: Specify the original source URL if this content is republished
            </p>
          </div>
        </div>
      </transition>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
      <!-- Cancel Button -->
      <button
        type="button"
        class="px-6 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
        :disabled="isSubmitting"
        @click="handleCancel"
      >
        {{ cancelLabel }}
      </button>

      <div class="flex space-x-3">
        <!-- Save as Draft -->
        <button
          type="submit"
          class="px-6 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="isSubmitting"
          @click.prevent="handleSubmit('draft')"
        >
          <span v-if="isSubmitting && formData.status === 'draft'" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
          </span>
          <span v-else>Save Draft</span>
        </button>

        <!-- Publish -->
        <button
          type="button"
          class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="isSubmitting"
          @click.prevent="handleSubmit('published')"
        >
          <span v-if="isSubmitting && formData.status === 'published'" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Publishing...
          </span>
          <span v-else>{{ submitLabel }}</span>
        </button>
      </div>
    </div>
  </form>
</template>

<style scoped>
/* Additional custom styles if needed */
</style>
