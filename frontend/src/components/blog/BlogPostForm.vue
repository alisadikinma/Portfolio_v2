<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import RichTextEditor from './RichTextEditor.vue'
import ImageUploader from './ImageUploader.vue'
import CategorySelect from './CategorySelect.vue'
import api from '@/services/api'

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

// Related posts
const allPosts = ref([])
const selectedRelatedPosts = ref([])
const relatedSearch = ref('')
const showRelatedDropdown = ref(false)

const filteredAvailablePosts = computed(() => {
  const selectedIds = new Set(selectedRelatedPosts.value.map(p => p.id))
  const currentId = props.post?.id
  let available = allPosts.value.filter(p => p.id !== currentId && !selectedIds.has(p.id))

  if (relatedSearch.value.trim()) {
    const q = relatedSearch.value.toLowerCase()
    available = available.filter(p => p.title?.toLowerCase().includes(q))
  }

  return available.slice(0, 8)
})

const addRelatedPost = (post) => {
  if (selectedRelatedPosts.value.length >= 5) return
  selectedRelatedPosts.value.push({ id: post.id, title: post.title, slug: post.slug, category: post.category })
  relatedSearch.value = ''
  showRelatedDropdown.value = false
}

const removeRelatedPost = (index) => {
  selectedRelatedPosts.value.splice(index, 1)
}

const fetchAllPosts = async () => {
  try {
    const res = await api.get('/posts', { params: { per_page: 50 } })
    allPosts.value = res.data?.data || []
  } catch { /* non-critical */ }
}
const uploadedImageFile = ref(null) // Store the File object separately

// Validation rules
const validationRules = {
  title: { required: true, minLength: 3, maxLength: 255 },
  slug: { required: true, minLength: 3, maxLength: 255, pattern: /^[a-z0-9]+(?:-[a-z0-9]+)*$/ },
  excerpt: { maxLength: 500 },
  content: { required: true, minLength: 100 },
  category_id: { required: true },
  meta_title: { maxLength: 255 },
  meta_description: { maxLength: 500 },
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
    translations: buildTranslationsPayload(),
    related_post_ids: selectedRelatedPosts.value.map(p => p.id)
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
onMounted(async () => {
  if (props.post) {
    loadPostData(props.post)
  }
  await fetchAllPosts()

  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-related-search]')) {
      showRelatedDropdown.value = false
    }
  })
})
</script>

<template>
  <form class="blog-post-form" @submit.prevent="handleSubmit('draft')">
    <!-- Language Tabs — flags only -->
    <div class="flex items-center gap-1.5 border-b border-gray-700 pb-3 mb-6">
      <button
        v-for="lang in [{code:'en',flag:'🇺🇸'},{code:'id',flag:'🇮🇩'}]"
        :key="lang.code"
        type="button"
        @click="switchTab(lang.code)"
        class="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-base transition-all duration-200"
        :class="[
          activeTranslationTab === lang.code
            ? 'bg-blue-600 ring-2 ring-blue-400/50 scale-110'
            : 'bg-gray-800 opacity-50 hover:opacity-80 hover:bg-gray-700'
        ]"
        :title="lang.code === 'en' ? 'English' : 'Indonesian'"
      >
        <span>{{ lang.flag }}</span>
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

        <!-- Content (collapsible — drag to resize) -->
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5 uppercase tracking-wider">Content *</label>
          <div class="overflow-hidden rounded-md border border-gray-700" style="resize: vertical; min-height: 200px; max-height: 80vh; height: 300px;">
            <RichTextEditor v-model="formData.content" placeholder="Start writing..." :disabled="isSubmitting" min-height="100%" />
          </div>
          <p class="text-[10px] text-gray-600 mt-1">Drag bottom edge to resize</p>
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

        <!-- Related Posts -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Related Posts</h3>
            <span class="text-[10px] text-gray-600">{{ selectedRelatedPosts.length }}/5</span>
          </div>

          <!-- Search to add -->
          <div class="relative mb-3">
            <input
              v-model="relatedSearch"
              type="text"
              placeholder="Search posts to add..."
              class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
              @focus="showRelatedDropdown = true"
            />
            <!-- Dropdown -->
            <div v-if="showRelatedDropdown && filteredAvailablePosts.length" class="absolute z-10 top-full left-0 right-0 mt-1 max-h-40 overflow-y-auto rounded-md border border-gray-700 bg-gray-900 shadow-xl">
              <button
                v-for="p in filteredAvailablePosts"
                :key="p.id"
                type="button"
                class="w-full text-left px-3 py-2 text-xs text-gray-300 hover:bg-gray-800 border-b border-gray-800 last:border-0 transition-colors"
                @mousedown.prevent="addRelatedPost(p)"
              >
                <span class="line-clamp-1">{{ p.title }}</span>
                <span v-if="p.category?.name" class="text-[10px] text-gray-600 ml-1">· {{ p.category.name }}</span>
              </button>
            </div>
          </div>

          <!-- Selected related posts -->
          <div v-if="selectedRelatedPosts.length" class="space-y-1.5">
            <div
              v-for="(rp, idx) in selectedRelatedPosts"
              :key="rp.id"
              class="flex items-center gap-2 px-2.5 py-1.5 rounded bg-gray-800 border border-gray-700"
            >
              <span class="text-[10px] text-gray-500 font-mono w-4">{{ idx + 1 }}</span>
              <span class="text-xs text-gray-300 flex-1 line-clamp-1">{{ rp.title }}</span>
              <button type="button" @click="removeRelatedPost(idx)" class="text-gray-600 hover:text-red-400 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
          <p v-else class="text-[10px] text-gray-600 text-center py-2">No related posts selected</p>
        </div>

        <!-- SEO Settings (per language) -->
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
          <button type="button" class="flex items-center justify-between w-full" @click="showAdvancedSeo = !showAdvancedSeo">
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
              SEO Settings
              <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-800 text-blue-400 normal-case">{{ activeTranslationTab.toUpperCase() }}</span>
            </h3>
            <svg class="w-4 h-4 text-gray-500 transition-transform" :class="{ 'rotate-180': showAdvancedSeo }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-show="showAdvancedSeo" class="mt-3 space-y-3">
            <p class="text-[10px] text-gray-600">SEO fields are per-language. Switch EN/ID tab to edit each.</p>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Meta Title <span class="text-gray-600">(recommended: 50-60 chars)</span></label>
              <input v-model="formData.meta_title" type="text" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                placeholder="SEO title for search engines..." />
              <p class="text-[10px] text-right mt-0.5" :class="metaTitleCount > 60 ? 'text-amber-400' : metaTitleCount > 50 ? 'text-green-400' : 'text-gray-500'">{{ metaTitleCount }}/60 {{ metaTitleCount > 60 ? '(may be truncated in search)' : '' }}</p>
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Meta Description <span class="text-gray-600">(recommended: 120-160 chars)</span></label>
              <textarea v-model="formData.meta_description" rows="4" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 resize-y placeholder-gray-600"
                placeholder="Compelling description for search results. Include primary keyword naturally..."></textarea>
              <p class="text-[10px] text-right mt-0.5" :class="metaDescriptionCount > 160 ? 'text-amber-400' : metaDescriptionCount > 120 ? 'text-green-400' : 'text-gray-500'">{{ metaDescriptionCount }}/160</p>
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Focus Keywords <span class="text-gray-600">(comma separated)</span></label>
              <input v-model="formData.focus_keyword" type="text" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                :placeholder="activeTranslationTab === 'id' ? 'kata kunci utama, kata kunci sekunder' : 'primary keyword, secondary keyword'" />
            </div>
            <div>
              <label class="block text-[11px] text-gray-500 mb-1">Canonical URL</label>
              <input v-model="formData.canonical_url" type="url" :disabled="isSubmitting"
                class="w-full px-2.5 py-1.5 text-xs border rounded bg-gray-900 text-white border-gray-700 focus:ring-1 focus:ring-blue-500 placeholder-gray-600"
                placeholder="https://alisadikinma.com/en/blog/..." />
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
