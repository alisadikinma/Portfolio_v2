<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  post: { type: Object, required: true },
  lang: { type: String, default: 'en' },
})

function truncate(text, max) {
  if (!text) return ''
  const clean = String(text).trim()
  if (clean.length <= max) return clean
  return clean.slice(0, max).trimEnd() + '…'
}

const quoteText = computed(() => {
  if (props.post.ai_summary) return truncate(props.post.ai_summary, 240)
  if (props.post.excerpt) return truncate(props.post.excerpt, 200)
  return ''
})

const hasQuote = computed(() => quoteText.value.length > 0)
</script>

<template>
  <RouterLink
    :to="`/${lang}/blog/${post.slug}`"
    class="block group"
    data-testid="blog-quote-card"
  >
    <article class="relative rounded-3xl p-10 md:p-16 overflow-hidden bg-bg-elevated/40 border border-accent-gold/15 backdrop-blur-sm">
      <div
        aria-hidden="true"
        class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-accent-gold/10 blur-3xl pointer-events-none"
      ></div>

      <span
        aria-hidden="true"
        class="absolute top-8 left-8 md:top-12 md:left-12 text-accent-gold font-serif text-7xl md:text-9xl leading-none opacity-40 select-none"
      >&ldquo;</span>

      <div class="relative max-w-3xl mx-auto text-center">
        <p
          v-if="hasQuote"
          class="font-serif italic text-2xl md:text-3xl lg:text-4xl leading-snug text-fg-primary mb-6"
        >
          {{ quoteText }}
        </p>
        <h3
          v-else
          class="font-display text-2xl md:text-3xl lg:text-4xl leading-snug text-fg-primary mb-6 font-semibold"
        >
          {{ post.title }}
        </h3>

        <p v-if="hasQuote" class="mono-label text-fg-dim text-xs">
          — from "{{ post.title }}"
        </p>

        <span
          class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-500"
        >
          Read essay
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
          </svg>
        </span>
      </div>
    </article>
  </RouterLink>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
    animation: none !important;
  }
}
</style>
