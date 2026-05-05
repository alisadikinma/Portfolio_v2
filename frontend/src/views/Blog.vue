<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary">

    <!-- Page Header — compact, no wasted vertical -->
    <section class="relative pt-24 pb-10 md:pt-28 md:pb-12">
      <div class="container-custom">
        <div class="max-w-3xl">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Writing & Insights</span>
          <h1 class="section-heading text-4xl md:text-5xl font-bold tracking-tight leading-[1.05] mb-4">
            <span class="text-gradient">Blog</span> <span class="text-fg-primary">&amp; Articles</span>
          </h1>
          <p class="text-base md:text-lg text-fg-muted font-light max-w-xl leading-relaxed">
            Thoughts, tutorials, and dispatches on AI, engineering, and building things that matter.
          </p>
        </div>
      </div>
    </section>

    <!-- Sticky filter strip -->
    <section
      class="sticky top-16 md:top-20 z-30 backdrop-blur-md bg-bg-deep/85 border-y border-border-hairline"
    >
      <div class="container-custom py-3 md:py-4">
        <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-6">
          <div class="flex-1 min-w-0 overflow-x-auto -mx-1 px-1 scrollbar-none">
            <BlogCategoryChips
              :categories="categories"
              :selected-id="selectedCategory"
              @select="selectCategory"
            />
          </div>

          <div class="flex items-center gap-3 flex-shrink-0">
            <div class="mono-label text-fg-dim text-[10px] hidden md:block">
              {{ filteredTotal }} {{ filteredTotal === 1 ? 'article' : 'articles' }}
            </div>

            <div class="relative w-full md:w-56">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-fg-dim pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search articles…"
                class="w-full pl-9 pr-8 py-2 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/30 transition-all duration-500 ease-spring"
                aria-label="Search articles"
              />
              <button
                v-if="searchQuery"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-fg-dim hover:text-fg-primary transition-colors p-0.5"
                @click="searchQuery = ''"
                aria-label="Clear search"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Feed -->
    <section class="container-custom mt-10 md:mt-12 mb-20">

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="space-y-0">
        <div class="flex gap-5 py-6 animate-pulse">
          <div class="w-[180px] md:w-[280px] aspect-[4/3] flex-shrink-0 bg-white/3 rounded-xl"></div>
          <div class="flex-1 space-y-3 py-2">
            <div class="h-3 bg-white/3 rounded w-1/3"></div>
            <div class="h-7 bg-white/3 rounded w-4/5"></div>
            <div class="h-4 bg-white/3 rounded w-full"></div>
          </div>
        </div>
        <div v-for="i in 5" :key="i" class="flex gap-4 md:gap-5 py-6 border-t border-border-hairline animate-pulse">
          <div class="w-24 md:w-32 aspect-[4/3] flex-shrink-0 bg-white/3 rounded-lg"></div>
          <div class="flex-1 space-y-2.5 py-1">
            <div class="h-2.5 bg-white/3 rounded w-1/4"></div>
            <div class="h-5 bg-white/3 rounded w-3/4"></div>
            <div class="h-3 bg-white/3 rounded w-full"></div>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="paginatedPosts.length === 0" class="text-center py-24">
        <div class="inline-flex flex-col items-center max-w-sm mx-auto">
          <div class="w-12 h-12 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center mb-5">
            <svg class="w-5 h-5 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
          </div>
          <p class="text-fg-primary text-base mb-1.5">Nothing matches that yet.</p>
          <p class="text-fg-dim text-sm font-light mb-6">Try a different keyword or clear the filter.</p>
          <button class="btn-glass text-sm" @click="searchQuery = ''; selectedCategory = null">
            Clear filters
          </button>
        </div>
      </div>

      <!-- Editorial feed -->
      <div v-else class="space-y-0">

        <!-- Latest (compact horizontal feature, only on page 1) -->
        <RouterLink
          v-if="featuredPost"
          :to="`/${lang}/blog/${featuredPost.slug}`"
          class="group grid grid-cols-1 md:grid-cols-[minmax(0,1.1fr),minmax(0,1fr)] gap-5 md:gap-8 pb-8 md:pb-10 mb-2"
          data-testid="blog-feature"
        >
          <div class="relative aspect-[16/10] md:aspect-[4/3] overflow-hidden rounded-xl bg-bg-elevated border border-border-hairline">
            <img
              v-if="featuredPost.featured_image"
              :src="featuredPost.featured_image"
              :alt="featuredPost.title"
              loading="eager"
              class="w-full h-full object-cover transition-transform duration-[900ms] ease-spring group-hover:scale-[1.03]"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-10 h-10 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <span class="absolute top-3 left-3 mono-label text-[9px] tracking-[0.2em] text-accent-gold bg-bg-deep/85 backdrop-blur-sm px-2 py-1 rounded-full border border-accent-gold/25">
              Latest
            </span>
          </div>

          <div class="flex flex-col justify-center">
            <div class="flex items-center gap-2 mono-label text-fg-dim text-[10px] mb-3">
              <span v-if="featuredPost.category?.name" class="text-accent-cyan">{{ featuredPost.category.name }}</span>
              <span v-if="featuredPost.category?.name" aria-hidden="true">·</span>
              <time v-if="featuredPost.published_at" :datetime="featuredPost.published_at">
                {{ formatDate(featuredPost.published_at) }}
              </time>
              <span aria-hidden="true">·</span>
              <span>{{ readingTime(featuredPost.content || featuredPost.excerpt) }} min read</span>
            </div>

            <h2 class="font-display text-2xl md:text-3xl lg:text-[2rem] font-bold leading-[1.15] tracking-tight text-fg-primary mb-3 transition-colors duration-500">
              <span class="bg-[linear-gradient(currentColor,currentColor)] bg-no-repeat bg-[length:0%_1px] bg-[position:0_98%] group-hover:bg-[length:100%_1px] transition-[background-size] duration-700 ease-spring text-fg-primary group-hover:text-accent-gold">
                {{ featuredPost.title }}
              </span>
            </h2>

            <p v-if="featuredPost.excerpt" class="text-fg-muted text-sm md:text-base leading-relaxed font-light line-clamp-3 mb-5 max-w-[55ch]">
              {{ featuredPost.excerpt }}
            </p>

            <span class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium group-hover:gap-3 transition-all duration-500 ease-spring">
              Read article
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </span>
          </div>
        </RouterLink>

        <!-- Editorial divide-y list -->
        <div class="divide-y divide-border-hairline border-t border-border-hairline">
          <template v-for="(post, index) in feedPosts" :key="post.id || index">

            <RouterLink
              :to="`/${lang}/blog/${post.slug}`"
              class="group flex gap-4 md:gap-6 py-5 md:py-6 transition-colors duration-300"
              data-testid="blog-row"
            >
              <!-- Thumbnail (small, controlled) -->
              <div class="relative w-24 md:w-36 lg:w-44 aspect-[4/3] flex-shrink-0 overflow-hidden rounded-lg bg-bg-elevated border border-border-hairline">
                <img
                  v-if="post.featured_image"
                  :src="post.featured_image"
                  :alt="post.title"
                  loading="lazy"
                  class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-[1.06]"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <svg class="w-6 h-6 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0 flex flex-col justify-center">
                <div class="flex items-center gap-2 mono-label text-fg-dim text-[10px] mb-1.5 md:mb-2">
                  <span v-if="post.category?.name" class="text-accent-cyan">{{ post.category.name }}</span>
                  <span v-if="post.category?.name" aria-hidden="true" class="text-border-hover">·</span>
                  <time v-if="post.published_at" :datetime="post.published_at">{{ formatDate(post.published_at) }}</time>
                  <span aria-hidden="true" class="text-border-hover">·</span>
                  <span>{{ readingTime(post.content || post.excerpt) }} min</span>
                </div>

                <h3 class="font-display text-lg md:text-xl font-semibold leading-snug tracking-tight text-fg-primary mb-1.5 line-clamp-2 transition-colors duration-300 group-hover:text-accent-gold">
                  {{ post.title }}
                </h3>

                <p v-if="post.excerpt" class="hidden md:block text-fg-muted text-sm leading-relaxed font-light line-clamp-2 max-w-[60ch]">
                  {{ post.excerpt }}
                </p>
              </div>

              <!-- Trailing arrow (desktop only) -->
              <div class="hidden md:flex items-center pl-2">
                <span class="w-8 h-8 rounded-full border border-border-hairline flex items-center justify-center text-fg-dim group-hover:text-accent-gold group-hover:border-accent-gold/40 group-hover:bg-accent-gold/[0.04] transition-all duration-500 ease-spring group-hover:translate-x-0.5">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                  </svg>
                </span>
              </div>
            </RouterLink>

            <!-- Inline newsletter card every N rows -->
            <div
              v-if="shouldInjectNewsletter(index)"
              class="py-6"
              :data-newsletter-after="index"
            >
              <NewsletterInlineCard variant="list" />
            </div>
          </template>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 && !isLoading" class="flex items-center justify-center gap-1.5 mt-12 md:mt-14">
        <button
          class="px-3.5 py-2 text-xs mono-label tracking-[0.18em] rounded-full border border-border-hairline text-fg-muted hover:text-fg-primary hover:border-border-hover transition-all duration-500 ease-spring active:scale-[0.97] disabled:opacity-30 disabled:cursor-not-allowed"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          Prev
        </button>
        <div class="flex items-center gap-1 mx-1">
          <button
            v-for="page in displayedPages"
            :key="page"
            class="w-9 h-9 rounded-full text-sm font-medium transition-all duration-500 ease-spring active:scale-[0.95]"
            :class="page === currentPage
              ? 'bg-accent-gold/15 text-accent-gold border border-accent-gold/30 shadow-[inset_0_1px_0_rgba(212,168,67,0.25)]'
              : 'bg-transparent text-fg-muted border border-border-hairline hover:border-border-hover hover:text-fg-primary'"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>
        <button
          class="px-3.5 py-2 text-xs mono-label tracking-[0.18em] rounded-full border border-border-hairline text-fg-muted hover:text-fg-primary hover:border-border-hover transition-all duration-500 ease-spring active:scale-[0.97] disabled:opacity-30 disabled:cursor-not-allowed"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
      </div>
    </section>

    <!-- Newsletter -->
    <section v-if="!newsletterAlreadySubscribed" class="container-custom mb-24">
      <div class="bezel-shell max-w-2xl mx-auto">
        <div class="bezel-core p-8 md:p-12 relative overflow-hidden text-center">
          <div class="absolute -top-20 -right-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #D4A843;"></div>
          <div class="absolute -bottom-20 -left-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #06B6D4;"></div>

          <div class="relative">
            <div v-if="nlStatus === 'idle' || nlStatus === 'loading' || nlStatus === 'error'">
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
                    required
                    :disabled="nlStatus === 'loading'"
                    class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder-fg-dim text-sm focus:outline-none rounded-[calc(1.25rem-4px)] disabled:opacity-50"
                  />
                </div>
                <button type="submit" class="btn-gold text-sm" :disabled="nlStatus === 'loading'">
                  <span v-if="nlStatus !== 'loading'">Subscribe</span>
                  <span v-else class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Sending
                  </span>
                </button>
              </form>

              <p v-if="nlStatus === 'error'" class="text-red-400 text-xs mt-3">{{ nlErrorMsg }}</p>
              <p v-else class="text-fg-dim text-xs mt-4">No spam. Unsubscribe anytime.</p>
            </div>

            <Transition name="fade" mode="out-in">
              <div v-if="nlStatus === 'success' || nlStatus === 'duplicate'" class="py-6">
                <div
                  class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5"
                  :class="nlStatus === 'success' ? 'bg-accent-gold/15 border border-accent-gold/40' : 'bg-accent-cyan/15 border border-accent-cyan/40'"
                >
                  <svg class="w-7 h-7" :class="nlStatus === 'success' ? 'text-accent-gold' : 'text-accent-cyan'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h3 class="section-heading text-2xl md:text-3xl font-bold mb-2">
                  {{ nlStatus === 'success' ? "You're in!" : "Already subscribed" }}
                </h3>
                <p class="text-fg-muted text-sm">
                  {{ nlStatus === 'success' ? 'Check your inbox for a welcome note.' : "You're on the list." }}
                </p>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <NewsletterFooterBar
      :show="showFooterBar"
      @dismiss="onFooterBarDismiss"
      @subscribed="onFooterBarSubscribed"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { useNewsletter } from '@/composables/useNewsletter'
import NewsletterFooterBar from '@/components/blog/NewsletterFooterBar.vue'
import NewsletterInlineCard from '@/components/blog/NewsletterInlineCard.vue'
import BlogCategoryChips from '@/components/blog/BlogCategoryChips.vue'
import api from '@/services/api'

const route = useRoute()
const { posts, isLoading, fetchPosts } = usePosts()
const { subscribe: nlSubscribe, isSubscribed: nlIsSubscribed, isDismissed: nlIsDismissed } = useNewsletter()

const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 10

const newsletterEmail = ref('')
const nlStatus = ref('idle')
const nlErrorMsg = ref('')
const newsletterAlreadySubscribed = ref(false)
const showFooterBar = ref(false)

let scrollRafId = null
let scrollListener = null

function checkScrollThreshold() {
  if (newsletterAlreadySubscribed.value || nlIsDismissed()) {
    showFooterBar.value = false
    return
  }
  const doc = document.documentElement
  const scrollY = window.scrollY || doc.scrollTop
  const maxScroll = doc.scrollHeight - window.innerHeight
  if (maxScroll <= 0) return
  const pct = scrollY / maxScroll
  if (pct > 0.6) showFooterBar.value = true
}

function onScroll() {
  if (scrollRafId !== null) return
  scrollRafId = requestAnimationFrame(() => {
    scrollRafId = null
    checkScrollThreshold()
  })
}

function onFooterBarDismiss() {
  showFooterBar.value = false
}

function onFooterBarSubscribed() {
  newsletterAlreadySubscribed.value = true
  showFooterBar.value = false
}

const lang = computed(() => route.params.lang || 'en')

onMounted(async () => {
  newsletterAlreadySubscribed.value = nlIsSubscribed()
  await fetchPosts({}, lang.value)
  try {
    const res = await api.get('/categories')
    const all = res.data?.data || res.data || []
    categories.value = all.filter(c =>
      c.posts_count === undefined || c.posts_count > 0
    )
  } catch {}

  scrollListener = onScroll
  window.addEventListener('scroll', scrollListener, { passive: true })
  requestAnimationFrame(checkScrollThreshold)
})

onUnmounted(() => {
  if (scrollListener) {
    window.removeEventListener('scroll', scrollListener)
    scrollListener = null
  }
  if (scrollRafId !== null) {
    cancelAnimationFrame(scrollRafId)
    scrollRafId = null
  }
})

watch(lang, async (newLang) => {
  await fetchPosts({}, newLang)
})

const filteredPosts = computed(() => {
  let list = posts.value ? [...posts.value] : []
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

const filteredTotal = computed(() => filteredPosts.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(filteredPosts.value.length / perPage)))

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredPosts.value.slice(start, start + perPage)
})

// Featured = first post on page 1 only; rest go to the editorial list.
// On other pages every post is treated equally — no synthetic "Latest" repeat.
const featuredPost = computed(() => {
  if (currentPage.value !== 1) return null
  return paginatedPosts.value[0] || null
})

const feedPosts = computed(() =>
  featuredPost.value ? paginatedPosts.value.slice(1) : paginatedPosts.value
)

// Inject inline newsletter every 6 rows (skip the very last row)
const newsletterEvery = 6
function shouldInjectNewsletter(index) {
  if (newsletterAlreadySubscribed.value) return false
  if (newsletterEvery <= 0) return false
  if (index === feedPosts.value.length - 1) return false
  return (index + 1) % newsletterEvery === 0
}

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

const subscribeNewsletter = async () => {
  const email = newsletterEmail.value.trim()
  if (!email) return

  nlStatus.value = 'loading'
  nlErrorMsg.value = ''

  const result = await nlSubscribe(email)

  if (result.success) {
    nlStatus.value = 'success'
    newsletterEmail.value = ''
  } else if (result.duplicate) {
    nlStatus.value = 'duplicate'
    newsletterEmail.value = ''
  } else {
    nlStatus.value = 'error'
    nlErrorMsg.value = result.message || 'Something went wrong. Please try again.'
  }
}
</script>

<style scoped>
.scrollbar-none {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.scrollbar-none::-webkit-scrollbar {
  display: none;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    transition-duration: 0ms !important;
    animation-duration: 0ms !important;
  }
  .group:hover img {
    transform: none !important;
  }
}
</style>
