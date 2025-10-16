<script setup>
import { computed } from 'vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseCard from '@/components/base/BaseCard.vue'

const props = defineProps({
  project: {
    type: Object,
    required: true
  }
})

// Check if project has CTA data
const hasCta = computed(() => {
  return props.project.cta_title || props.project.cta_description
})

// Generate contact link
const contactLink = computed(() => {
  if (!props.project.cta_phone_number) return '#'
  
  // Check if it's email or phone
  if (props.project.cta_phone_number.includes('@')) {
    return `mailto:${props.project.cta_phone_number}`
  }
  // Phone
  return `tel:${props.project.cta_phone_number}`
})

// Format contact for display
const contactDisplay = computed(() => {
  if (!props.project.cta_phone_number) return ''
  
  if (props.project.cta_phone_number.includes('@')) {
    return props.project.cta_phone_number
  }
  return props.project.cta_phone_number
})
</script>

<template>
  <section v-if="hasCta" class="section bg-gradient-to-br from-primary-50 to-accent-50 dark:from-primary-900/20 dark:to-accent-900/20">
    <div class="container-custom">
      <div class="max-w-3xl mx-auto">
        <BaseCard class="border-primary-200 dark:border-primary-800">
          <div class="text-center">
            <!-- CTA Icon -->
            <div class="mb-6 flex justify-center">
              <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-100 dark:bg-primary-900/30 rounded-full">
                <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
              </div>
            </div>

            <!-- CTA Title -->
            <h3 v-if="project.cta_title" class="text-2xl md:text-3xl font-display font-bold mb-4 text-neutral-900 dark:text-neutral-100">
              {{ project.cta_title }}
            </h3>

            <!-- CTA Description -->
            <p v-if="project.cta_description" class="text-lg text-neutral-600 dark:text-neutral-300 mb-8">
              {{ project.cta_description }}
            </p>

            <!-- CTA Button & Contact Info -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
              <BaseButton
                v-if="project.cta_phone_number"
                :href="contactLink"
                button-type="primary"
                size="lg"
              >
                {{ project.cta_button_text || 'Get in Touch' }}
              </BaseButton>

              <!-- Contact Info -->
              <div v-if="project.cta_phone_number" class="text-sm text-neutral-600 dark:text-neutral-400">
                <p>Or reach out directly:</p>
                <a
                  :href="contactLink"
                  class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors"
                >
                  {{ contactDisplay }}
                </a>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* No extra styles needed - using Tailwind */
</style>
