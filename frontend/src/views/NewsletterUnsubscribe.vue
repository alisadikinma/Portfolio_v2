<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useNewsletter } from '@/composables/useNewsletter'

const route = useRoute()
const { unsubscribeByToken, isLoading } = useNewsletter()

const token = computed(() => String(route.query.token ?? '').trim())
const status = ref('idle') // 'idle' | 'invalid_token' | 'success' | 'error'
const errorMsg = ref('')

onMounted(() => {
  if (!token.value || token.value.length !== 32) {
    status.value = 'invalid_token'
  }
})

async function handleConfirm() {
  if (!token.value) return
  status.value = 'loading'
  errorMsg.value = ''

  const result = await unsubscribeByToken(token.value)

  if (result.success) {
    status.value = 'success'
  } else {
    status.value = 'error'
    errorMsg.value = result.message || 'This unsubscribe link is no longer valid.'
  }
}
</script>

<template>
  <main class="min-h-screen bg-bg-deep flex items-center justify-center px-4 py-16">
    <div class="w-full max-w-md">
      <div class="rounded-2xl border border-accent-gold/15 bg-bg-elevated/60 backdrop-blur-sm p-8 md:p-10 text-center">
        <!-- Brand mark -->
        <p class="mono-label text-accent-gold text-xs mb-4">alisadikinma · newsletter</p>

        <!-- Idle state — confirm button -->
        <template v-if="status === 'idle' || status === 'loading'">
          <h1 class="font-display font-bold text-2xl md:text-3xl text-fg-primary mb-3">
            Unsubscribe?
          </h1>
          <p class="text-fg-muted text-sm leading-relaxed mb-8">
            You'll stop receiving the Friday digest. You can re-subscribe anytime from the blog.
          </p>
          <button
            type="button"
            class="btn-gold w-full text-sm"
            :disabled="status === 'loading' || isLoading"
            @click="handleConfirm"
          >
            <span v-if="status !== 'loading'">Confirm unsubscribe</span>
            <span v-else class="inline-flex items-center gap-2 justify-center">
              <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Working
            </span>
          </button>
          <router-link
            to="/blog"
            class="block text-fg-dim text-xs mt-4 hover:text-fg-muted transition-colors"
          >
            Cancel — keep me subscribed
          </router-link>
        </template>

        <!-- Success state -->
        <template v-else-if="status === 'success'">
          <div class="w-14 h-14 rounded-full bg-accent-gold/15 border border-accent-gold/40 flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-accent-gold" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 class="font-display font-bold text-2xl md:text-3xl text-fg-primary mb-3">
            You're unsubscribed
          </h1>
          <p class="text-fg-muted text-sm leading-relaxed mb-8">
            Sorry to see you go. If you change your mind, the door is always open.
          </p>
          <router-link to="/blog" class="btn-glass w-full text-sm">
            Back to blog
          </router-link>
        </template>

        <!-- Invalid token / error -->
        <template v-else>
          <div class="w-14 h-14 rounded-full bg-red-400/15 border border-red-400/40 flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h1 class="font-display font-bold text-2xl md:text-3xl text-fg-primary mb-3">
            Link no longer valid
          </h1>
          <p class="text-fg-muted text-sm leading-relaxed mb-2">
            {{ status === 'invalid_token'
              ? 'The unsubscribe link in your email is missing or malformed.'
              : errorMsg }}
          </p>
          <p class="text-fg-dim text-xs mb-6">
            If you keep getting newsletters and want them stopped, reply to any of them.
          </p>
          <router-link to="/blog" class="btn-glass w-full text-sm">
            Back to blog
          </router-link>
        </template>
      </div>

      <p class="text-center text-fg-dim text-xs mt-6">
        <router-link to="/" class="hover:text-fg-muted transition-colors">alisadikinma.com</router-link>
      </p>
    </div>
  </main>
</template>
