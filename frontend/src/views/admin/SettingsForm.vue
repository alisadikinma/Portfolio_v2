<template>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100 mb-2">
        Site Settings
      </h1>
      <p class="text-neutral-600 dark:text-neutral-400">
        Manage your site information, contact details, social media, and SEO settings
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !settingsStore.siteSettings.site_name" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-900 dark:border-neutral-100"></div>
      <p class="mt-4 text-neutral-600 dark:text-neutral-400">Loading settings...</p>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- General Settings Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          General Settings
        </h2>

        <div class="space-y-6">
          <!-- Site Name -->
          <div>
            <label for="site_name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Site Name
            </label>
            <input
              id="site_name"
              v-model="formData.site_name"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Your Portfolio"
            />
          </div>

          <!-- Site Description -->
          <div>
            <label for="site_description" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Site Description
            </label>
            <textarea
              id="site_description"
              v-model="formData.site_description"
              rows="3"
              maxlength="500"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="A brief description of your site (max 500 characters)"
            ></textarea>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
              {{ formData.site_description.length }}/500 characters
            </p>
          </div>

          <!-- Site Logo -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Site Logo
            </label>

            <div class="flex items-center gap-4">
              <!-- Current Logo Preview -->
              <div v-if="currentLogoUrl" class="relative w-32 h-32 rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                <img :src="currentLogoUrl" alt="Site Logo" class="w-full h-full object-contain p-2" />
              </div>

              <!-- Upload Input -->
              <div class="flex-1">
                <input
                  ref="logoInput"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleLogoChange"
                />
                <BaseButton
                  type="button"
                  button-type="secondary"
                  @click="$refs.logoInput.click()"
                >
                  {{ currentLogoUrl ? 'Change Logo' : 'Upload Logo' }}
                </BaseButton>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                  Maximum file size: 5MB. Recommended: PNG with transparent background.
                </p>
              </div>

              <!-- Remove Logo Button -->
              <BaseButton
                v-if="currentLogoUrl"
                type="button"
                button-type="danger"
                @click="removeLogo"
              >
                Remove
              </BaseButton>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Contact Information Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          Contact Information
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Contact Email -->
          <div>
            <label for="contact_email" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Contact Email
            </label>
            <input
              id="contact_email"
              v-model="formData.contact_email"
              type="email"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="contact@example.com"
            />
          </div>

          <!-- Contact Phone -->
          <div>
            <label for="contact_phone" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Contact Phone
            </label>
            <input
              id="contact_phone"
              v-model="formData.contact_phone"
              type="tel"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="+1 (555) 123-4567"
            />
          </div>
        </div>
      </BaseCard>

      <!-- Social Media Links Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Social Media Links
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addSocialMedia"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Link
          </BaseButton>
        </div>

        <div v-if="formData.social_media.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No social media links added yet. Click "Add Link" to get started.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="(link, index) in formData.social_media"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Link #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeSocialMedia(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove link"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Platform -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Platform *
                </label>
                <input
                  v-model="link.platform"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Twitter, LinkedIn"
                  required
                />
              </div>

              <!-- URL -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  URL *
                </label>
                <input
                  v-model="link.url"
                  type="url"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://..."
                  required
                />
              </div>

              <!-- Icon -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Icon Class
                </label>
                <input
                  v-model="link.icon"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., fab fa-twitter"
                />
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- SEO & Meta Tags Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            SEO & Meta Tags
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addMetaTag"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Meta Tag
          </BaseButton>
        </div>

        <div v-if="formData.meta_tags.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No meta tags added yet. Click "Add Meta Tag" to get started.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="(tag, index) in formData.meta_tags"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Meta Tag #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeMetaTag(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove meta tag"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Name *
                </label>
                <input
                  v-model="tag.name"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., description, keywords, author"
                  required
                />
              </div>

              <!-- Content -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Content *
                </label>
                <input
                  v-model="tag.content"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Meta tag content"
                  required
                />
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Analytics Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          Analytics & Tracking
        </h2>

        <div>
          <label for="analytics_code" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Analytics Code
          </label>
          <textarea
            id="analytics_code"
            v-model="formData.analytics_code"
            rows="6"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
            placeholder="<!-- Google Analytics, GTM, or other tracking codes -->"
          ></textarea>
          <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Paste your Google Analytics, Google Tag Manager, or other tracking codes here. They will be added to the site's &lt;head&gt; section.
          </p>
        </div>
      </BaseCard>

      <!-- Form Actions -->
      <div class="flex items-center justify-end gap-4">
        <BaseButton
          type="button"
          button-type="secondary"
          @click="resetForm"
          :disabled="isSubmitting"
        >
          Reset
        </BaseButton>
        <BaseButton
          type="submit"
          :disabled="isSubmitting"
        >
          <span v-if="isSubmitting" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
          </span>
          <span v-else>Save Settings</span>
        </BaseButton>
      </div>
    </form>

    <!-- Error Display -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
      <p class="text-red-800 dark:text-red-200">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'

const settingsStore = useSettingsStore()
const uiStore = useUiStore()

const logoInput = ref(null)
const isSubmitting = ref(false)
const loading = ref(false)
const error = ref(null)
const isLoadingSettings = ref(false) // Guard untuk prevent double load

// Form data
const formData = ref({
  site_name: '',
  site_description: '',
  site_logo: null,
  contact_email: '',
  contact_phone: '',
  social_media: [],
  meta_tags: [],
  analytics_code: ''
})

const logoFile = ref(null)
const logoRemoved = ref(false)

// Current logo URL
const currentLogoUrl = computed(() => {
  if (logoRemoved.value) return null
  if (logoFile.value) {
    return URL.createObjectURL(logoFile.value)
  }
  return formData.value.site_logo
})

// Social media methods
function addSocialMedia() {
  formData.value.social_media.push({
    platform: '',
    url: '',
    icon: ''
  })
}

function removeSocialMedia(index) {
  formData.value.social_media.splice(index, 1)
}

// Meta tags methods
function addMetaTag() {
  formData.value.meta_tags.push({
    name: '',
    content: ''
  })
}

function removeMetaTag(index) {
  formData.value.meta_tags.splice(index, 1)
}

// Logo handling
function handleLogoChange(event) {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      uiStore.showError('File size must not exceed 5MB', 'Upload Error')
      return
    }
    logoFile.value = file
    logoRemoved.value = false
  }
}

function removeLogo() {
  logoFile.value = null
  logoRemoved.value = true
  if (logoInput.value) {
    logoInput.value.value = ''
  }
}

// Form submission
async function handleSubmit() {
  isSubmitting.value = true
  error.value = null

  console.log('🔵 Site settings form submitted - Starting validation...')
  console.log('📋 Current formData:', {
    site_name: formData.value.site_name,
    site_description: formData.value.site_description?.substring(0, 50) + '...',
    contact_email: formData.value.contact_email,
    contact_phone: formData.value.contact_phone,
    social_media: formData.value.social_media,
    meta_tags: formData.value.meta_tags
  })

  try {
    // Validate required fields
    const hasInvalidSocialMedia = formData.value.social_media.some(
      link => !link.platform || !link.url
    )
    const hasInvalidMetaTags = formData.value.meta_tags.some(
      tag => !tag.name || !tag.content
    )

    if (hasInvalidSocialMedia) {
      throw new Error('Please fill in all required social media fields (platform, URL)')
    }
    if (hasInvalidMetaTags) {
      throw new Error('Please fill in all required meta tag fields (name, content)')
    }

    // Prepare FormData
    const data = new FormData()

    // Basic fields
    if (formData.value.site_name) data.append('site_name', formData.value.site_name)
    if (formData.value.site_description) data.append('site_description', formData.value.site_description)
    if (formData.value.contact_email) data.append('contact_email', formData.value.contact_email)
    if (formData.value.contact_phone) data.append('contact_phone', formData.value.contact_phone)
    if (formData.value.analytics_code) data.append('analytics_code', formData.value.analytics_code)

    // Site logo
    if (logoFile.value) {
      data.append('site_logo', logoFile.value)
    }

    // Arrays as JSON strings
    if (formData.value.social_media.length > 0) {
      data.append('social_media', JSON.stringify(formData.value.social_media))
    }
    if (formData.value.meta_tags.length > 0) {
      data.append('meta_tags', JSON.stringify(formData.value.meta_tags))
    }

    console.log('🔵 Sending request to API...', {
      hasLogo: !!logoFile.value,
      socialMediaCount: formData.value.social_media.length,
      metaTagsCount: formData.value.meta_tags.length
    })
    
    // Log FormData contents
    console.log('📦 FormData contents:')
    for (let [key, value] of data.entries()) {
      console.log(`  ${key}:`, typeof value === 'string' ? value.substring(0, 100) : value)
    }

    await settingsStore.updateSiteSettings(data)

    console.log('✅ Settings updated successfully')
    uiStore.showSuccess('Site settings updated successfully', 'Settings Saved')

    // Reset logo upload state
    logoFile.value = null
    logoRemoved.value = false
    if (logoInput.value) {
      logoInput.value.value = ''
    }
  } catch (err) {
    console.error('❌ Failed to update site settings:', err)
    console.error('Error details:', {
      message: err.message,
      response: err.response?.data,
      status: err.response?.status
    })
    error.value = err.message || err.response?.data?.message || 'Failed to update settings. Please try again.'
    uiStore.showError(error.value, 'Update Failed', 0)
  } finally {
    isSubmitting.value = false
  }
}

// Reset form
function resetForm() {
  loadSettings()
  logoFile.value = null
  logoRemoved.value = false
  if (logoInput.value) {
    logoInput.value.value = ''
  }
  error.value = null
}

// Load settings
async function loadSettings() {
  if (isLoadingSettings.value) {
    console.log('⚠️ Already loading settings, skipping...')
    return
  }
  
  isLoadingSettings.value = true
  loading.value = true
  error.value = null
  
  console.log('🔄 Loading site settings from API...')

  try {
    await settingsStore.fetchSiteSettings()
    
    console.log('📥 Settings fetched from store:', settingsStore.siteSettings)

    // Populate form data
    formData.value = {
      site_name: settingsStore.siteSettings.site_name || '',
      site_description: settingsStore.siteSettings.site_description || '',
      site_logo: settingsStore.siteSettings.site_logo || null,
      contact_email: settingsStore.siteSettings.contact_email || '',
      contact_phone: settingsStore.siteSettings.contact_phone || '',
      social_media: JSON.parse(JSON.stringify(settingsStore.siteSettings.social_media || [])),
      meta_tags: JSON.parse(JSON.stringify(settingsStore.siteSettings.meta_tags || [])),
      analytics_code: settingsStore.siteSettings.analytics_code || ''
    }
    
    console.log('✅ Form data populated:', {
      site_name: formData.value.site_name,
      contact_email: formData.value.contact_email,
      socialMediaCount: formData.value.social_media.length,
      metaTagsCount: formData.value.meta_tags.length
    })
  } catch (err) {
    console.error('❌ Failed to load settings:', err)
    error.value = 'Failed to load settings. Please refresh the page.'
    uiStore.showError(error.value, 'Load Failed')
  } finally {
    loading.value = false
    isLoadingSettings.value = false
  }
}

// Load on mount
onMounted(() => {
  loadSettings()
})
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
