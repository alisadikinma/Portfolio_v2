<script setup>
import { ref } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'
import NewsletterModal from '@/components/blog/NewsletterModal.vue'

defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['dismiss', 'subscribed'])

const { markDismissed, markSubscribed } = useNewsletter()

const showModal = ref(false)
const collapsed = ref(false)
const successKind = ref('success') // 'success' | 'duplicate'

function openModal() {
  showModal.value = true
}

function onModalDismiss() {
  showModal.value = false
}

function onModalSubscribed(submittedEmail) {
  // The modal already calls markSubscribed via its own subscribe() chain.
  // We just collapse the bar to a small success state, then fully dismiss.
  if (submittedEmail) {
    markSubscribed(submittedEmail)
  }
  successKind.value = 'success'
  collapsed.value = true
  emit('subscribed', submittedEmail)
  setTimeout(() => {
    showModal.value = false
    emit('dismiss')
  }, 2500)
}

function handleDismiss() {
  markDismissed()
  emit('dismiss')
}
</script>

<template>
  <Transition name="slide-up">
    <div
      v-if="show"
      class="fixed bottom-0 left-0 right-0 z-40 border-t border-white/5 bg-bg-deep/90 backdrop-blur-xl"
      data-testid="newsletter-footer-bar"
      role="region"
      aria-label="Newsletter subscription"
    >
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3">
        <div v-if="!collapsed" class="flex items-center gap-3 sm:gap-6">
          <div class="hidden sm:flex items-center gap-3 flex-shrink-0">
            <span class="text-lg" aria-hidden="true">✨</span>
            <p class="text-fg-primary text-sm font-medium">
              <span class="hidden md:inline">Liked what you read? </span>Get a new essay every Friday.
            </p>
          </div>

          <p class="sm:hidden text-fg-primary text-xs font-medium flex-1">Get weekly essays</p>

          <button
            type="button"
            class="btn-gold text-xs sm:text-sm px-4 py-2 whitespace-nowrap ml-auto sm:ml-0 sm:flex-1 sm:max-w-[180px]"
            @click="openModal"
          >
            Subscribe →
          </button>

          <button
            type="button"
            class="text-fg-dim hover:text-fg-primary transition-colors flex-shrink-0 p-1"
            aria-label="Dismiss newsletter bar"
            @click="handleDismiss"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div v-else class="flex items-center justify-center gap-3 py-1">
          <svg
            class="w-5 h-5"
            :class="successKind === 'success' ? 'text-accent-gold' : 'text-accent-cyan'"
            fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          <p class="text-fg-primary text-sm font-medium">
            {{ successKind === 'success' ? "You're in! Check your inbox." : "You're already on the list ✓" }}
          </p>
        </div>
      </div>
    </div>
  </Transition>

  <NewsletterModal
    :show="showModal"
    source="footer_bar"
    @dismiss="onModalDismiss"
    @subscribed="onModalSubscribed"
  />
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.4s cubic-bezier(0.33, 1, 0.68, 1), opacity 0.4s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
