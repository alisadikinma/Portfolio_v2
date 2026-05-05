<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['dismiss', 'subscribed'])

const { subscribe, markDismissed } = useNewsletter()

const name = ref('')
const email = ref('')
const whatsappNumber = ref('')
const waError = ref(false)
const status = ref('idle')
const errorMsg = ref('')
const cardRef = ref(null)

const WA_REGEX = /^\+[1-9]\d{6,14}$/

function validateWa() {
  if (!whatsappNumber.value) {
    waError.value = false
    return
  }
  waError.value = !WA_REGEX.test(whatsappNumber.value.trim())
}

async function handleSubmit() {
  if (waError.value) return
  if (!name.value.trim() || !email.value.trim() || !whatsappNumber.value.trim()) return

  status.value = 'loading'
  errorMsg.value = ''

  const result = await subscribe({
    name: name.value.trim(),
    email: email.value.trim(),
    whatsappNumber: whatsappNumber.value.trim(),
    source: 'floating_banner',
  })

  if (result.success) {
    status.value = 'success'
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', email.value)
    setTimeout(() => emit('dismiss'), 2800)
  } else if (result.duplicate) {
    status.value = 'duplicate'
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', email.value)
    setTimeout(() => emit('dismiss'), 2800)
  } else {
    status.value = 'error'
    errorMsg.value = result.message || 'Something went wrong.'
  }
}

function handleDismiss() {
  markDismissed()
  emit('dismiss')
}

function handleKeydown(event) {
  if (event.key === 'Escape' && props.show) {
    handleDismiss()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

watch(
  () => props.show,
  async (visible) => {
    if (visible) {
      await nextTick()
      const input = cardRef.value?.querySelector('input[type="text"]')
      input?.focus()
    }
  },
)
</script>

<template>
  <Transition name="pop">
    <div
      v-if="show"
      ref="cardRef"
      class="fixed bottom-6 right-6 left-6 sm:left-auto sm:max-w-sm z-40 glass-card rounded-2xl p-5 border border-accent-cyan/25"
      data-testid="newsletter-floating-banner"
      role="dialog"
      aria-label="Subscribe to newsletter"
      aria-modal="false"
    >
      <button
        type="button"
        class="absolute top-3 right-3 w-7 h-7 rounded-full flex items-center justify-center text-fg-dim hover:text-fg-primary hover:bg-white/5 transition-colors"
        aria-label="Close newsletter banner"
        @click="handleDismiss"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div v-if="status !== 'success' && status !== 'duplicate'">
        <p class="mono-label text-accent-gold text-[10px] mb-2">✨ BEFORE YOU GO</p>
        <h3 class="font-display font-bold text-lg text-fg-primary mb-1.5 leading-snug">
          Before you go —
        </h3>
        <p class="text-fg-muted text-sm leading-relaxed mb-4">
          One essay per Friday on AI + engineering. No spam.
        </p>

        <form class="flex flex-col gap-2" @submit.prevent="handleSubmit">
          <input
            v-model="name"
            type="text"
            required
            minlength="2"
            maxlength="120"
            :disabled="status === 'loading'"
            placeholder="Your name"
            class="w-full px-4 py-2.5 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/40 transition-colors disabled:opacity-50"
          />
          <input
            v-model="email"
            type="email"
            required
            :disabled="status === 'loading'"
            placeholder="your@email.com"
            class="w-full px-4 py-2.5 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/40 transition-colors disabled:opacity-50"
          />
          <input
            v-model="whatsappNumber"
            type="tel"
            required
            :disabled="status === 'loading'"
            placeholder="+628123456789"
            @blur="validateWa"
            class="w-full px-4 py-2.5 rounded-full bg-white/4 border text-fg-primary placeholder-fg-dim text-sm focus:outline-none transition-colors disabled:opacity-50"
            :class="waError ? 'border-red-400/60 focus:border-red-400/80' : 'border-border-hairline focus:border-accent-gold/40'"
          />
          <p v-if="waError" class="text-red-400 text-[10px] px-2">
            Format: +country code, e.g., +628123456789
          </p>
          <button
            type="submit"
            :disabled="status === 'loading' || waError"
            class="btn-gold text-sm w-full mt-1"
          >
            <span v-if="status !== 'loading'">Subscribe</span>
            <span v-else class="inline-flex items-center gap-2 justify-center">
              <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Sending
            </span>
          </button>
        </form>

        <p v-if="status === 'error'" class="text-red-400 text-xs mt-2">{{ errorMsg }}</p>

        <button
          type="button"
          class="text-fg-dim text-xs mt-3 hover:text-fg-muted transition-colors"
          @click="handleDismiss"
        >
          Maybe later
        </button>
      </div>

      <div v-else class="flex items-center gap-3 py-2">
        <div
          class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
          :class="status === 'success' ? 'bg-accent-gold/15 border border-accent-gold/40' : 'bg-accent-cyan/15 border border-accent-cyan/40'"
        >
          <svg class="w-5 h-5" :class="status === 'success' ? 'text-accent-gold' : 'text-accent-cyan'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div>
          <h3 class="font-display font-bold text-sm text-fg-primary">
            {{ status === 'success' ? "You're in!" : "Already subscribed" }}
          </h3>
          <p class="text-fg-muted text-xs">
            {{ status === 'success' ? 'Check your inbox.' : "You're on the list ✓" }}
          </p>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.pop-enter-active,
.pop-leave-active {
  transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.33, 1, 0.68, 1);
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.96);
}
</style>
