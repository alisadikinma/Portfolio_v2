<script setup>
import { computed, h } from 'vue'
import { splitHtmlByH2 } from '@/utils/splitHtmlByH2'
import BlogInlinePromoCard from './BlogInlinePromoCard.vue'
import BlogInlineRelatedPosts from './BlogInlineRelatedPosts.vue'
import NewsletterInlineCard from './NewsletterInlineCard.vue'

const props = defineProps({
  html: { type: String, default: '' },
  promoSlot: { type: Object, default: null },
  relatedPosts: { type: Array, default: () => [] },
  lang: { type: String, default: 'en' },
})

// Chunks: parsed once per html change.
const chunks = computed(() => {
  try {
    return splitHtmlByH2(props.html)
  } catch {
    // If the regex parser trips on something unexpected, fall back to a single
    // chunk so the article still renders. Catches theoretical edge cases only.
    return [{ type: 'chunk', html: props.html || '' }]
  }
})

// Count only H2 sections (not raw chunks) so injection depth scales with
// outline length, not just prose volume.
const h2Count = computed(() => chunks.value.filter((c) => c.type === 'h2').length)

// Map: for chunk at index i (0-based into the chunks array), what component
// should follow it? Uses H2 count — not chunk index — so short articles don't
// get over-injected.
//
// Strategy: after the 1st H2 section → promo; near middle → newsletter;
// near end (one H2 before last) → related posts.
const injectAfterChunkIndex = computed(() => {
  const out = {}
  const h2s = h2Count.value
  if (h2s < 2) return out

  // Find chunk indices that immediately follow the 1st, middle-ish, and
  // next-to-last H2.
  const h2ChunkIndices = []
  chunks.value.forEach((c, i) => {
    if (c.type === 'h2') h2ChunkIndices.push(i)
  })

  // The chunk AFTER the Nth H2 is at h2ChunkIndices[N-1] + 1.
  const after = (n) => {
    if (n < 1 || n > h2ChunkIndices.length) return null
    const idx = h2ChunkIndices[n - 1] + 1
    return idx < chunks.value.length ? idx : null
  }

  // Promo after H2 #1 (requires ≥ 2 H2s so there's content after it).
  if (h2s >= 2 && props.promoSlot) {
    const afterFirst = after(1)
    if (afterFirst !== null) out[afterFirst] = 'promo'
  }

  // Newsletter mid-way — only when article has ≥ 4 H2s.
  if (h2s >= 4) {
    const mid = Math.max(2, Math.floor(h2s / 2))
    const afterMid = after(mid)
    if (afterMid !== null && !out[afterMid]) out[afterMid] = 'newsletter'
  }

  // Related posts near the end — requires ≥ 5 H2s AND at least 2 related posts.
  if (h2s >= 5 && Array.isArray(props.relatedPosts) && props.relatedPosts.length >= 2) {
    const afterLastButOne = after(h2s - 1)
    if (afterLastButOne !== null && !out[afterLastButOne]) out[afterLastButOne] = 'related'
  }

  return out
})

function renderInjection(kind) {
  if (kind === 'promo') {
    return h(BlogInlinePromoCard, { slot: props.promoSlot })
  }
  if (kind === 'newsletter') {
    return h(NewsletterInlineCard, { variant: 'detail' })
  }
  if (kind === 'related') {
    return h(BlogInlineRelatedPosts, {
      posts: props.relatedPosts,
      lang: props.lang,
    })
  }
  return null
}

const RenderedArticle = {
  render() {
    const nodes = []
    chunks.value.forEach((chunk, i) => {
      if (chunk.type === 'chunk') {
        // The raw HTML chunk goes into its own div so v-html isolates scope.
        nodes.push(
          h('div', {
            class: 'blog-content',
            innerHTML: chunk.html,
          })
        )
      } else if (chunk.type === 'h2') {
        // Render the real H2 with its id so the TOC observer can track it.
        // scroll-margin-top keeps the header offset sane when navigating.
        nodes.push(
          h(
            'h2',
            {
              id: chunk.id,
              class: 'blog-content-h2',
              style: { scrollMarginTop: '96px' },
            },
            chunk.text
          )
        )
      }

      const kind = injectAfterChunkIndex.value[i]
      if (kind) {
        const injection = renderInjection(kind)
        if (injection) nodes.push(injection)
      }
    })
    return nodes
  },
}
</script>

<template>
  <div data-testid="blog-content-injector">
    <RenderedArticle />
  </div>
</template>
