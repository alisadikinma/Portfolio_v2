<script setup>
import { ref, onMounted } from 'vue'
import { useNewsletter } from '@/composables/useNewsletter'

const props = defineProps({
  variant: {
    type: String,
    default: 'detail', // 'list' | 'detail' — visual tuning only
    validator: (v) => ['list', 'detail'].includes(v),
  },
})

const emit = defineEmits(['subscribed'])

const { subscribe, isSubscribed } = useNewsletter()

const hidden = ref(false)
const name = ref('')
const email = ref('')
const whatsappNumber = ref('')
const waError = ref(false)
const status = ref('idle') // 'idle' | 'loading' | 'success' | 'duplicate' | 'error'
const errorMsg = ref('')

const WA_REGEX = /^\+[1-9]\d{6,14}$/

onMounted(() => {
  if (isSubscribed()) hidden.value = true
})

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
    source: 'inline_card',
  })

  if (result.success) {
    status.value = 'success'
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', email.value)
  } else if (result.duplicate) {
    status.value = 'duplicate'
    name.value = ''
    email.value = ''
    whatsappNumber.value = ''
    emit('subscribed', email.value)
  } else {
    status.value = 'error'
    errorMsg.value = result.message || 'Something went wrong. Please try again.'
  }
}
</script>

<template>
  <div
    v-if="!hidden"
    class="my-10 max-w-xl mx-auto rounded-2xl border border-accent-gold/15 bg-bg-elevated/60 backdrop-blur-sm p-6 md:p-8"
    data-testid="newsletter-inline-card"
  >
    <div
      v-if="status === 'idle' || status === 'loading' || status === 'error'"
      class="flex flex-col gap-5"
    >
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-full bg-accent-gold/10 border border-accent-gold/20 flex items-center justify-center flex-shrink-0 text-2xl">
          <span aria-hidden="true">✨</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="mono-label text-accent-gold text-xs mb-1">One email, every Friday</p>
          <h3 class="font-display font-bold text-xl md:text-2xl text-fg-primary mb-1.5 leading-tight">
            Enjoying this?
          </h3>
          <p class="text-fg-muted text-sm leading-relaxed">
            Get the next essay like this one, plus behind-the-scenes notes on what I'm building.
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
          Format internasional, no spaces (e.g., +628123456789)
        </p>
        <p v-else class="text-fg-dim text-[11px] px-2">
          WhatsApp format internasional, mulai dengan +
        </p>

        <button
          type="submit"
          :disabled="status === 'loading' || waError"
          class="btn-gold text-sm whitespace-nowrap mt-1.5"
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

    <!-- Success / duplicate state -->
    <div
      v-else
      class="flex items-center gap-4 py-2"
    >
      <div
        class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
        :class="status === 'success' ? 'bg-accent-gold/15 border border-accent-gold/40' : 'bg-accent-cyan/15 border border-accent-cyan/40'"
      >
        <svg class="w-6 h-6" :class="status === 'success' ? 'text-accent-gold' : 'text-accent-cyan'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
</template>
