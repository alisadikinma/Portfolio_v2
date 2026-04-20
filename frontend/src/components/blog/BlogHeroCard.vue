<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  post: { type: Object, required: true },
  lang: { type: String, default: 'en' },
})

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
const readingTime = (text) => {
  if (!text) return 1
  const words = text.replace(/<[^>]+>/g, '').split(/\s+/).filter(Boolean).length
  return Math.max(1, Math.round(words / 200))
}

const dateLabel = computed(() => formatDate(props.post.published_at))
const minutesLabel = computed(() => `${readingTime(props.post.content || props.post.excerpt)} min read`)
</script>

<template>
  <RouterLink
    :to="`/${lang}/blog/${post.slug}`"
    class="relative block group rounded-3xl overflow-hidden bg-bg-elevated aspect-[4/5] md:aspect-[21/9]"
    data-testid="blog-hero-card"
  >
    <img
      v-if="post.featured_image"
      :src="post.featured_image"
      :alt="post.title"
      class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
    />
    <div
      v-else
      class="absolute inset-0 bg-gradient-to-br from-accent-gold/20 via-bg-elevated to-accent-cyan/20 flex items-center justify-center"
    >
      <svg class="w-16 h-16 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>

    <!-- Overlay gradient -->
    <div class="absolute inset-0 bg-gradient-to-t from-bg-deep via-bg-deep/60 to-transparent pointer-events-none"></div>

    <!-- Category pill top-left -->
    <span
      v-if="post.category?.name"
      class="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-mono bg-accent-cyan/15 text-accent-cyan border border-accent-cyan/25 backdrop-blur-sm"
    >
      {{ post.category.name }}
    </span>

    <!-- Bottom content -->
    <div class="absolute left-0 right-0 bottom-0 p-8 md:p-12 max-w-3xl">
      <div class="flex gap-3 items-center mono-label text-fg-dim text-xs mb-3">
        <time v-if="dateLabel" :datetime="post.published_at">{{ dateLabel }}</time>
        <span v-if="dateLabel" aria-hidden="true">·</span>
        <span>{{ minutesLabel }}</span>
      </div>
      <h2 class="font-display text-3xl md:text-5xl lg:text-6xl font-bold leading-tight text-fg-primary mb-4 group-hover:text-accent-gold transition-colors duration-500">
        {{ post.title }}
      </h2>
      <p v-if="post.excerpt" class="hidden md:block text-fg-muted text-base md:text-lg line-clamp-2 mb-5 max-w-2xl font-light">
        {{ post.excerpt }}
      </p>
      <span class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium group-hover:gap-3 transition-all">
        Read article
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
      </span>
    </div>
  </RouterLink>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
    animation: none !important;
  }
  .group:hover img {
    transform: none !important;
  }
}
</style>
