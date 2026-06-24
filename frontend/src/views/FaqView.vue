<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useMetaTags } from '@/composables/useMetaTags'

// Single source: the same /api/faq the SSR /faq page renders from config/faq.php.
const { get } = useApi()
const { updatePageMeta } = useMetaTags()

const items = ref([])
const loading = ref(true)
const error = ref(null)
const openIndex = ref(0) // first item open by default

function toggle(i) {
  openIndex.value = openIndex.value === i ? -1 : i
}

onMounted(async () => {
  updatePageMeta({
    title: 'FAQ — Ali Sadikin Ma',
    description:
      'Answers to common questions about Ali Sadikin Ma — his AI services (AI Agents, Vibe Coding, Generative Video, AI visual inspection), how he works, and how to engage him.',
    url: '/faq',
  })

  try {
    const res = await get('/faq')
    items.value = Array.isArray(res?.data) ? res.data : []
    // FAQPage JSON-LD is emitted server-side by the SSR /faq page (the
    // authoritative crawlable copy), so the SPA view doesn't re-inject it.
  } catch (err) {
    error.value = 'Could not load the FAQ right now. Please try again later.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="min-h-screen bg-bg-deep px-4 py-16 md:py-24">
    <div class="mx-auto w-full max-w-3xl">
      <header class="mb-10 text-center">
        <p class="mono-label text-accent-gold text-xs mb-3">alisadikinma · faq</p>
        <h1 class="font-display font-bold text-3xl md:text-4xl text-fg-primary">
          Frequently Asked Questions
        </h1>
        <p class="mt-3 text-fg-muted">
          Quick answers about Ali Sadikin Ma — services, approach, and how to work together.
        </p>
      </header>

      <div v-if="loading" class="space-y-3" aria-busy="true">
        <div
          v-for="n in 6"
          :key="n"
          class="h-16 animate-pulse rounded-xl border border-accent-gold/10 bg-bg-elevated/40"
        />
      </div>

      <p v-else-if="error" class="rounded-xl border border-red-500/20 bg-red-500/5 p-4 text-center text-red-300">
        {{ error }}
      </p>

      <dl v-else class="space-y-3">
        <div
          v-for="(item, i) in items"
          :key="i"
          class="overflow-hidden rounded-xl border border-accent-gold/15 bg-bg-elevated/60 backdrop-blur-sm"
        >
          <dt>
            <button
              type="button"
              class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-accent-gold/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-gold/40"
              :aria-expanded="openIndex === i"
              :aria-controls="`faq-answer-${i}`"
              @click="toggle(i)"
            >
              <span class="font-display font-semibold text-fg-primary">{{ item.question }}</span>
              <span
                class="shrink-0 text-accent-gold transition-transform duration-200"
                :class="openIndex === i ? 'rotate-45' : ''"
                aria-hidden="true"
              >+</span>
            </button>
          </dt>
          <dd
            v-show="openIndex === i"
            :id="`faq-answer-${i}`"
            class="px-5 pb-5 pt-0 text-fg-muted leading-relaxed"
          >
            {{ item.answer }}
          </dd>
        </div>
      </dl>
    </div>
  </main>
</template>
