<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary">

    <!-- Page Header -->
    <section class="relative pt-28 pb-20 overflow-hidden">
      <div class="container-custom relative">
        <div class="max-w-3xl mx-auto text-center">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Writing & Insights</span>
          <h1 class="section-heading text-5xl md:text-6xl lg:text-7xl font-bold mt-4 mb-6">
            <span class="text-gradient">Blog</span> & Articles
          </h1>
          <p class="text-lg text-fg-muted font-light max-w-xl mx-auto leading-relaxed">
            Thoughts, tutorials, and insights on AI, web development, and building things that matter.
          </p>
        </div>
      </div>
    </section>

    <!-- Featured Post -->
    <section v-if="featuredPost" class="container-custom mb-20">
      <span class="eyebrow-tag text-fg-dim mb-6 inline-flex">Featured Post</span>
      <div class="mt-4 bezel-shell cursor-pointer group" @click="$router.push(`/blog/${featuredPost.slug}`)">
        <div class="bezel-core overflow-hidden">
          <div class="grid md:grid-cols-2 gap-0">
            <!-- Image -->
            <div class="relative aspect-video md:aspect-auto overflow-hidden bg-bg-elevated">
              <img
                v-if="featuredPost.featured_image"
                :src="featuredPost.featured_image"
                :alt="featuredPost.title"
                class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
              />
              <div v-else class="w-full h-full flex items-center justify-center min-h-64">
                <svg class="w-16 h-16 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <span
                v-if="featuredPost.category?.name"
                class="absolute top-4 left-4 eyebrow-tag text-accent-gold border-accent-gold/20"
              >
                {{ featuredPost.category.name }}
              </span>
            </div>

            <!-- Content -->
            <div class="p-8 md:p-10 flex flex-col justify-center">
              <div class="flex items-center gap-3 mb-5">
                <time :datetime="featuredPost.published_at" class="mono-label text-fg-dim">{{ formatDate(featuredPost.published_at) }}</time>
                <span class="text-fg-dim text-xs">&middot;</span>
                <span class="mono-label text-fg-dim">{{ readingTime(featuredPost.content || featuredPost.excerpt) }} min read</span>
              </div>

              <h2 class="section-heading text-3xl md:text-4xl font-bold text-fg-primary mb-4 group-hover:text-accent-gold transition-all duration-700 ease-spring leading-tight">
                {{ featuredPost.title }}
              </h2>

              <p class="text-fg-muted leading-relaxed mb-8 line-clamp-3 font-light">
                {{ featuredPost.excerpt }}
              </p>

              <div class="flex items-center gap-3">
                <span class="btn-gold text-sm cursor-pointer">
                  Read Article
                  <span class="btn-icon-island">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Filters & Search -->
    <section class="container-custom mb-10">
      <div class="bezel-shell-sm">
        <div class="bezel-core-sm p-5">
          <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <!-- Category Pills -->
            <div class="flex flex-wrap gap-2">
              <button
                class="px-4 py-1.5 rounded-full text-xs font-medium transition-all duration-700 ease-spring"
                :class="selectedCategory === null
                  ? 'bg-accent-gold/10 text-accent-gold border border-accent-gold/25'
                  : 'bg-white/4 text-fg-muted border border-border-hairline hover:border-border-hover'"
                @click="selectCategory(null)"
              >
                All Posts
                <span class="ml-1.5 text-[10px] opacity-50">({{ filteredTotal }})</span>
              </button>
              <button
                v-for="cat in categories"
                :key="cat.id"
                class="px-4 py-1.5 rounded-full text-xs font-medium transition-all duration-700 ease-spring"
                :class="selectedCategory === cat.id
                  ? 'bg-accent-gold/10 text-accent-gold border border-accent-gold/25'
                  : 'bg-white/4 text-fg-muted border border-border-hairline hover:border-border-hover'"
                @click="selectCategory(cat.id)"
              >
                {{ cat.name }}
              </button>
            </div>

            <!-- Search -->
            <div class="relative w-full md:w-64 flex-shrink-0">
              <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-fg-dim pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search articles..."
                class="w-full pl-10 pr-4 py-2.5 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/30 transition-all duration-700 ease-spring"
              />
              <button
                v-if="searchQuery"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-fg-dim hover:text-fg-primary transition-colors"
                @click="searchQuery = ''"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Post Grid -->
    <section class="container-custom mb-20">
      <!-- Loading -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="i in 6" :key="i" class="bezel-shell-sm"><div class="bezel-core-sm animate-pulse"><div class="aspect-video bg-white/3"></div><div class="p-6 space-y-3"><div class="h-3 bg-white/3 rounded w-1/3"></div><div class="h-5 bg-white/3 rounded w-4/5"></div><div class="h-4 bg-white/3 rounded w-full mt-2"></div></div></div></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="paginatedPosts.length === 0" class="text-center py-28">
        <div class="bezel-shell inline-block max-w-md mx-auto">
          <div class="bezel-core p-12 text-center">
            <p class="text-fg-muted text-lg mb-2">No articles found</p>
            <p class="text-fg-dim text-sm font-light">Try adjusting your search or filter.</p>
            <button class="btn-glass mt-6 text-sm" @click="searchQuery = ''; selectedCategory = null">
              Clear Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <article
          v-for="post in paginatedPosts"
          :key="post.id"
          class="cursor-pointer group"
          @click="$router.push(`/blog/${post.slug}`)"
        >
          <div class="bezel-shell-sm h-full">
            <div class="bezel-core-sm overflow-hidden h-full flex flex-col">
              <!-- Thumbnail -->
              <div class="relative aspect-video overflow-hidden bg-bg-elevated flex-shrink-0">
                <img
                  v-if="post.featured_image"
                  :src="post.featured_image"
                  :alt="post.title"
                  class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <svg class="w-10 h-10 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <span
                  v-if="post.category?.name"
                  class="absolute top-3 left-3 eyebrow-tag text-[8px] text-accent-cyan border-accent-cyan/15 bg-bg-deep/70 backdrop-blur-sm"
                >
                  {{ post.category.name }}
                </span>
              </div>

              <!-- Body -->
              <div class="p-5 flex flex-col flex-grow">
                <div class="flex items-center gap-2 mb-3">
                  <time :datetime="post.published_at" class="mono-label text-fg-dim text-[10px]">{{ formatDate(post.published_at) }}</time>
                  <span class="text-fg-dim text-[10px]">&middot;</span>
                  <span class="mono-label text-fg-dim text-[10px]">{{ readingTime(post.content || post.excerpt) }} min</span>
                </div>

                <h3 class="text-lg font-semibold font-display text-fg-primary mb-2 leading-snug group-hover:text-accent-gold transition-all duration-700 ease-spring line-clamp-2">
                  {{ post.title }}
                </h3>

                <p class="text-fg-muted text-sm leading-relaxed line-clamp-3 flex-grow font-light">
                  {{ post.excerpt }}
                </p>

                <div class="flex items-center justify-between pt-4 border-t border-border-hairline mt-auto">
                  <span class="flex items-center gap-1 text-xs text-fg-dim">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ post.views || 0 }}
                  </span>
                  <span class="text-accent-gold text-xs font-medium flex items-center gap-1 group-hover:gap-2 transition-all duration-700 ease-spring">
                    Read
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-14">
        <button
          class="btn-glass px-4 py-2 text-sm disabled:opacity-30 disabled:cursor-not-allowed"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          Prev
        </button>
        <div class="flex items-center gap-1">
          <button
            v-for="page in displayedPages"
            :key="page"
            class="w-9 h-9 rounded-full text-sm font-medium transition-all duration-700 ease-spring"
            :class="page === currentPage
              ? 'bg-accent-gold/15 text-accent-gold border border-accent-gold/25'
              : 'bg-white/4 text-fg-muted border border-border-hairline hover:border-border-hover'"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>
        <button
          class="btn-glass px-4 py-2 text-sm disabled:opacity-30 disabled:cursor-not-allowed"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
      </div>
    </section>

    <!-- Newsletter -->
    <section class="container-custom mb-24">
      <div class="bezel-shell max-w-2xl mx-auto">
        <div class="bezel-core p-8 md:p-12 relative overflow-hidden text-center">
          <div class="absolute -top-20 -right-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #D4A843;"></div>
          <div class="absolute -bottom-20 -left-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #06B6D4;"></div>

          <div class="relative">
            <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Stay in the loop</span>
            <h2 class="section-heading text-3xl md:text-4xl font-bold mt-4 mb-4">
              Get the latest <span class="text-gradient">articles</span>
            </h2>
            <p class="text-fg-muted mb-8 leading-relaxed font-light">
              Thoughtful pieces on AI, engineering, and the future of work.
            </p>

            <form class="flex flex-col sm:flex-row gap-3 max-w-sm mx-auto" @submit.prevent="subscribeNewsletter">
              <div class="flex-1 bezel-shell-sm">
                <input
                  v-model="newsletterEmail"
                  type="email"
                  placeholder="your@email.com"
                  class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder-fg-dim text-sm focus:outline-none rounded-[calc(1.25rem-4px)]"
                />
              </div>
              <button type="submit" class="btn-gold text-sm">
                Subscribe
              </button>
            </form>

            <p class="text-fg-dim text-xs mt-4">No spam. Unsubscribe anytime.</p>
          </div>
        </div>
      </div>
    </section>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { useToast } from '@/composables/useToast'
import api from '@/services/api'

const route = useRoute()
const { posts, isLoading, fetchPosts } = usePosts()
const toast = useToast()

const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 9
const newsletterEmail = ref('')

const lang = computed(() => route.params.lang || 'en')

onMounted(async () => {
  await fetchPosts({}, lang.value)
  try {
    const res = await api.get('/categories')
    categories.value = res.data?.data || res.data || []
  } catch {}
})

// Refetch when language changes
watch(lang, async (newLang) => {
  await fetchPosts({}, newLang)
})

const featuredPost = computed(() => posts.value?.[0] ?? null)

const filteredPosts = computed(() => {
  let list = posts.value?.slice(1) ?? []
  if (selectedCategory.value !== null) {
    list = list.filter(p => p.category?.id === selectedCategory.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(p =>
      p.title?.toLowerCase().includes(q) ||
      p.excerpt?.toLowerCase().includes(q)
    )
  }
  return list
})

const filteredTotal = computed(() => {
  if (selectedCategory.value === null && !searchQuery.value.trim()) {
    return posts.value?.length ?? 0
  }
  return filteredPosts.value.length
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredPosts.value.length / perPage)))

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredPosts.value.slice(start, start + perPage)
})

const displayedPages = computed(() => {
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(totalPages.value, start + maxVisible - 1)
  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1)
  }
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const goToPage = (page) => {
  currentPage.value = page
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const selectCategory = (id) => {
  selectedCategory.value = id
  currentPage.value = 1
}

watch([selectedCategory, searchQuery], () => {
  currentPage.value = 1
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

const subscribeNewsletter = () => {
  toast.info('Coming soon — newsletter subscription is not yet available.')
  newsletterEmail.value = ''
}
</script>
