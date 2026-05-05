<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  source: {
    type: String,
    default: 'inline_card',
    validator: (v) => ['blog_inline', 'inline_card', 'floating_banner', 'footer_bar'].includes(v),
  },
})

const emit = defineEmits(['dismiss', 'subscribed'])

const { subscribe } = useNewsletter()

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
    source: props.source,
  })

  if (result.success) {
    status.value = 'success'
    const submitted = email.value
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', submitted)
    setTimeout(() => emit('dismiss'), 2500)
  } else if (result.duplicate) {
    status.value = 'duplicate'
    const submitted = email.value
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', submitted)
    setTimeout(() => emit('dismiss'), 2500)
  } else {
    status.value = 'error'
    errorMsg.value = result.message || 'Something went wrong. Please try again.'
  }
}

function handleBackdropClick(event) {
  if (event.target === event.currentTarget) {
    emit('dismiss')
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape' && props.show) {
    emit('dismiss')
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
      // reset state when reopened
      status.value = 'idle'
      errorMsg.value = ''
      waError.value = false
      await nextTick()
      const input = cardRef.value?.querySelector('input[type="text"]')
      input?.focus()
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <Transition name="nl-modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        @click="handleBackdropClick"
        role="dialog"
        aria-modal="true"
        aria-label="Subscribe to newsletter"
      >
        <div
          ref="cardRef"
          class="relative w-full max-w-md rounded-2xl border border-accent-gold/20 bg-bg-elevated/95 backdrop-blur-xl p-6 md:p-8 shadow-2xl"
        >
          <button
            type="button"
            class="absolute top-3 right-3 w-8 h-8 rounded-full flex items-center justify-center text-fg-dim hover:text-fg-primary hover:bg-white/5 transition-colors"
            aria-label="Close"
            @click="emit('dismiss')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div v-if="status !== 'success' && status !== 'duplicate'">
            <div class="flex items-start gap-4 mb-5">
              <div class="w-12 h-12 rounded-full bg-accent-gold/10 border border-accent-gold/20 flex items-center justify-center flex-shrink-0 text-2xl">
                <span aria-hidden="true">✨</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="mono-label text-accent-gold text-xs mb-1">One email, every Friday</p>
                <h3 class="font-display font-bold text-xl text-fg-primary leading-tight">
                  Stay in the loop
                </h3>
                <p class="text-fg-muted text-sm leading-relaxed mt-1.5">
                  AI, engineering, and the future of work — straight to your inbox.
                </p>
              </div>
            </div>

            <form class="flex flex-col gap-2.5" @submit.prevent="handleSubmit">
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
              <p v-if="waError" class="text-red-400 text-[11px] px-2">
                Format: +country code, no spaces (e.g., +628123456789)
              </p>
              <p v-else class="text-fg-dim text-[11px] px-2">
                WhatsApp internasional, mulai dengan +
              </p>

              <button
                type="submit"
                :disabled="status === 'loading' || waError"
                class="btn-gold text-sm w-full mt-2"
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

              <p v-if="status === 'error'" class="text-red-400 text-xs mt-1">{{ errorMsg }}</p>
            </form>
          </div>

          <div v-else class="flex items-center gap-4 py-4">
            <div
              class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0"
              :class="status === 'success' ? 'bg-accent-gold/15 border border-accent-gold/40' : 'bg-accent-cyan/15 border border-accent-cyan/40'"
            >
              <svg class="w-7 h-7" :class="status === 'success' ? 'text-accent-gold' : 'text-accent-cyan'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h3 class="font-display font-bold text-lg text-fg-primary mb-0.5">
                {{ status === 'success' ? "You're in!" : "Already subscribed" }}
              </h3>
              <p class="text-fg-muted text-sm">
                {{ status === 'success' ? 'Check your inbox for a welcome note.' : "You're on the list ✓" }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.nl-modal-enter-active,
.nl-modal-leave-active {
  transition: opacity 0.25s ease;
}
.nl-modal-enter-active > div,
.nl-modal-leave-active > div {
  transition: transform 0.25s cubic-bezier(0.33, 1, 0.68, 1), opacity 0.25s ease;
}
.nl-modal-enter-from,
.nl-modal-leave-to {
  opacity: 0;
}
.nl-modal-enter-from > div,
.nl-modal-leave-to > div {
  opacity: 0;
  transform: translateY(20px) scale(0.96);
}
</style>
