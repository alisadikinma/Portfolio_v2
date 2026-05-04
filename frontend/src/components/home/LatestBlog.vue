<template>
  <section ref="sectionRef" class="py-28">
    <div class="container-custom">
      <!-- Header -->
      <div class="flex items-end justify-between mb-16 reveal" :class="{ 'is-visible': isVisible }">
        <div>
          <span class="eyebrow-tag text-accent-indigo mb-4 inline-flex">Insights</span>
          <h2 class="section-heading text-4xl md:text-5xl lg:text-6xl text-gradient mt-4">Latest Blog</h2>
          <p class="text-fg-muted text-lg mt-3 font-light">Insights on AI, tech, and building products.</p>
        </div>
        <router-link to="/blog" class="hidden md:flex items-center gap-2 btn-glass text-sm py-2.5 px-5">
          All Posts
          <span class="btn-icon-island w-6 h-6">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
          </span>
        </router-link>
      </div>

      <!-- Loading — 1 hero + 3 stacked (matches Phase 4 magazine layout) -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <div class="md:col-span-7 bezel-shell"><div class="bezel-core animate-pulse h-96" /></div>
        <div class="md:col-span-5 flex flex-col gap-4">
          <div class="bezel-shell-sm flex-1"><div class="bezel-core-sm animate-pulse h-28" /></div>
          <div class="bezel-shell-sm flex-1"><div class="bezel-core-sm animate-pulse h-28" /></div>
          <div class="bezel-shell-sm flex-1"><div class="bezel-core-sm animate-pulse h-28" /></div>
        </div>
      </div>

      <!-- Asymmetric Layout: 1 large (7 cols) + 2 stacked (5 cols) -->
      <div v-else class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <!-- Featured Post — Large Card -->
        <router-link
          v-if="posts[0]"
          :to="`/blog/${posts[0].slug}`"
          class="md:col-span-7 group"
        >
          <div class="bezel-shell h-full">
            <div class="bezel-core h-full overflow-hidden flex flex-col">
              <div v-if="posts[0].featured_image" class="aspect-[16/9] overflow-hidden bg-bg-elevated flex-shrink-0">
                <img
                  :src="posts[0].featured_image"
                  :alt="posts[0].title"
                  class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                  loading="lazy"
                />
              </div>
              <div class="p-6 flex-1 flex flex-col">
                <div class="flex items-center gap-2 mb-3">
                  <span v-if="posts[0].category" class="eyebrow-tag text-accent-cyan text-[9px]">{{ posts[0].category?.name || posts[0].category }}</span>
                  <span class="mono-label text-fg-dim text-[9px]">{{ formatDate(posts[0].published_at || posts[0].created_at) }}</span>
                </div>
                <h3 class="font-display text-xl md:text-2xl font-semibold text-fg-primary group-hover:text-accent-gold transition-all duration-700 ease-spring line-clamp-2">
                  {{ posts[0].title }}
                </h3>
                <p class="text-sm text-fg-muted mt-3 line-clamp-3 font-light">{{ posts[0].excerpt }}</p>
                <div class="mt-auto pt-4 flex items-center gap-2 text-fg-dim group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  <span class="text-xs font-medium">Read Article</span>
                  <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                </div>
              </div>
            </div>
          </div>
        </router-link>

        <!-- Stacked smaller posts (5 cols) — up to 3 secondary articles -->
        <div class="md:col-span-5 flex flex-col gap-4">
          <router-link
            v-for="post in posts.slice(1, 4)"
            :key="post.id"
            :to="`/blog/${post.slug}`"
            class="group flex-1"
          >
            <div class="bezel-shell-sm h-full">
              <div class="bezel-core-sm h-full overflow-hidden flex">
                <div v-if="post.featured_image" class="w-2/5 flex-shrink-0 overflow-hidden bg-bg-elevated">
                  <img
                    :src="post.featured_image"
                    :alt="post.title"
                    class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                    loading="lazy"
                  />
                </div>
                <div class="flex-1 p-5 flex flex-col">
                  <div class="flex items-center gap-2 mb-3">
                    <span v-if="post.category" class="eyebrow-tag text-accent-cyan text-[9px]">{{ post.category?.name || post.category }}</span>
                    <span class="mono-label text-fg-dim text-[9px]">{{ formatDate(post.published_at || post.created_at) }}</span>
                  </div>
                  <h3 class="font-display text-lg font-semibold text-fg-primary group-hover:text-accent-gold transition-all duration-700 ease-spring line-clamp-2">
                    {{ post.title }}
                  </h3>
                  <p class="text-sm text-fg-muted mt-2 line-clamp-2 font-light">{{ post.excerpt }}</p>
                  <div class="mt-auto pt-3 flex items-center gap-2 text-fg-dim group-hover:text-accent-gold transition-all duration-700 ease-spring">
                    <span class="text-xs font-medium">Read</span>
                    <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                  </div>
                </div>
              </div>
            </div>
          </router-link>
        </div>
      </div>

      <!-- Mobile CTA -->
      <router-link to="/blog" class="md:hidden flex items-center justify-center gap-2 mt-10 btn-glass text-sm py-3 px-6">
        All Posts
        <span class="btn-icon-island w-6 h-6">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
        </span>
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'

const posts = ref([])
const isLoading = ref(true)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(async () => {
  try {
    const res = await api.get('/posts', { params: { per_page: 4 } })
    posts.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch latest posts:', err)
  } finally {
    isLoading.value = false
  }

  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        isVisible.value = true
        observer.disconnect()
      }
    },
    { threshold: 0.1 }
  )
  if (sectionRef.value) observer.observe(sectionRef.value)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>
