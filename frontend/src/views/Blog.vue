<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary relative">

    <!-- Ambient backdrop -->
    <div class="absolute inset-x-0 top-0 h-[700px] pointer-events-none overflow-hidden" aria-hidden="true">
      <div
        class="absolute -top-32 left-1/2 -translate-x-1/2 w-[900px] h-[500px] rounded-full opacity-[0.07] blur-3xl"
        style="background: radial-gradient(circle, #D4A843 0%, transparent 70%);"
      ></div>
      <div
        class="absolute top-32 -right-40 w-[500px] h-[500px] rounded-full opacity-[0.05] blur-3xl"
        style="background: radial-gradient(circle, #06B6D4 0%, transparent 70%);"
      ></div>
    </div>

    <!-- Editorial Header -->
    <section class="relative pt-28 md:pt-36 pb-14 md:pb-20">
      <div class="container-custom">
        <!-- Live indicator -->
        <div class="flex items-center gap-3 mb-8">
          <span class="relative flex h-2 w-2" aria-hidden="true">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-gold opacity-50"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-gold"></span>
          </span>
          <span class="mono-label text-fg-dim text-[10px] tracking-[0.25em]">Writing &amp; Insights · Live</span>
        </div>

        <!-- Display heading -->
        <div class="max-w-4xl">
          <h1 class="font-display text-5xl md:text-7xl lg:text-[5.25rem] font-bold tracking-[-0.025em] leading-[0.95] mb-6 md:mb-8 text-fg-primary">
            Field notes on
            <br class="hidden sm:block" />
            <span class="text-gradient italic font-bold">building</span> with AI.
          </h1>
          <p class="text-base md:text-lg text-fg-muted font-light max-w-xl leading-relaxed">
            Essays, postmortems, and tactical playbooks from the trenches.
            Updated when the work surfaces something worth saying.
          </p>
        </div>

        <!-- Stats strip -->
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3 mono-label text-fg-dim text-[10px] mt-12 pt-6 border-t border-border-hairline tracking-[0.18em]">
          <span><span class="text-accent-gold tabular-nums">{{ String(totalCount).padStart(2, '0') }}</span> Essays</span>
          <span class="text-border-hover" aria-hidden="true">/</span>
          <span><span class="text-accent-cyan tabular-nums">{{ String(categories.length).padStart(2, '0') }}</span> Topics</span>
          <span class="text-border-hover" aria-hidden="true">/</span>
          <span>Last drop <span class="text-fg-primary">{{ lastDropLabel }}</span></span>
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

          <div class="relative w-full md:w-56 flex-shrink-0">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-fg-dim pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search essays…"
              class="w-full pl-9 pr-8 py-2 rounded-full bg-white/4 border border-border-hairline text-fg-primary placeholder-fg-dim text-sm focus:outline-none focus:border-accent-gold/30 transition-all duration-500 ease-spring"
              aria-label="Search essays"
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
    </section>

    <!-- Featured Essay (page 1 only) -->
    <section v-if="featuredPost && !isLoading" class="container-custom mt-14 md:mt-20 mb-16 md:mb-24">
      <RouterLink
        :to="`/${lang}/blog/${featuredPost.slug}`"
        class="group relative block"
        data-testid="blog-featured"
      >
        <div class="relative grid md:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] gap-0 rounded-2xl overflow-hidden border border-border-hairline group-hover:border-accent-gold/40 transition-colors duration-700 ease-spring bg-bg-elevated/30">

          <!-- Left text panel -->
          <div class="relative p-7 md:p-10 lg:p-14 flex flex-col justify-center order-2 md:order-1 z-10">
            <div class="mono-label text-accent-gold text-[10px] mb-5 inline-flex items-center gap-2.5 tracking-[0.2em]">
              <span class="w-6 h-px bg-accent-gold/60"></span>
              Featured Essay
              <span class="text-fg-dim font-mono">№ {{ String(featuredNumber).padStart(2, '0') }}</span>
            </div>

            <h2 class="font-display text-3xl md:text-4xl lg:text-[2.75rem] font-bold leading-[1.05] tracking-[-0.02em] text-fg-primary mb-5 max-w-[20ch]">
              <span class="bg-[linear-gradient(currentColor,currentColor)] bg-no-repeat bg-[length:0%_2px] bg-[position:0_99%] group-hover:bg-[length:100%_2px] transition-[background-size] duration-[900ms] ease-spring">
                {{ featuredPost.title }}
              </span>
            </h2>

            <p v-if="featuredPost.excerpt" class="text-base md:text-lg text-fg-muted leading-relaxed font-light line-clamp-3 max-w-[55ch] mb-8">
              {{ featuredPost.excerpt }}
            </p>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mono-label text-fg-dim text-[10px] tracking-[0.2em] mb-7">
              <span v-if="featuredPost.category?.name" class="text-accent-cyan">{{ featuredPost.category.name }}</span>
              <span v-if="featuredPost.category?.name" class="text-border-hover" aria-hidden="true">/</span>
              <time v-if="featuredPost.published_at" :datetime="featuredPost.published_at">
                {{ formatDate(featuredPost.published_at) }}
              </time>
              <span class="text-border-hover" aria-hidden="true">/</span>
              <span>{{ readingTime(featuredPost.content || featuredPost.excerpt) }} min read</span>
            </div>

            <span class="inline-flex items-center gap-2 text-accent-gold text-sm font-medium group-hover:gap-3 transition-all duration-500 ease-spring">
              Read essay
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </span>
          </div>

          <!-- Right atmospheric image (cropped to right, faded into text panel) -->
          <div class="relative order-1 md:order-2 aspect-[16/10] md:aspect-auto md:min-h-[480px] overflow-hidden bg-bg-deep">
            <img
              v-if="featuredPost.featured_image"
              :src="featuredPost.featured_image"
              :alt="featuredPost.title"
              loading="eager"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1500ms] ease-spring group-hover:scale-[1.04]"
              style="object-position: right center;"
            />
            <!-- Left edge fade INTO text panel — kills burned-in title that lives on left of image -->
            <div class="absolute inset-y-0 left-0 w-2/3 md:w-1/2 bg-gradient-to-r from-bg-elevated via-bg-elevated/85 to-transparent"></div>
            <!-- Bottom subtle vignette -->
            <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-bg-deep/40 to-transparent"></div>
            <!-- Atmospheric color wash -->
            <div class="absolute inset-0 mix-blend-overlay opacity-30 pointer-events-none" style="background: radial-gradient(circle at 80% 50%, rgba(212,168,67,0.25) 0%, transparent 60%);"></div>
          </div>
        </div>

        <!-- Hover rail (gold→cyan stripe across bottom) -->
        <div class="absolute -bottom-px left-0 right-0 h-[2px] bg-gradient-to-r from-accent-gold via-accent-cyan to-transparent scale-x-0 group-hover:scale-x-100 origin-left transition-transform duration-1000 ease-spring rounded-full"></div>
      </RouterLink>
    </section>

    <!-- The Index -->
    <section class="container-custom mb-20 md:mb-28">

      <!-- Section header -->
      <div v-if="!isLoading && (feedPosts.length > 0 || featuredPost)" class="flex items-end justify-between mb-6 md:mb-10 pb-5 md:pb-6 border-b border-border-hairline">
        <div>
          <span class="mono-label text-fg-dim text-[10px] inline-flex tracking-[0.25em] mb-3">Section · 02</span>
          <h3 class="font-display text-2xl md:text-3xl font-bold tracking-[-0.02em] text-fg-primary">
            The <span class="italic font-medium text-accent-gold">index</span>
          </h3>
        </div>
        <div class="text-right">
          <div class="mono-label text-fg-dim text-[10px] tracking-[0.2em] mb-1">Filtered</div>
          <div class="font-mono text-sm md:text-base text-fg-primary tabular-nums">
            {{ String(filteredTotal).padStart(2, '0') }}
            <span class="text-fg-dim text-xs">/ {{ String(totalCount).padStart(2, '0') }}</span>
          </div>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="space-y-0">
        <div v-for="i in 8" :key="i" class="grid grid-cols-[40px_1fr_36px] md:grid-cols-[60px_1fr_100px_36px] items-center gap-4 md:gap-6 py-6 md:py-7 border-b border-border-hairline animate-pulse">
          <div class="h-3 bg-white/3 rounded w-full"></div>
          <div class="space-y-2.5 min-w-0">
            <div class="h-5 bg-white/3 rounded w-3/4"></div>
            <div class="h-2.5 bg-white/3 rounded w-1/3"></div>
          </div>
          <div class="hidden md:block h-3 bg-white/3 rounded w-full"></div>
          <div class="w-7 h-7 rounded-full bg-white/3"></div>
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

      <!-- Editorial typography list -->
      <div v-else class="essay-list">
        <template v-for="(post, index) in feedPosts" :key="post.id || index">

          <RouterLink
            :to="`/${lang}/blog/${post.slug}`"
            class="essay-row group relative grid grid-cols-[40px_1fr_28px] md:grid-cols-[60px_1fr_110px_36px] items-center gap-4 md:gap-6 py-6 md:py-7 border-b border-border-hairline transition-[padding,background] duration-500 ease-spring hover:pl-3 md:hover:pl-4 hover:bg-white/[0.012]"
            :style="{ '--row-delay': `${Math.min(index * 40, 320)}ms` }"
            data-testid="blog-row"
          >
            <!-- Left rail accent (slides in on hover) -->
            <span
              class="absolute left-0 top-3 bottom-3 w-[2px] rounded-full bg-gradient-to-b from-accent-gold via-accent-gold to-accent-cyan scale-y-0 group-hover:scale-y-100 origin-center transition-transform duration-700 ease-spring"
              aria-hidden="true"
            ></span>

            <!-- Number -->
            <span class="font-mono text-xs md:text-sm text-fg-dim group-hover:text-accent-gold transition-colors duration-500 tabular-nums tracking-[0.05em] whitespace-nowrap">
              <span class="opacity-60 mr-0.5">№</span>{{ String(getPostNumber(index)).padStart(2, '0') }}
            </span>

            <!-- Title + meta + excerpt -->
            <div class="min-w-0">
              <h4 class="font-display text-base md:text-xl font-semibold leading-[1.25] tracking-[-0.015em] text-fg-primary group-hover:text-accent-gold transition-colors duration-500 mb-1.5 line-clamp-2">
                {{ post.title }}
              </h4>
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mono-label text-fg-dim text-[10px] tracking-[0.18em]">
                <span v-if="post.category?.name" class="text-accent-cyan">{{ post.category.name }}</span>
                <span v-if="post.category?.name" class="text-border-hover" aria-hidden="true">/</span>
                <time v-if="post.published_at" :datetime="post.published_at">{{ formatDate(post.published_at) }}</time>
              </div>
              <p v-if="post.excerpt" class="hidden md:block text-fg-muted text-sm leading-relaxed font-light line-clamp-1 mt-2 max-w-[62ch]">
                {{ post.excerpt }}
              </p>
            </div>

            <!-- Reading time (desktop only) -->
            <div class="hidden md:flex items-center justify-end mono-label text-fg-dim text-[10px] tracking-[0.2em] tabular-nums whitespace-nowrap">
              <span class="text-fg-muted">{{ String(readingTime(post.content || post.excerpt)).padStart(2, '0') }}</span>
              <span class="ml-1.5 text-border-hover">MIN</span>
            </div>

            <!-- Arrow -->
            <span
              class="w-7 h-7 md:w-8 md:h-8 rounded-full border border-border-hairline flex items-center justify-center text-fg-dim group-hover:text-bg-deep group-hover:bg-accent-gold group-hover:border-accent-gold transition-all duration-500 ease-spring group-hover:translate-x-0.5"
              aria-hidden="true"
            >
              <svg class="w-3 h-3 md:w-3.5 md:h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </span>
          </RouterLink>

          <!-- Inline newsletter every N rows (chapter break) -->
          <div
            v-if="shouldInjectNewsletter(index)"
            class="py-8 md:py-10 border-b border-border-hairline"
            :data-newsletter-after="index"
          >
            <NewsletterInlineCard variant="list" />
          </div>
        </template>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1 && !isLoading" class="grid grid-cols-3 items-center gap-4 mt-14 md:mt-20 pt-7 border-t border-border-hairline">
        <button
          class="group inline-flex items-center gap-3 mono-label text-[10px] text-fg-muted hover:text-fg-primary transition-colors duration-500 tracking-[0.22em] disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-fg-muted justify-self-start"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          <svg class="w-3 h-3 group-hover:-translate-x-1 group-disabled:translate-x-0 transition-transform duration-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
          Earlier
        </button>

        <div class="font-mono text-xs md:text-sm text-fg-dim tabular-nums tracking-[0.18em] justify-self-center text-center">
          <span class="text-accent-gold">{{ String(currentPage).padStart(2, '0') }}</span>
          <span class="mx-1.5 text-border-hover">/</span>
          <span>{{ String(totalPages).padStart(2, '0') }}</span>
        </div>

        <button
          class="group inline-flex items-center gap-3 mono-label text-[10px] text-fg-muted hover:text-fg-primary transition-colors duration-500 tracking-[0.22em] disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-fg-muted justify-self-end"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Recent
          <svg class="w-3 h-3 group-hover:translate-x-1 group-disabled:translate-x-0 transition-transform duration-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
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
const perPage = 12

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

const totalCount = computed(() => (posts.value ? posts.value.length : 0))

const lastDropLabel = computed(() => {
  const list = posts.value || []
  if (!list.length) return '—'
  const latest = list.reduce((acc, p) => {
    const t = p.published_at ? new Date(p.published_at).getTime() : 0
    return t > acc ? t : acc
  }, 0)
  if (!latest) return '—'
  const diff = Date.now() - latest
  const days = Math.floor(diff / 86400000)
  if (days <= 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days}d ago`
  if (days < 30) return `${Math.floor(days / 7)}w ago`
  return `${Math.floor(days / 30)}mo ago`
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

const featuredPost = computed(() => {
  if (currentPage.value !== 1) return null
  return paginatedPosts.value[0] || null
})

const featuredNumber = computed(() => filteredTotal.value)

const feedPosts = computed(() =>
  featuredPost.value ? paginatedPosts.value.slice(1) : paginatedPosts.value
)

// Number = position in filtered list, descending (newest = highest number)
function getPostNumber(localFeedIndex) {
  const featuredOffset = (currentPage.value === 1 && featuredPost.value) ? 1 : 0
  const filteredIndex = (currentPage.value - 1) * perPage + featuredOffset + localFeedIndex
  return Math.max(1, filteredTotal.value - filteredIndex)
}

const newsletterEvery = 6
function shouldInjectNewsletter(index) {
  if (newsletterAlreadySubscribed.value) return false
  if (newsletterEvery <= 0) return false
  if (index === feedPosts.value.length - 1) return false
  return (index + 1) % newsletterEvery === 0
}

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

/* Staggered row reveal */
.essay-list .essay-row {
  animation: row-rise 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
  animation-delay: var(--row-delay, 0ms);
}
@keyframes row-rise {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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
  .essay-list .essay-row {
    animation: none !important;
  }
}
</style>
