<script setup>
import { computed } from 'vue'
import { getCardVariant } from '@/utils/blogCardDistributor'
import BlogHeroCard from './BlogHeroCard.vue'
import BlogWideCard from './BlogWideCard.vue'
import BlogTallCard from './BlogTallCard.vue'
import BlogSmallCard from './BlogSmallCard.vue'
import BlogQuoteCard from './BlogQuoteCard.vue'
import NewsletterInlineCard from './NewsletterInlineCard.vue'

const props = defineProps({
  posts: { type: Array, required: true },
  lang: { type: String, default: 'en' },
  newsletterEvery: {
    type: Number,
    default: 9,
    validator: (v) => Number.isFinite(v) && v >= 0,
  },
})

const variantComponent = {
  hero: BlogHeroCard,
  wide: BlogWideCard,
  tall: BlogTallCard,
  small: BlogSmallCard,
  quote: BlogQuoteCard,
}

const variantColClass = {
  hero: 'col-span-full',
  wide: 'col-span-full',
  tall: 'col-span-1 md:col-span-1 md:row-span-2',
  small: 'col-span-1',
  quote: 'col-span-full',
}

const slots = computed(() => {
  const posts = Array.isArray(props.posts) ? props.posts : []
  const out = []
  posts.forEach((post, i) => {
    const variant = getCardVariant(i, post)
    out.push({
      kind: 'card',
      key: `post-${post.id ?? i}`,
      variant,
      colClass: variantColClass[variant] || variantColClass.small,
      component: variantComponent[variant] || BlogSmallCard,
      post,
    })
    const every = Number(props.newsletterEvery) || 0
    if (every > 0 && (i + 1) % every === 0 && i !== posts.length - 1) {
      out.push({
        kind: 'newsletter',
        key: `nl-after-${i}`,
        colClass: 'col-span-1 md:col-span-3',
      })
    }
  })
  return out
})
</script>

<template>
  <div
    class="grid grid-cols-1 md:grid-cols-3 gap-6"
    data-testid="blog-feed-distributor"
  >
    <template v-for="slot in slots" :key="slot.key">
      <component
        v-if="slot.kind === 'card'"
        :is="slot.component"
        :post="slot.post"
        :lang="lang"
        :class="slot.colClass"
        :data-variant="slot.variant"
      />
      <div
        v-else-if="slot.kind === 'newsletter'"
        :class="slot.colClass"
      >
        <NewsletterInlineCard variant="list" />
      </div>
    </template>
  </div>
</template>
