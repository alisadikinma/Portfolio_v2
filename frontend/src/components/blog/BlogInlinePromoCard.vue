<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  slot: { type: Object, default: null },
})

const accentClasses = computed(() => {
  const t = props.slot?.type
  if (t === 'project') return { eyebrow: 'text-accent-gold', border: 'border-accent-gold/20' }
  if (t === 'award') return { eyebrow: 'text-accent-cyan', border: 'border-accent-cyan/20' }
  return { eyebrow: 'text-accent-indigo', border: 'border-accent-indigo/20' }
})

const hasImage = computed(() => {
  return typeof props.slot?.image === 'string' && props.slot.image.trim().length > 0
})
</script>

<template>
  <RouterLink
    v-if="slot && slot.link"
    :to="slot.link"
    class="my-10 block group rounded-2xl border bg-bg-elevated/50 backdrop-blur-sm overflow-hidden hover:bg-bg-elevated/70 transition-colors duration-300"
    :class="accentClasses.border"
    data-testid="blog-inline-promo-card"
  >
    <div class="grid md:grid-cols-[40%_60%] gap-0">
      <div
        v-if="hasImage"
        class="relative aspect-[4/3] overflow-hidden bg-bg-deep"
      >
        <img
          :src="slot.image"
          :alt="slot.title"
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
          {{ slot.eyebrow || 'Featured' }}
        </p>
        <h3 class="font-display font-bold text-xl md:text-2xl text-fg-primary mb-2 leading-tight group-hover:text-accent-gold transition-colors duration-300">
          {{ slot.title }}
        </h3>
        <p v-if="slot.description" class="text-fg-muted text-sm line-clamp-2 mb-4 font-light leading-relaxed">
          {{ slot.description }}
        </p>
        <span class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium group-hover:gap-3 transition-all">
          {{ slot.cta_label || 'Learn more' }}
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </span>
      </div>
    </div>
  </RouterLink>
</template>
