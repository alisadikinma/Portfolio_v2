<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            Get in <span class="text-gradient">Touch</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            Have a project in mind? Let's work together to bring your ideas to life
          </p>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-12">
          <!-- Contact Form -->
          <div>
            <h2 class="text-3xl font-display font-bold mb-6">Send a Message</h2>
            <form @submit.prevent="handleSubmit" class="space-y-6">
              <!-- Name -->
              <div>
                <label for="name" class="block text-sm font-medium mb-2">
                  Name <span class="text-red-500">*</span>
                </label>
                <input
                  id="name"
                  v-model="form.name"
                  @input="clearError('name')"
                  type="text"
                  required
                  :class="[
                    'w-full px-4 py-3 rounded-lg border bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2',
                    formErrors.name ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-primary-500'
                  ]"
                  placeholder="Your name"
                />
                <p v-if="formErrors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ formErrors.name }}
                </p>
              </div>

              <!-- Email -->
              <div>
                <label for="email" class="block text-sm font-medium mb-2">
                  Email <span class="text-red-500">*</span>
                </label>
                <input
                  id="email"
                  v-model="form.email"
                  @input="clearError('email')"
                  type="email"
                  required
                  :class="[
                    'w-full px-4 py-3 rounded-lg border bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2',
                    formErrors.email ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-primary-500'
                  ]"
                  placeholder="your.email@example.com"
                />
                <p v-if="formErrors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ formErrors.email }}
                </p>
              </div>

              <!-- WhatsApp Number -->
              <div>
                <label for="whatsapp" class="block text-sm font-medium mb-2">
                  WhatsApp Number <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                  </div>
                  <input
                    id="whatsapp"
                    v-model="form.whatsapp_number"
                    @input="clearError('whatsapp_number')"
                    type="tel"
                    required
                    :class="[
                      'w-full pl-12 pr-4 py-3 rounded-lg border bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2',
                      formErrors.whatsapp_number ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-primary-500'
                    ]"
                    placeholder="+62812345678 or 08123456789"
                  />
                </div>
                <p v-if="formErrors.whatsapp_number" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ formErrors.whatsapp_number }}
                </p>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                  We'll contact you via WhatsApp for faster response
                </p>
              </div>

              <!-- Subject -->
              <div>
                <label for="subject" class="block text-sm font-medium mb-2">
                  Subject <span class="text-red-500">*</span>
                </label>
                <input
                  id="subject"
                  v-model="form.subject"
                  @input="clearError('subject')"
                  type="text"
                  required
                  :class="[
                    'w-full px-4 py-3 rounded-lg border bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2',
                    formErrors.subject ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-primary-500'
                  ]"
                  placeholder="What's this about?"
                />
                <p v-if="formErrors.subject" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ formErrors.subject }}
                </p>
              </div>

              <!-- Message -->
              <div>
                <label for="message" class="block text-sm font-medium mb-2">
                  Message <span class="text-red-500">*</span>
                </label>
                <textarea
                  id="message"
                  v-model="form.message"
                  @input="clearError('message')"
                  required
                  rows="6"
                  :class="[
                    'w-full px-4 py-3 rounded-lg border bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-2 resize-none',
                    formErrors.message ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-primary-500'
                  ]"
                  placeholder="Tell me about your project..."
                ></textarea>
                <p v-if="formErrors.message" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ formErrors.message }}
                </p>
              </div>

              <!-- hCaptcha -->
              <div>
                <div ref="captchaContainer" class="flex justify-center"></div>
                <p v-if="formErrors.captcha" class="mt-2 text-sm text-red-600 dark:text-red-400 text-center">
                  {{ formErrors.captcha }}
                </p>
              </div>

              <!-- Submit Button -->
              <BaseButton
                type="submit"
                variant="primary"
                size="lg"
                :disabled="isSubmitting"
                class="w-full"
              >
                {{ isSubmitting ? 'Sending...' : 'Send Message' }}
              </BaseButton>
            </form>
          </div>

          <!-- Contact Info -->
          <div>
            <h2 class="text-3xl font-display font-bold mb-6">Contact Information</h2>
            <div class="space-y-6 mb-8">
              <div v-if="siteSettings?.contact_phone" class="flex items-start gap-4">
                <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400">
                  <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold mb-1">WhatsApp</h3>
                  <a 
                    :href="whatsappLink" 
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-neutral-600 dark:text-neutral-400 hover:text-green-600 dark:hover:text-green-400 transition-colors flex items-center gap-2"
                  >
                    {{ siteSettings.contact_phone }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                  </a>
                </div>
              </div>

              <div v-if="siteSettings?.contact_email" class="flex items-start gap-4">
                <div class="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold mb-1">Email</h3>
                  <a :href="`mailto:${siteSettings.contact_email}`" class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    {{ siteSettings.contact_email }}
                  </a>
                </div>
              </div>

              <div v-if="siteSettings?.phone" class="flex items-start gap-4">
                <div class="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold mb-1">Phone</h3>
                  <a :href="`tel:${siteSettings.phone}`" class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    {{ siteSettings.phone }}
                  </a>
                </div>
              </div>

              <div v-if="siteSettings?.address" class="flex items-start gap-4">
                <div class="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold mb-1">Location</h3>
                  <p class="text-neutral-600 dark:text-neutral-400">{{ siteSettings.address }}</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <div class="p-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold mb-1">Response Time</h3>
                  <p class="text-neutral-600 dark:text-neutral-400">Within 24 hours</p>
                </div>
              </div>
            </div>

            <!-- Social Links -->
            <div v-if="siteSettings?.social_links && siteSettings.social_links.length > 0" class="pt-8 border-t border-neutral-200 dark:border-neutral-700">
              <h3 class="font-semibold mb-4">Follow Me</h3>
              <div class="flex gap-4 flex-wrap">
                <a
                  v-for="(link, index) in siteSettings.social_links"
                  :key="index"
                  :href="link.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="p-3 rounded-lg bg-neutral-100 dark:bg-neutral-700 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                  :aria-label="link.platform"
                >
                  <svg v-if="link.platform === 'github'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else-if="link.platform === 'linkedin'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                  </svg>
                  <svg v-else-if="link.platform === 'twitter'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                  </svg>
                  <svg v-else class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm0 22c-5.514 0-10-4.486-10-10s4.486-10 10-10 10 4.486 10 10-4.486 10-10 10zm1-16h-2v7h7v-2h-5z"/>
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>
    <!-- Map Section (Placeholder) -->
    <!--section class="section bg-neutral-50 dark:bg-neutral-900">
      <div class="container-custom">
        <div class="h-96 bg-neutral-200 dark:bg-neutral-700 rounded-2xl flex items-center justify-center">
          <p class="text-neutral-500 dark:text-neutral-400">Map Placeholder</p>
        </div>
      </div>
    </section-->
    
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useUIStore } from '@/stores/ui'
import { BaseButton } from '@/components/base'
import api from '@/services/api'

const uiStore = useUIStore()

const form = ref({
  name: '',
  email: '',
  whatsapp_number: '',
  subject: '',
  message: ''
})

const formErrors = ref({})
const isSubmitting = ref(false)
const siteSettings = ref(null)
const loadingSettings = ref(false)
const captchaContainer = ref(null)
const captchaToken = ref(null)
let hcaptchaWidgetId = null

// hCaptcha Site Key (Test Key - ganti dengan site key production nanti)
const HCAPTCHA_SITE_KEY = '10000000-ffff-ffff-ffff-000000000001' // Test key dari hCaptcha

// WhatsApp link computed
const whatsappLink = computed(() => {
  if (!siteSettings.value?.contact_phone) return '#'
  const phone = siteSettings.value.contact_phone.replace(/[^0-9]/g, '')
  const message = encodeURIComponent('Hi! I found your portfolio and would like to discuss a project.')
  return `https://wa.me/${phone}?text=${message}`
})

// Validation rules
const validateForm = () => {
  const errors = {}

  // Name validation
  if (!form.value.name || form.value.name.trim().length < 2) {
    errors.name = 'Name must be at least 2 characters'
  }

  // Email validation (RFC 5322 compliant)
  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/
  if (!form.value.email) {
    errors.email = 'Email is required'
  } else if (!emailRegex.test(form.value.email)) {
    errors.email = 'Please enter a valid email address (e.g., name@example.com)'
  } else if (form.value.email.length > 254) {
    errors.email = 'Email address is too long'
  }

  // WhatsApp Number validation
  if (!form.value.whatsapp_number) {
    errors.whatsapp_number = 'WhatsApp number is required'
  } else {
    // Remove all non-digit characters for validation
    const cleaned = form.value.whatsapp_number.replace(/\D/g, '')
    
    // Check if it's a valid phone number (8-15 digits)
    if (cleaned.length < 8) {
      errors.whatsapp_number = 'Phone number is too short (min 8 digits)'
    } else if (cleaned.length > 15) {
      errors.whatsapp_number = 'Phone number is too long (max 15 digits)'
    }
    
    // Check for Indonesian format if starts with 0 or 62
    if (cleaned.startsWith('0')) {
      if (cleaned.length < 10 || cleaned.length > 13) {
        errors.whatsapp_number = 'Invalid Indonesian phone format (e.g., 08123456789)'
      }
    } else if (cleaned.startsWith('62')) {
      if (cleaned.length < 11 || cleaned.length > 14) {
        errors.whatsapp_number = 'Invalid Indonesian phone format (e.g., +628123456789)'
      }
    }
  }

  // Subject validation
  if (!form.value.subject || form.value.subject.trim().length < 3) {
    errors.subject = 'Subject must be at least 3 characters'
  } else if (form.value.subject.trim().length > 255) {
    errors.subject = 'Subject is too long (max 255 characters)'
  }

  // Message validation
  if (!form.value.message || form.value.message.trim().length < 10) {
    errors.message = 'Message must be at least 10 characters'
  } else if (form.value.message.trim().length > 5000) {
    errors.message = 'Message is too long (max 5000 characters)'
  }

  // Captcha validation
  if (!captchaToken.value) {
    errors.captcha = 'Please complete the captcha verification'
  }

  return errors
}

const handleSubmit = async () => {
  // Validate form
  formErrors.value = validateForm()

  // Stop if there are errors
  if (Object.keys(formErrors.value).length > 0) {
    uiStore.showError(
      'Please fix the errors in the form',
      'Validation Error'
    )
    return
  }

  isSubmitting.value = true

  try {
    // Sanitize input (basic XSS prevention)
    const sanitizedForm = {
      name: form.value.name.trim(),
      email: form.value.email.trim().toLowerCase(),
      whatsapp_number: form.value.whatsapp_number.trim(),
      subject: form.value.subject.trim(),
      message: form.value.message.trim(),
      captcha_token: captchaToken.value // Send captcha token
    }

    // Actual API call
    const response = await api.post('/contact', sanitizedForm)

    if (response.data.message || response.data.data) {
      // Show success message with better visibility
      uiStore.showSuccess(
        'Thank you for reaching out! We\'ll contact you via WhatsApp within 24 hours.',
        '✅ Message Sent Successfully!',
        6000 // Show for 6 seconds
      )

      // Reset form
      form.value = {
        name: '',
        email: '',
        whatsapp_number: '',
        subject: '',
        message: ''
      }
      formErrors.value = {}
      
      // Reset captcha
      captchaToken.value = null
      if (window.hcaptcha && hcaptchaWidgetId !== null) {
        window.hcaptcha.reset(hcaptchaWidgetId)
      }
    }
  } catch (error) {
    // Reset captcha on error
    captchaToken.value = null
    if (window.hcaptcha && hcaptchaWidgetId !== null) {
      window.hcaptcha.reset(hcaptchaWidgetId)
    }
    
    uiStore.showError(
      error.response?.data?.message || 'Failed to send message. Please try again.',
      'Error'
    )
  } finally {
    isSubmitting.value = false
  }
}

// Load hCaptcha script
const loadHCaptcha = () => {
  return new Promise((resolve, reject) => {
    if (window.hcaptcha) {
      resolve()
      return
    }

    const script = document.createElement('script')
    script.src = 'https://js.hcaptcha.com/1/api.js'
    script.async = true
    script.defer = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Failed to load hCaptcha'))
    document.head.appendChild(script)
  })
}

// Initialize hCaptcha widget
const initHCaptcha = () => {
  if (!captchaContainer.value || !window.hcaptcha) return

  try {
    hcaptchaWidgetId = window.hcaptcha.render(captchaContainer.value, {
      sitekey: HCAPTCHA_SITE_KEY,
      callback: (token) => {
        captchaToken.value = token
        clearError('captcha')
      },
      'expired-callback': () => {
        captchaToken.value = null
      },
      'error-callback': () => {
        captchaToken.value = null
      }
    })
  } catch (error) {
    console.error('Failed to initialize hCaptcha:', error)
  }
}

// Clear error when user types
const clearError = (field) => {
  if (formErrors.value[field]) {
    delete formErrors.value[field]
  }
}

// Fetch site settings
const fetchSiteSettings = async () => {
  loadingSettings.value = true
  try {
    const response = await api.get('/settings/site')
    if (response.data.success) {
      siteSettings.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch site settings:', error)
  } finally {
    loadingSettings.value = false
  }
}

// Load settings on mount
onMounted(async () => {
  await fetchSiteSettings()
  
  // Load and initialize hCaptcha
  try {
    await loadHCaptcha()
    // Wait a bit for DOM to be ready
    setTimeout(() => {
      initHCaptcha()
    }, 100)
  } catch (error) {
    console.error('Failed to load hCaptcha:', error)
  }
})

// Cleanup on unmount
onUnmounted(() => {
  // Remove hCaptcha widget if exists
  if (window.hcaptcha && hcaptchaWidgetId !== null) {
    try {
      window.hcaptcha.remove(hcaptchaWidgetId)
    } catch (error) {
      // Ignore errors
    }
  }
})
</script>
