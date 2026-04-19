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
const email = ref('')
const status = ref('idle') // 'idle' | 'loading' | 'success' | 'duplicate' | 'error'
const errorMsg = ref('')

onMounted(() => {
  if (isSubscribed()) hidden.value = true
})

async function handleSubmit() {
  const value = email.value.trim()
  if (!value) return

  status.value = 'loading'
  errorMsg.value = ''

  const result = await subscribe(value)

  if (result.success) {
    status.value = 'success'
    email.value = ''
    emit('subscribed', value)
  } else if (result.duplicate) {
    status.value = 'duplicate'
    email.value = ''
    emit('subscribed', value)
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
      class="flex flex-col md:flex-row items-start md:items-center gap-5"
    >
      <div class="w-14 h-14 rounded-full bg-accent-gold/10 border border-accent-gold/20 flex items-center justify-center flex-shrink-0 text-2xl">
        <span aria-hidden="true">✨</span>
      </div>

      <div class="flex-1 min-w-0">
        <p class="mono-label text-accent-gold text-xs mb-1.5">One email, every Friday</p>
        <h3 class="font-display font-bold text-xl md:text-2xl text-fg-primary mb-1.5 leading-tight">
          Enjoying this?
        </h3>
        <p class="text-fg-muted text-sm leading-relaxed mb-4">
          Get the next essay like this one, plus behind-the-scenes notes on what I'm building.
        </p>

        <form class="flex flex-col sm:flex-row gap-2.5" @submit.prevent="handleSubmit">
          <input
            v-model="email"
            type="email"
            required
            :disabled="status === 'loading'"
            placeholder="your@email.com"
            class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/40 transition-colors disabled:opacity-50"
          />
          <button
            type="submit"
            :disabled="status === 'loading'"
            class="btn-gold text-sm whitespace-nowrap"
          >
            <span v-if="status !== 'loading'">Subscribe</span>
            <span v-else class="inline-flex items-center gap-2">
              <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Sending
            </span>
          </button>
        </form>

        <p v-if="status === 'error'" class="text-red-400 text-xs mt-2">{{ errorMsg }}</p>
      </div>
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
