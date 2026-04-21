<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

// Prop named `card` (not `slot`) to avoid the Vue 2 `slot` reserved-word
// footgun that some linters + Volar still warn about in Vue 3.
const props = defineProps({
  card: { type: Object, default: null },
})

const accentClasses = computed(() => {
  const t = props.card?.type
  if (t === 'project') return { eyebrow: 'text-accent-gold', border: 'border-accent-gold/20' }
  if (t === 'award') return { eyebrow: 'text-accent-cyan', border: 'border-accent-cyan/20' }
  return { eyebrow: 'text-accent-indigo', border: 'border-accent-indigo/20' }
})

const hasImage = computed(() => {
  return typeof props.card?.image === 'string' && props.card.image.trim().length > 0
})
</script>

<template>
  <!--
    RouterLink with custom slot → we get the resolved href from Vue Router
    but render a plain <a target="_blank"> so the project/award opens in a
    new tab. Goal: keep readers anchored to the blog post — they can
    explore the case study in a new tab and return without losing scroll
    position / reading progress / newsletter dismissal state.
  -->
  <RouterLink
    v-if="card && card.link"
    :to="card.link"
    custom
    v-slot="{ href }"
  >
    <a
      :href="href"
      target="_blank"
      rel="noopener noreferrer"
      class="relative my-10 block group rounded-2xl border bg-bg-elevated/50 backdrop-blur-sm overflow-hidden hover:bg-bg-elevated/70 transition-colors duration-300"
      :class="accentClasses.border"
      data-testid="blog-inline-promo-card"
    >
      <!-- External-link hint (top-right on hover) -->
      <div class="pointer-events-none absolute top-3 right-3 z-10 w-7 h-7 rounded-full bg-bg-deep/70 backdrop-blur-sm border border-white/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
        <svg class="w-3.5 h-3.5" :class="accentClasses.eyebrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
      </div>
      <div class="grid md:grid-cols-[40%_60%] gap-0">
      <div
        v-if="hasImage"
        class="relative aspect-[4/3] overflow-hidden bg-bg-deep"
      >
        <img
          :src="card.image"
          :alt="card.title"
          class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
        />
      </div>
      <div
        v-else
        class="relative aspect-[4/3] md:aspect-auto bg-gradient-to-br from-accent-gold/10 via-bg-elevated to-accent-cyan/10 flex items-center justify-center"
      >
        <svg class="w-12 h-12 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      </div>

      <div class="p-6 md:p-7 flex flex-col justify-center">
        <p class="mono-label text-xs mb-2" :class="accentClasses.eyebrow">
          {{ card.eyebrow || 'Featured' }}
        </p>
        <h3 class="font-display font-bold text-xl md:text-2xl text-fg-primary mb-2 leading-tight group-hover:text-accent-gold transition-colors duration-300">
          {{ card.title }}
        </h3>
        <p v-if="card.description" class="text-fg-muted text-sm line-clamp-2 mb-4 font-light leading-relaxed">
          {{ card.description }}
        </p>
        <span class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium group-hover:gap-3 transition-all">
          {{ card.cta_label || 'Learn more' }}
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </span>
      </div>
      </div>
    </a>
  </RouterLink>
</template>
