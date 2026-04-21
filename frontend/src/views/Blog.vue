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

    <!-- Filters & Search -->
    <section class="container-custom mb-8">
      <BlogCategoryChips
        :categories="categories"
        :selected-id="selectedCategory"
        @select="selectCategory"
        class="mb-6"
      />
      <div class="bezel-shell-sm">
        <div class="bezel-core-sm p-4">
          <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div class="mono-label text-fg-dim text-xs">
              {{ filteredTotal }} {{ filteredTotal === 1 ? 'article' : 'articles' }}
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
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="col-span-full bezel-shell"><div class="bezel-core animate-pulse aspect-[4/5] md:aspect-[21/9] bg-white/3"></div></div>
        <div class="col-span-full bezel-shell-sm"><div class="bezel-core-sm animate-pulse grid md:grid-cols-2 gap-0"><div class="aspect-video md:aspect-[4/3] bg-white/3"></div><div class="p-8 space-y-3"><div class="h-3 bg-white/3 rounded w-1/3"></div><div class="h-6 bg-white/3 rounded w-4/5"></div><div class="h-4 bg-white/3 rounded w-full mt-2"></div></div></div></div>
        <div v-for="i in 4" :key="i" class="bezel-shell-sm"><div class="bezel-core-sm animate-pulse"><div class="aspect-video bg-white/3"></div><div class="p-5 space-y-3"><div class="h-3 bg-white/3 rounded w-1/3"></div><div class="h-5 bg-white/3 rounded w-4/5"></div><div class="h-4 bg-white/3 rounded w-full mt-2"></div></div></div></div>
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

      <!-- Feed -->
      <BlogFeedDistributor
        v-else
        :posts="paginatedPosts"
        :lang="lang"
        :newsletter-every="9"
      />

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
    <section v-if="!newsletterAlreadySubscribed" class="container-custom mb-24">
      <div class="bezel-shell max-w-2xl mx-auto">
        <div class="bezel-core p-8 md:p-12 relative overflow-hidden text-center">
          <div class="absolute -top-20 -right-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #D4A843;"></div>
          <div class="absolute -bottom-20 -left-20 w-48 h-48 rounded-full opacity-8 blur-3xl pointer-events-none" style="background: #06B6D4;"></div>

          <div class="relative">
            <!-- Idle / loading / error state -->
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
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
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

            <!-- Success state -->
            <Transition name="fade" mode="out-in">
              <div v-if="nlStatus === 'success' || nlStatus === 'duplicate'" class="py-6">
                <div
                  class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5"
                  :class="nlStatus === 'success' ? 'bg-accent-gold/15 border border-accent-gold/40' : 'bg-accent-cyan/15 border border-accent-cyan/40'"
                >
                  <svg class="w-7 h-7" :class="nlStatus === 'success' ? 'text-accent-gold' : 'text-accent-cyan'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h3 class="section-heading text-2xl md:text-3xl font-bold mb-2">
                  {{ nlStatus === 'success' ? "You're in!" : "Already subscribed" }}
                </h3>
                <p class="text-fg-muted text-sm">
                  {{ nlStatus === 'success' ? 'Check your inbox for a welcome note.' : "You're on the list ✓" }}
                </p>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </section>

    <!-- Sticky footer newsletter bar (triggers after 60% scroll) -->
    <NewsletterFooterBar
      :show="showFooterBar"
      @dismiss="onFooterBarDismiss"
      @subscribed="onFooterBarSubscribed"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { useNewsletter } from '@/composables/useNewsletter'
import NewsletterFooterBar from '@/components/blog/NewsletterFooterBar.vue'
import BlogCategoryChips from '@/components/blog/BlogCategoryChips.vue'
import BlogFeedDistributor from '@/components/blog/BlogFeedDistributor.vue'
import api from '@/services/api'

const route = useRoute()
const { posts, isLoading, fetchPosts } = usePosts()
const { subscribe: nlSubscribe, isSubscribed: nlIsSubscribed, isDismissed: nlIsDismissed } = useNewsletter()

const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = 9

// Newsletter state
const newsletterEmail = ref('')
const nlStatus = ref('idle') // 'idle' | 'loading' | 'success' | 'duplicate' | 'error'
const nlErrorMsg = ref('')
const newsletterAlreadySubscribed = ref(false)
const showFooterBar = ref(false)

// Scroll-triggered footer bar
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
    // Hide empty categories from the filter chips — the API returns every
    // category for admin compatibility, but showing a chip that links to
    // zero posts is a dead-end click. posts_count is populated by
    // CategoryController::index via withCount(['posts' => published()]).
    // Categories without the count (legacy payload) default to visible so
    // we never silently drop everything on schema drift.
    categories.value = all.filter(c =>
      c.posts_count === undefined || c.posts_count > 0
    )
  } catch {}

  scrollListener = onScroll
  window.addEventListener('scroll', scrollListener, { passive: true })
  // Trigger once after mount in case user lands deep-scrolled (e.g. back-button)
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

// Refetch when language changes
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
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
