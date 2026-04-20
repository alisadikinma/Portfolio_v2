<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  posts: { type: Array, default: () => [] },
  lang: { type: String, default: 'en' },
})

const visible = computed(() => (Array.isArray(props.posts) ? props.posts.slice(0, 2) : []))

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <section
    v-if="visible.length >= 2"
    class="my-10 rounded-2xl border border-white/5 bg-bg-elevated/30 p-6 md:p-7"
    aria-label="Related reading"
    data-testid="blog-inline-related"
  >
    <p class="mono-label text-fg-dim text-xs mb-5">YOU MIGHT ALSO LIKE</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <RouterLink
        v-for="post in visible"
        :key="post.id"
        :to="`/${lang}/blog/${post.slug}`"
        class="group flex flex-col rounded-xl overflow-hidden bg-bg-deep/40 border border-white/5 hover:border-white/15 transition-colors duration-300"
      >
        <div class="relative aspect-video overflow-hidden bg-bg-elevated">
          <img
            v-if="post.featured_image"
            :src="post.featured_image"
            :alt="post.title"
            class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
          />
          <div
            v-else
            class="absolute inset-0 bg-gradient-to-br from-accent-gold/10 via-bg-elevated to-accent-cyan/10"
          ></div>
        </div>
        <div class="p-4 flex flex-col gap-2">
          <p v-if="post.category?.name" class="mono-label text-accent-cyan text-[10px]">
            {{ post.category.name }}
          </p>
          <h4 class="font-display text-base font-semibold text-fg-primary leading-snug line-clamp-2 group-hover:text-accent-gold transition-colors duration-300">
            {{ post.title }}
          </h4>
          <time
            v-if="post.published_at"
            :datetime="post.published_at"
            class="mono-label text-fg-dim text-[10px] mt-auto"
          >{{ formatDate(post.published_at) }}</time>
        </div>
      </RouterLink>
    </div>
  </section>
</template>
