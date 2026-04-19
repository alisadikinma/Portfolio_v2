<script setup>
import { ref } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'

defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['dismiss', 'subscribed'])

const { subscribe, markDismissed } = useNewsletter()

const email = ref('')
const status = ref('idle') // 'idle' | 'loading' | 'success' | 'duplicate' | 'error'

async function handleSubmit() {
  const value = email.value.trim()
  if (!value) return

  status.value = 'loading'

  const result = await subscribe(value)

  if (result.success) {
    status.value = 'success'
    email.value = ''
    emit('subscribed', value)
    setTimeout(() => emit('dismiss'), 2500)
  } else if (result.duplicate) {
    status.value = 'duplicate'
    email.value = ''
    emit('subscribed', value)
    setTimeout(() => emit('dismiss'), 2500)
  } else {
    status.value = 'idle'
  }
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
        <!-- Idle / loading state -->
        <div v-if="status !== 'success' && status !== 'duplicate'" class="flex items-center gap-3 sm:gap-6">
          <div class="hidden sm:flex items-center gap-3 flex-shrink-0">
            <span class="text-lg" aria-hidden="true">✨</span>
            <p class="text-fg-primary text-sm font-medium">
              <span class="hidden md:inline">Liked what you read? </span>Get a new essay every Friday.
            </p>
          </div>

          <p class="sm:hidden text-fg-primary text-xs font-medium flex-shrink-0">Get weekly essays</p>

          <form class="flex items-center gap-2 flex-1 min-w-0 sm:max-w-md" @submit.prevent="handleSubmit">
            <input
              v-model="email"
              type="email"
              required
              :disabled="status === 'loading'"
              placeholder="your@email.com"
              class="flex-1 min-w-0 px-3 py-2 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-xs sm:text-sm focus:outline-none focus:border-accent-gold/40 transition-colors disabled:opacity-50"
            />
            <button
              type="submit"
              :disabled="status === 'loading'"
              class="btn-gold text-xs px-4 py-2 whitespace-nowrap"
            >
              <span v-if="status !== 'loading'">Subscribe</span>
              <span v-else>...</span>
            </button>
          </form>

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

        <!-- Success state -->
        <div v-else class="flex items-center justify-center gap-3 py-1">
          <svg
            class="w-5 h-5"
            :class="status === 'success' ? 'text-accent-gold' : 'text-accent-cyan'"
            fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          <p class="text-fg-primary text-sm font-medium">
            {{ status === 'success' ? "You're in! Check your inbox." : "You're already on the list ✓" }}
          </p>
        </div>
      </div>
    </div>
  </Transition>
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
