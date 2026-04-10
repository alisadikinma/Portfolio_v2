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

// Translation tabs
const activeTranslationTab = ref('en')
const EMPTY_TRANSLATION = () => ({
  title: '', slug: '', excerpt: '', content: '',
  meta_title: '', meta_description: '', meta_keywords: '',
  canonical_url: '', ai_summary: '', id: null
})
const translationData = ref({
  en: EMPTY_TRANSLATION(),
  id: EMPTY_TRANSLATION()
})

// Form data (shared fields only)
const formData = ref({
  title: '',
  slug: '',
  excerpt: '',
  content: '',
  featured_image: null,
  category_id: null,
  status: 'draft',
  published_at: null,
  // SEO fields (kept for backwards compatibility)
  meta_title: '',
  meta_description: '',
  focus_keyword: '',
  canonical_url: '',
  translationsData: null
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

// Sync formData fields to active translation tab
watch(() => formData.value.title, (v) => {
  translationData.value[activeTranslationTab.value].title = v
  if (isAutoSlug.value) {
    const slug = generateSlug(v)
    formData.value.slug = slug
    translationData.value[activeTranslationTab.value].slug = slug
  }
})
watch(() => formData.value.slug, (v) => { translationData.value[activeTranslationTab.value].slug = v })
watch(() => formData.value.excerpt, (v) => { translationData.value[activeTranslationTab.value].excerpt = v })
watch(() => formData.value.content, (v) => { translationData.value[activeTranslationTab.value].content = v })
watch(() => formData.value.meta_title, (v) => { translationData.value[activeTranslationTab.value].meta_title = v })
watch(() => formData.value.meta_description, (v) => { translationData.value[activeTranslationTab.value].meta_description = v })
watch(() => formData.value.focus_keyword, (v) => { translationData.value[activeTranslationTab.value].meta_keywords = v })
watch(() => formData.value.canonical_url, (v) => { translationData.value[activeTranslationTab.value].canonical_url = v })

// When switching tabs, load that tab's data into formData
function switchTab(lang) {
  // Save current tab data first
  const current = activeTranslationTab.value
  translationData.value[current] = {
    ...translationData.value[current],
    title: formData.value.title,
    slug: formData.value.slug,
    excerpt: formData.value.excerpt,
    content: formData.value.content,
    meta_title: formData.value.meta_title,
    meta_description: formData.value.meta_description,
    meta_keywords: formData.value.focus_keyword,
    canonical_url: formData.value.canonical_url
  }

  // Switch tab
  activeTranslationTab.value = lang

  // Load new tab data into formData
  const data = translationData.value[lang]
  formData.value.title = data.title || ''
  formData.value.slug = data.slug || ''
  formData.value.excerpt = data.excerpt || ''
  formData.value.content = data.content || ''
  formData.value.meta_title = data.meta_title || ''
  formData.value.meta_description = data.meta_description || ''
  formData.value.focus_keyword = data.meta_keywords || ''
  formData.value.canonical_url = data.canonical_url || ''
  isAutoSlug.value = false
}

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
    // Global SEO fields (on posts table)
    meta_keywords: translationData.value.en.meta_keywords || null,
    og_image: null,
    schema_markup: null,
    faq_schema: null,
    seo_score: 0,
    index_follow: true,
    // Per-language translations — save current tab first
    translations: buildTranslationsPayload()
  }

  function buildTranslationsPayload() {
    // Save current tab data
    const current = activeTranslationTab.value
    translationData.value[current] = {
      ...translationData.value[current],
      title: formData.value.title,
      slug: formData.value.slug,
      excerpt: formData.value.excerpt,
      content: formData.value.content,
      meta_title: formData.value.meta_title,
      meta_description: formData.value.meta_description,
      meta_keywords: formData.value.focus_keyword,
      canonical_url: formData.value.canonical_url
    }

    const translations = []
    for (const lang of ['en', 'id']) {
      const t = translationData.value[lang]
      // Only include if there's actual content
      if (!t.title && !t.content) continue
      translations.push({
        id: t.id || undefined,
        language: lang,
        title: t.title,
        slug: t.slug || generateSlug(t.title),
        excerpt: t.excerpt || null,
        content: t.content,
        meta_title: t.meta_title || null,
        meta_description: t.meta_description || null,
        meta_keywords: t.meta_keywords || null,
        og_title: t.meta_title || t.title,
        og_description: t.meta_description || t.excerpt || null,
        canonical_url: t.canonical_url || null,
        ai_summary: t.ai_summary || null
      })
    }
    return translations
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

  // Populate translation tabs from existing translations
  const translations = post.translations || []
  for (const lang of ['en', 'id']) {
    const trans = translations.find(t => t.language === lang)
    if (trans) {
      translationData.value[lang] = {
        id: trans.id || null,
        title: trans.title || '',
        slug: trans.slug || '',
        excerpt: trans.excerpt || '',
        content: trans.content || '',
        meta_title: trans.meta_title || '',
        meta_description: trans.meta_description || '',
        meta_keywords: trans.meta_keywords || '',
        canonical_url: trans.canonical_url || '',
        ai_summary: trans.ai_summary || ''
      }
    } else {
      translationData.value[lang] = EMPTY_TRANSLATION()
    }
  }

  // Default to EN tab and load its data into formData
  activeTranslationTab.value = 'en'
  const enData = translationData.value.en

  formData.value = {
    title: enData.title || post.title || '',
    slug: enData.slug || post.slug || '',
    excerpt: enData.excerpt || post.excerpt || '',
    content: enData.content || post.content || '',
    featured_image: post.featured_image || null,
    category_id: post.category_id || null,
    status: post.published ? 'published' : 'draft',
    published_at: post.published_at || null,
    meta_title: enData.meta_title || post.seo?.meta_title || '',
    meta_description: enData.meta_description || post.seo?.meta_description || '',
    focus_keyword: enData.meta_keywords || '',
    canonical_url: enData.canonical_url || post.seo?.canonical_url || '',
    translationsData: translations
  }

  // Set uploaded image for preview
  if (post.featured_image && typeof post.featured_image === 'string') {
    uploadedImageFile.value = post.featured_image
  }

  isAutoSlug.value = false
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
  <form class="blog-post-form" @submit.prevent="handleSubmit('draft')">
    <!-- Language Tabs — sticky top bar -->
    <div class="flex items-center gap-2 border-b border-gray-700 pb-3 mb-6">
      <button
        v-for="lang in [{code:'en',label:'EN',flag:'🇺🇸'},{code:'id',label:'ID',flag:'🇮🇩'}]"
        :key="lang.code"
        type="button"
        @click="switchTab(lang.code)"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold tracking-wide transition-all duration-200"
        :class="[
          activeTranslationTab === lang.code
            ? 'bg-blue-600 text-white'
            : 'bg-gray-800 text-gray-400 hover:bg-gray-700'
        ]"
      >
        <span>{{ lang.flag }}</span>
        {{ lang.label }}
        <span v-if="translationData[lang.code]?.title" class="w-1.5 h-1.5 rounded-full" :class="activeTranslationTab === lang.code ? 'bg-white/60' : 'bg-green-500'"></span>
      </button>
      <span v-if="activeTranslationTab === 'id'" class="ml-auto text-[10px] text-amber-500">Keep tech terms in English</span>
    </div>

    <!-- 2-COLUMN LAYOUT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- LEFT COLUMN: Content (8 cols) -->
      <div class="lg:col-span-8 space-y-5">
        <!-- Title -->
        <div>
          <label for="title" class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Title *</label>
          <input id="title" v-model="formData.title" name="title" type="text" :disabled="isSubmitting"
            class="w-full px-3 py-2 text-sm border rounded-md bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors placeholder-gray-600"
            :class="{ 'border-red-500': errors.title }"
            placeholder="Article title..." />
          <div class="flex justify-between mt-1">
            <p v-if="errors.title" class="text-xs text-red-400">{{ errors.title }}</p>
            <p class="text-[10px] ml-auto" :class="getCountColor(titleCount, 255)">{{ titleCount }}/255</p>
          </div>
        </div>

        <!-- Slug -->
        <div>
          <label for="slug" class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">
            Slug *
            <span v-if="isAutoSlug" class="text-blue-400 normal-case tracking-normal font-normal ml-1">(auto)</span>
          </label>
          <input id="slug" v-model="formData.slug" name="slug" type="text" :disabled="isSubmitting" @input="handleSlugInput"
            class="w-full px-3 py-2 text-xs font-mono border rounded-md bg-gray-900 text-gray-300 border-gray-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors placeholder-gray-600"
            :class="{ 'border-red-500': errors.slug }"
            placeholder="article-slug-here" />
        </div>

        <!-- Excerpt -->
        <div>
          <label for="excerpt" class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Excerpt</label>
          <textarea id="excerpt" v-model="formData.excerpt" name="excerpt" rows="2" :disabled="isSubmitting"
            class="w-full px-3 py-2 text-sm border rounded-md bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none placeholder-gray-600"
            :class="{ 'border-red-500': errors.excerpt }"
            placeholder="Brief summary..."></textarea>
          <p class="text-[10px] text-right mt-0.5" :class="getCountColor(excerptCount, 500)">{{ excerptCount }}/500</p>
        </div>

        <!-- Content -->
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Content *</label>
          <RichTextEditor v-model="formData.content" placeholder="Start writing..." :disabled="isSubmitting" min-height="400px" />
          <p v-if="errors.content" class="mt-1 text-xs text-red-400">{{ errors.content }}</p>
        </div>
      </div>

      <!-- RIGHT COLUMN: Sidebar (4 cols) -->
      <div class="lg:col-span-4 space-y-5">
        <!-- Publish Actions Card -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Publish</h3>
          <div class="flex gap-2">
            <button type="submit" :disabled="isSubmitting" @click.prevent="handleSubmit('draft')"
              class="flex-1 px-3 py-2 text-xs font-medium border border-gray-600 text-gray-300 rounded-md hover:bg-gray-800 transition-colors disabled:opacity-50">
              <span v-if="isSubmitting && formData.status === 'draft'">Saving...</span>
              <span v-else>Save Draft</span>
            </button>
            <button type="button" :disabled="isSubmitting" @click.prevent="handleSubmit('published')"
              class="flex-1 px-3 py-2 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors disabled:opacity-50">
              <span v-if="isSubmitting && formData.status === 'published'">Publishing...</span>
              <span v-else>{{ submitLabel }}</span>
            </button>
          </div>
          <button type="button" :disabled="isSubmitting" @click="handleCancel"
            class="w-full mt-2 px-3 py-1.5 text-[11px] text-gray-500 hover:text-gray-300 transition-colors text-center">
            {{ cancelLabel }}
          </button>
        </div>

        <!-- Category -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Category</h3>
          <CategorySelect v-model="formData.category_id" placeholder="Select category" :required="true" :error="errors.category_id" :disabled="isSubmitting" />
        </div>

        <!-- Featured Image -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Featured Image</h3>
          <ImageUploader :model-value="uploadedImageFile" description="PNG, JPG, WEBP (max 5MB)" aspect-ratio="16:9" :disabled="isSubmitting" @update:model-value="handleImageChange" />
        </div>

        <!-- SEO Settings -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <button type="button" class="flex items-center justify-between w-full" @click="showAdvancedSeo = !showAdvancedSeo">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">SEO Settings</h3>
            <svg class="w-4 h-4 text-gray-500 transition-transform" :class="{ 'rotate-180': showAdvancedSeo }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="showAdvancedSeo" class="mt-3 space-y-3">
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Meta Title</label>
              <input v-model="formData.meta_title" type="text" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                placeholder="SEO title..." />
              <p class="text-[10px] text-right mt-0.5" :class="getCountColor(metaTitleCount, 60)">{{ metaTitleCount }}/60</p>
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Meta Description</label>
              <textarea v-model="formData.meta_description" rows="2" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 resize-none placeholder-gray-600"
                placeholder="SEO description..."></textarea>
              <p class="text-[10px] text-right mt-0.5" :class="getCountColor(metaDescriptionCount, 160)">{{ metaDescriptionCount }}/160</p>
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Focus Keyword</label>
              <input v-model="formData.focus_keyword" type="text" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                placeholder="Primary keyword..." />
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Canonical URL</label>
              <input v-model="formData.canonical_url" type="url" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                placeholder="https://..." />
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</template>

<style scoped>
/* Remove label from CategorySelect since we have our own header */
:deep(.category-select > label) {
  display: none;
}
</style>
