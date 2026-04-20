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
    class="block group bezel-shell"
    data-testid="blog-wide-card"
  >
    <article class="bezel-core overflow-hidden">
      <div class="grid md:grid-cols-2 gap-0">
        <!-- Image side -->
        <div class="relative aspect-video md:aspect-[4/3] overflow-hidden bg-bg-elevated">
          <img
            v-if="post.featured_image"
            :src="post.featured_image"
            :alt="post.title"
            loading="lazy"
            class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
          />
          <div v-else class="w-full h-full flex items-center justify-center">
            <svg class="w-14 h-14 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <span
            v-if="post.category?.name"
            class="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-mono bg-accent-cyan/15 text-accent-cyan border border-accent-cyan/25 backdrop-blur-sm"
          >
            {{ post.category.name }}
          </span>
        </div>

        <!-- Content side -->
        <div class="p-8 md:p-10 flex flex-col justify-center">
          <div class="flex gap-3 items-center mono-label text-fg-dim text-xs mb-4">
            <time v-if="dateLabel" :datetime="post.published_at">{{ dateLabel }}</time>
            <span v-if="dateLabel" aria-hidden="true">·</span>
            <span>{{ minutesLabel }}</span>
          </div>
          <h3 class="section-heading text-2xl md:text-3xl font-bold text-fg-primary mb-3 leading-tight group-hover:text-accent-gold transition-colors duration-500 line-clamp-3">
            {{ post.title }}
          </h3>
          <p v-if="post.excerpt" class="text-fg-muted leading-relaxed line-clamp-3 font-light mb-6">
            {{ post.excerpt }}
          </p>
          <div class="flex justify-between items-center">
            <span class="inline-flex items-center gap-1 text-xs text-fg-dim">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              {{ post.views || 0 }}
            </span>
            <span class="inline-flex items-center gap-1 text-accent-gold text-sm font-medium group-hover:gap-2 transition-all">
              Read article
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </span>
          </div>
        </div>
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
  .group:hover img {
    transform: none !important;
  }
}
</style>
