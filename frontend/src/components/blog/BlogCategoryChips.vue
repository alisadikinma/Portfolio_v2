<script setup>
import { RouterLink } from 'vue-router'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  selectedId: { type: [Number, Object, null], default: null },
})

const emit = defineEmits(['select'])

function handleSelect(id) {
  emit('select', id)
}

function initial(name) {
  if (!name) return '?'
  return name.trim().charAt(0).toUpperCase()
}
</script>

<template>
  <nav
    class="blog-category-chips flex gap-4 px-1 py-2 overflow-x-auto scroll-smooth snap-x"
    aria-label="Category filter"
    data-testid="blog-category-chips"
  >
    <!-- All pill -->
    <button
      type="button"
      class="group flex flex-col items-center flex-shrink-0 snap-start focus:outline-none"
      :class="selectedId === null ? 'is-selected' : ''"
      :aria-pressed="selectedId === null"
      @click="handleSelect(null)"
    >
      <div
        class="w-[72px] h-[72px] rounded-full flex items-center justify-center transition-transform duration-300 ease-spring bg-accent-gold/10 border border-accent-gold/20 group-hover:scale-[1.08] group-hover:brightness-110"
        :class="selectedId === null ? 'ring-2 ring-accent-gold ring-offset-2 ring-offset-bg-deep scale-105' : ''"
      >
        <svg
          class="w-6 h-6"
          :class="selectedId === null ? 'text-accent-gold' : 'text-fg-muted group-hover:text-fg-primary'"
          fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"
        >
          <rect x="3" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="3" width="7" height="7" rx="1.5" />
          <rect x="3" y="14" width="7" height="7" rx="1.5" />
          <rect x="14" y="14" width="7" height="7" rx="1.5" />
        </svg>
      </div>
      <span
        class="mt-2 text-xs font-medium transition-colors duration-300"
        :class="selectedId === null ? 'text-accent-gold' : 'text-fg-muted'"
      >
        All
      </span>
    </button>

    <!-- Category pills -->
    <button
      v-for="cat in categories"
      :key="cat.id"
      type="button"
      class="group flex flex-col items-center flex-shrink-0 snap-start focus:outline-none"
      :aria-pressed="selectedId === cat.id"
      @click="handleSelect(cat.id)"
    >
      <div
        class="w-[72px] h-[72px] rounded-full flex items-center justify-center transition-transform duration-300 ease-spring bg-accent-gold/10 border border-accent-gold/20 overflow-hidden group-hover:scale-[1.08] group-hover:brightness-110"
        :class="selectedId === cat.id ? 'ring-2 ring-accent-gold ring-offset-2 ring-offset-bg-deep scale-105' : ''"
      >
        <img
          v-if="cat.icon_url"
          :src="cat.icon_url"
          :alt="cat.name"
          class="w-full h-full object-cover"
        />
        <span
          v-else
          class="font-display text-2xl"
          :class="selectedId === cat.id ? 'text-accent-gold' : 'text-fg-muted group-hover:text-fg-primary'"
        >
          {{ initial(cat.name) }}
        </span>
      </div>
      <span
        class="mt-2 text-xs font-medium transition-colors duration-300 max-w-[80px] truncate text-center"
        :class="selectedId === cat.id ? 'text-accent-gold' : 'text-fg-muted'"
      >
        {{ cat.name }}
      </span>
    </button>
  </nav>
</template>

<style scoped>
.blog-category-chips {
  scrollbar-width: none;
}
.blog-category-chips::-webkit-scrollbar {
  display: none;
}
button:focus-visible > div {
  outline: 2px solid var(--accent-gold, #D4A843);
  outline-offset: 4px;
}
@media (prefers-reduced-motion: reduce) {
  .blog-category-chips * {
    transition: none !important;
    animation: none !important;
  }
  .blog-category-chips .group:hover > div {
    transform: none !important;
  }
}
</style>
