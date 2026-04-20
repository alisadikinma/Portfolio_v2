<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary">

    <!-- ─── Reading Progress Bar ─── -->
    <div v-if="post && !isLoading && !fetchError" class="fixed top-0 left-0 right-0 z-50 h-0.5">
      <div class="h-full bg-accent-gold transition-all duration-100 ease-out" :style="{ width: readingProgress + '%' }"></div>
    </div>

    <!-- ─── Loading ─── -->
    <div v-if="isLoading" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div class="w-10 h-10 border-2 border-accent-gold/30 border-t-accent-gold rounded-full animate-spin mx-auto mb-4"></div>
        <p class="mono-label text-fg-dim">Loading article...</p>
      </div>
    </div>

    <!-- ─── Error / Not Found ─── -->
    <div v-else-if="fetchError" class="flex items-center justify-center min-h-screen px-4">
      <div class="glass-card p-10 max-w-md w-full text-center">
        <div class="w-14 h-14 rounded-full bg-destructive/10 flex items-center justify-center mx-auto mb-6">
          <svg class="w-7 h-7 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <h2 class="section-heading text-2xl font-bold mb-3 text-fg-primary">Post Not Found</h2>
        <p class="text-fg-muted mb-8 text-sm leading-relaxed">
          The blog post you're looking for doesn't exist or has been removed.
        </p>
        <button class="btn-gold text-sm" @click="$router.push('/blog')">
          ← Back to Blog
        </button>
      </div>
    </div>

    <!-- ─── Article ─── -->
    <article v-else-if="post">

      <!-- ─── Hero Header ─── -->
      <header class="relative pt-24 pb-12 overflow-hidden">
        <!-- Background image blur (if available) -->
        <div v-if="post.featured_image" class="pointer-events-none absolute inset-0 overflow-hidden">
          <img
            :src="post.featured_image"
            :alt="post.title"
            class="w-full h-full object-cover opacity-10 scale-110 blur-xl"
          />
          <div class="absolute inset-0 bg-gradient-to-b from-bg-deep/60 via-bg-deep/80 to-bg-deep"></div>
        </div>

        <!-- Ambient orbs -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
          <div class="absolute -top-32 left-1/3 w-96 h-96 rounded-full bg-accent-gold/6 blur-3xl animate-aurora-float"></div>
          <div class="absolute top-10 right-1/4 w-72 h-72 rounded-full bg-accent-cyan/5 blur-3xl animate-aurora-float animate-delay-500"></div>
        </div>

        <div class="container-custom relative">
          <div class="max-w-4xl mx-auto">
            <!-- Back + Category -->
            <div class="flex items-center gap-3 mb-8">
              <button
                class="btn-glass px-4 py-2 text-sm flex items-center gap-2"
                @click="$router.push('/blog')"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Blog
              </button>
              <span
                v-if="post.category?.name"
                class="px-3 py-1 rounded-full text-xs font-mono bg-accent-cyan/15 text-accent-cyan border border-accent-cyan/25"
              >
                {{ post.category.name }}
              </span>
            </div>

            <!-- Title -->
            <h1 class="section-heading text-4xl md:text-5xl lg:text-6xl font-bold text-fg-primary mb-6 leading-tight animate-fade-in-up">
              {{ post.title }}
            </h1>

            <!-- Meta bar -->
            <div class="flex flex-wrap items-center gap-4 text-sm text-fg-muted animate-fade-in-up animate-delay-100">
              <time :datetime="post.published_at" class="mono-label flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-fg-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ formatDate(post.published_at) }}
              </time>
              <span class="text-fg-dim">·</span>
              <span class="mono-label flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-fg-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ estimatedReadTime }} min read
              </span>
              <span class="text-fg-dim">·</span>
              <span class="mono-label flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-fg-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                {{ post.views || 0 }} views
              </span>
            </div>
          </div>
        </div>
      </header>

      <!-- ─── Featured Image ─── -->
      <figure v-if="post.featured_image" class="container-custom mb-12">
        <div class="max-w-4xl mx-auto">
          <div class="rounded-2xl overflow-hidden aspect-video bg-bg-elevated">
            <img
              :src="post.featured_image"
              :alt="post.title"
              class="w-full h-full object-cover"
            />
          </div>
          <figcaption v-if="post.title" class="mt-3 text-center text-sm text-fg-dim mono-label">
            {{ post.title }}
          </figcaption>
        </div>
      </figure>

      <!-- ─── Content Layout — Article + Sticky TOC ─── -->
      <div class="container-custom mb-16" ref="articleContent">
        <div class="grid lg:grid-cols-[240px_minmax(0,720px)] gap-10 max-w-6xl mx-auto lg:justify-center">
          <aside v-if="tocSections.length > 0" class="hidden lg:block">
            <StickyTOC :sections="tocSections" :progress="readingProgress" />
          </aside>
          <div class="max-w-3xl">

          <!-- AI Summary Box -->
          <div
            v-if="post.ai_summary"
            class="rounded-xl p-5 mb-10 bg-accent-cyan/5 border border-accent-cyan/15"
          >
            <div class="flex items-center gap-2 mb-3">
              <svg class="w-4 h-4 text-accent-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
              </svg>
              <span class="text-xs font-mono font-medium text-accent-cyan uppercase tracking-wider">TL;DR</span>
            </div>
            <p class="text-fg-muted text-sm leading-relaxed">{{ post.ai_summary }}</p>
          </div>

          <!-- Excerpt / Lead paragraph -->
          <p v-if="post.excerpt" class="text-xl md:text-2xl text-fg-muted leading-relaxed mb-10 font-light" style="text-wrap: pretty;">
            {{ post.excerpt }}
          </p>

          <!-- Article body with mid-article injections (FAQ stripped — rendered as accordion below) -->
          <BlogContentInjector
            v-if="contentWithoutFaq"
            class="blog-content"
            :html="contentWithoutFaq"
            :promo-slot="promoSlot"
            :related-posts="relatedPosts"
            :lang="lang"
          />

          <!-- Tags -->
          <div v-if="post.tags?.length" class="mt-10 pt-8 border-t border-white/5">
            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in post.tags"
                :key="tag"
                class="px-3 py-1.5 rounded-full text-xs bg-white/5 border border-white/8 text-fg-muted hover:text-accent-cyan hover:border-accent-cyan/30 transition-colors cursor-default"
              >
                #{{ tag }}
              </span>
            </div>
          </div>

          <!-- Share Bar — inline after article -->
          <div class="mt-10 pt-8 border-t border-white/5">
            <p class="mono-label text-fg-dim mb-4 text-xs">Share this article</p>
            <div class="flex gap-2">
              <button @click="shareTwitter" title="Share on X"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white/5 border border-white/8 hover:border-accent-cyan/40 hover:bg-accent-cyan/5 transition-all duration-200 group">
                <svg class="w-4 h-4 text-fg-muted group-hover:text-accent-cyan transition-colors" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
                <span class="text-xs text-fg-muted group-hover:text-fg-primary">X</span>
              </button>
              <button @click="shareLinkedIn" title="Share on LinkedIn"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white/5 border border-white/8 hover:border-accent-cyan/40 hover:bg-accent-cyan/5 transition-all duration-200 group">
                <svg class="w-4 h-4 text-fg-muted group-hover:text-accent-cyan transition-colors" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
                <span class="text-xs text-fg-muted group-hover:text-fg-primary">LinkedIn</span>
              </button>
              <button @click="copyUrl" title="Copy link"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white/5 border border-white/8 hover:border-accent-gold/40 hover:bg-accent-gold/5 transition-all duration-200 group">
                <svg v-if="!urlCopied" class="w-4 h-4 text-fg-muted group-hover:text-accent-gold transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <svg v-else class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="text-xs" :class="urlCopied ? 'text-green-400' : 'text-fg-muted group-hover:text-fg-primary'">
                  {{ urlCopied ? 'Copied' : 'Copy link' }}
                </span>
              </button>
            </div>
          </div>

          <!-- Author Card -->
          <div class="mt-10 pt-8 border-t border-white/5">
            <div class="flex items-start gap-4">
              <div class="w-12 h-12 rounded-full bg-accent-gold/10 border border-accent-gold/20 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <img v-if="post.author?.avatar" :src="post.author.avatar" :alt="post.author?.name" class="w-full h-full object-cover" />
                <svg v-else class="w-6 h-6 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs text-fg-dim mb-0.5">Written by</p>
                <h3 class="font-semibold font-display text-fg-primary mb-1">
                  {{ post.author?.name || 'Ali Sadikin Ma' }}
                </h3>
                <p class="text-fg-muted text-sm leading-relaxed">
                  {{ post.author?.bio || 'AI Generalist Expert. Building intelligent systems and sharing insights on AI, engineering, and the future of work.' }}
                </p>
              </div>
            </div>
          </div>
          </div><!-- /max-w-3xl -->
        </div><!-- /grid -->
      </div><!-- /container-custom -->

      <!-- ─── FAQ Accordion ─── -->
      <FaqAccordion :items="faqItems" />

      <!-- ─── Related Posts — Continue Reading ─── -->
      <section v-if="relatedPosts.length" class="container-custom mb-20">
        <div class="max-w-5xl mx-auto">
          <div class="flex items-center gap-4 mb-8">
            <p class="mono-label text-accent-gold">Continue reading</p>
            <div class="flex-1 h-px bg-white/5"></div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <router-link
              v-for="related in relatedPosts"
              :key="related.id"
              :to="`/${lang}/blog/${related.slug}`"
              target="_blank"
              rel="noopener noreferrer"
              class="group relative rounded-xl overflow-hidden bg-bg-elevated/50 border border-white/5 hover:border-accent-gold/20 transition-all duration-300"
            >
              <!-- External-link hint (appears on hover) -->
              <div class="pointer-events-none absolute top-3 right-3 z-10 w-7 h-7 rounded-full bg-bg-deep/70 backdrop-blur-sm border border-white/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <svg class="w-3.5 h-3.5 text-accent-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </div>
              <div class="aspect-[16/10] bg-bg-elevated overflow-hidden">
                <img
                  v-if="related.featured_image"
                  :src="related.featured_image"
                  :alt="related.title"
                  class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                  loading="lazy"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <svg class="w-8 h-8 text-fg-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
              </div>
              <div class="p-4">
                <span v-if="related.category?.name" class="inline-block mb-2 text-[10px] font-mono uppercase tracking-wider text-accent-cyan">
                  {{ related.category.name }}
                </span>
                <h3 class="font-semibold font-display text-fg-primary text-sm leading-snug group-hover:text-accent-gold transition-colors line-clamp-2 mb-2">
                  {{ related.title }}
                </h3>
                <time :datetime="related.published_at" class="text-[11px] text-fg-dim font-mono">{{ formatDate(related.published_at) }}</time>
              </div>
            </router-link>
          </div>
        </div>
      </section>

    </article>

    <!-- Floating newsletter banner — fires 60s after mount -->
    <NewsletterFloatingBanner
      :show="showFloatingBanner"
      @dismiss="onFloatingBannerDismiss"
      @subscribed="onFloatingBannerSubscribed"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePosts } from '@/composables/usePosts'
import { useMetaTags } from '@/composables/useMetaTags'
import { usePageSections } from '@/composables/usePageSections'
import { useNewsletter } from '@/composables/useNewsletter'
import { useToast } from '@/composables/useToast'
import { stripFaqSection } from '@/utils/stripFaqSection'
import { extractFaqFromHtml } from '@/utils/extractFaqFromHtml'
import FaqAccordion from '@/components/blog/FaqAccordion.vue'
import NewsletterFloatingBanner from '@/components/blog/NewsletterFloatingBanner.vue'
import StickyTOC from '@/components/blog/StickyTOC.vue'
import BlogContentInjector from '@/components/blog/BlogContentInjector.vue'
import { splitHtmlByH2 } from '@/utils/splitHtmlByH2'
import api from '@/services/api'

const route = useRoute()
const toast = useToast()

const { post, isLoadingPost: isLoading, fetchPost } = usePosts()
const { updatePageMeta, updateMetaTag, injectBreadcrumbSchema, injectArticleSchema, updateHreflang } = useMetaTags()
const { fetchActiveSections } = usePageSections()
const { isSubscribed: nlIsSubscribed, isDismissed: nlIsDismissed } = useNewsletter()

// ── State ─────────────────────────────────────────────────────────────────────
const fetchError = ref(null)
const relatedPosts = ref([])
const urlCopied = ref(false)
const articleContent = ref(null)
const readingProgress = ref(0)
const showFloatingBanner = ref(false)
const promoSlot = ref(null)
let floatingBannerTimerId = null

function scheduleFloatingBanner() {
  if (nlIsSubscribed() || nlIsDismissed()) return
  floatingBannerTimerId = setTimeout(() => {
    if (nlIsSubscribed() || nlIsDismissed()) return
    showFloatingBanner.value = true
  }, 60_000)
}

function onFloatingBannerDismiss() {
  showFloatingBanner.value = false
}

function onFloatingBannerSubscribed() {
  showFloatingBanner.value = false
  if (floatingBannerTimerId) {
    clearTimeout(floatingBannerTimerId)
    floatingBannerTimerId = null
  }
}

// Reading progress tracking
const handleScroll = () => {
  if (!articleContent.value) return
  const el = articleContent.value
  const rect = el.getBoundingClientRect()
  const total = el.scrollHeight
  const scrolled = Math.max(0, -rect.top)
  readingProgress.value = Math.min(100, Math.round((scrolled / (total - window.innerHeight)) * 100))
}

// ── Reading time ──────────────────────────────────────────────────────────────
const estimatedReadTime = computed(() => {
  if (!post.value) return 1
  const text = (post.value.content || '') + (post.value.excerpt || '')
  const words = text.replace(/<[^>]+>/g, '').split(/\s+/).filter(Boolean).length
  return Math.max(1, Math.round(words / 200))
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// ── Share actions ─────────────────────────────────────────────────────────────
const shareTwitter = () => {
  const url = encodeURIComponent(window.location.href)
  const text = encodeURIComponent(post.value?.title || '')
  window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'noopener,noreferrer')
}

const shareLinkedIn = () => {
  const url = encodeURIComponent(window.location.href)
  window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${url}`, '_blank', 'noopener,noreferrer')
}

const copyUrl = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href)
    urlCopied.value = true
    toast.success('Link copied to clipboard!')
    setTimeout(() => { urlCopied.value = false }, 2500)
  } catch {
    toast.error('Could not copy link.')
  }
}

// ── FAQ accordion ─────────────────────────────────────────────────────────────
// Primary source: faq_schema JSON-LD populated by article-write plugin
// (mainEntity[].name + mainEntity[].acceptedAnswer.text). For older posts
// predating reliable schema emission, fall back to parsing the FAQ section
// out of the body HTML directly (supports both <h3>Q</h3><p>A</p> and
// <p><strong>Q</strong></p><p>A</p> patterns).
const faqItems = computed(() => {
  const entities = post.value?.seo?.faq_schema?.mainEntity
  if (Array.isArray(entities) && entities.length > 0) {
    const fromSchema = entities
      .filter((e) => typeof e?.name === 'string' && typeof e?.acceptedAnswer?.text === 'string')
      .map((e) => ({ question: e.name, answer: e.acceptedAnswer.text }))
    if (fromSchema.length > 0) return fromSchema
  }
  return extractFaqFromHtml(post.value?.content || '')
})

const contentWithoutFaq = computed(() => {
  const html = post.value?.content
  if (!html || faqItems.value.length === 0) return html
  return stripFaqSection(html)
})

// Sections derived from the live article HTML — powers the StickyTOC.
// Only H2 slots, with the same ids BlogContentInjector writes onto the
// rendered headings so IntersectionObserver hooks up cleanly.
const tocSections = computed(() => {
  const html = contentWithoutFaq.value
  if (!html) return []
  return splitHtmlByH2(html)
    .filter((s) => s.type === 'h2')
    .map((s) => ({ id: s.id, text: s.text }))
})

// ── Thumbnail for OG ──────────────────────────────────────────────────────────
const thumbnailUrl = computed(() => {
  if (!post.value) return ''
  if (post.value.og_image) {
    if (post.value.og_image.startsWith('http')) return post.value.og_image
    const base = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') || ''
    return `${base}${post.value.og_image}`
  }
  return post.value.featured_image || `${window.location.origin}/og-image.jpg`
})

// ── Meta tags ─────────────────────────────────────────────────────────────────
const updateMetaTags = () => {
  if (!post.value) return
  updatePageMeta({
    title: post.value.meta_title || `${post.value.title} | Blog`,
    description: post.value.meta_description || post.value.excerpt,
    image: thumbnailUrl.value,
    url: window.location.href,
    type: 'article',
    // PostResource exposes per-translation SEO under `seo.meta_keywords`
    // (see backend/app/Http/Resources/PostResource.php). Reading
    // `focus_keyword` directly was a typo — that field doesn't exist on
    // the API response, so `keywords` resolved to undefined and
    // updatePageMeta() skipped its keyword override entirely. Result:
    // the global site_settings keywords (Ali's branding chain) leaked
    // onto every blog post page.
    keywords: post.value.seo?.meta_keywords || post.value.meta_keywords || post.value.tags?.join(', ')
  })
  if (post.value.og_title) updateMetaTag('property', 'og:title', post.value.og_title)
  if (post.value.og_description) updateMetaTag('property', 'og:description', post.value.og_description)
  updateMetaTag('property', 'article:published_time', post.value.published_at)
  updateMetaTag('property', 'article:author', post.value.author?.name || '')

  // GEO: Inject BlogPosting JSON-LD with language
  injectArticleSchema(post.value, lang.value)

  // SEO: Inject hreflang alternate links
  updateHreflang(lang.value, post.value.slug, post.value.available_translations || [lang.value])

  // GEO: Inject BreadcrumbList
  const currentLang = lang.value
  const breadcrumbs = [
    { name: 'Home', url: `${window.location.origin}/${currentLang}` },
    { name: 'Blog', url: `${window.location.origin}/${currentLang}/blog` }
  ]
  if (post.value.category?.name) {
    breadcrumbs.push({
      name: post.value.category.name,
      url: `${window.location.origin}/blog/category/${post.value.category.slug}`
    })
  }
  breadcrumbs.push({ name: post.value.title, url: window.location.href })
  injectBreadcrumbSchema(breadcrumbs)
}

// ── Related posts ─────────────────────────────────────────────────────────────
const fetchRelatedPosts = async () => {
  if (!post.value) return
  try {
    const params = {}
    if (post.value.category?.id) params.category_id = post.value.category.id
    const res = await api.get('/posts', { params })
    const all = res.data?.data || []
    relatedPosts.value = all
      .filter(p => p.id !== post.value.id)
      .slice(0, 3)
  } catch {
    // related posts are non-critical
  }
}

// ── Watch post changes ────────────────────────────────────────────────────────
watch(
  () => post.value,
  (newPost) => {
    if (newPost) {
      updateMetaTags()
      fetchRelatedPosts()
    }
  }
)

// ── Mount ─────────────────────────────────────────────────────────────────────
const lang = computed(() => route.params.lang || 'en')

onMounted(async () => {
  window.addEventListener('scroll', handleScroll, { passive: true })

  await fetchActiveSections('blog')

  const slug = route.params.slug
  const result = await fetchPost(slug, lang.value)

  if (!result.success) {
    fetchError.value = result.error || 'Post not found'
  } else if (post.value) {
    updateMetaTags()
    await fetchRelatedPosts()
    fetchPromoSlot()
    scheduleFloatingBanner()
  }
})

async function fetchPromoSlot() {
  try {
    const res = await api.get('/blog/promo-slot')
    promoSlot.value = res.data?.data || null
  } catch {
    promoSlot.value = null
  }
}

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  if (floatingBannerTimerId) {
    clearTimeout(floatingBannerTimerId)
    floatingBannerTimerId = null
  }
})

// Refetch when language changes (user switches via LanguageSwitcher)
watch(lang, async (newLang) => {
  const slug = route.params.slug
  if (slug) {
    const result = await fetchPost(slug, newLang)
    if (result.success && post.value) {
      updateMetaTags()
    }
  }
})
</script>

<style scoped>
/* ── Dark Cinematic Article Typography ── */
.blog-content {
  color: #EDEDEF;
  line-height: 1.85;
  font-size: 1.0625rem;
}

.blog-content :deep(h2) {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.625rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-top: 2.5rem;
  margin-bottom: 1rem;
  color: #EDEDEF;
}

.blog-content :deep(h3) {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 2rem;
  margin-bottom: 0.75rem;
  color: #EDEDEF;
}

.blog-content :deep(h4) {
  font-size: 1.05rem;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
  color: #D4D4D8;
}

.blog-content :deep(p) {
  margin-bottom: 1.25rem;
  color: #A1A1AA;
}

.blog-content :deep(ul),
.blog-content :deep(ol) {
  padding-left: 1.5rem;
  margin-bottom: 1.25rem;
  color: #A1A1AA;
}

.blog-content :deep(li) {
  margin-bottom: 0.5rem;
}

.blog-content :deep(a) {
  color: #06B6D4;
  text-decoration: underline;
  text-underline-offset: 3px;
  text-decoration-color: rgba(6, 182, 212, 0.4);
  transition: color 0.2s, text-decoration-color 0.2s;
}

.blog-content :deep(a:hover) {
  color: #D4A843;
  text-decoration-color: rgba(212, 168, 67, 0.5);
}

.blog-content :deep(blockquote) {
  border-left: 3px solid #D4A843;
  padding: 0.75rem 1.25rem;
  margin: 1.5rem 0;
  background: rgba(212, 168, 67, 0.05);
  border-radius: 0 8px 8px 0;
  color: #8A8F98;
  font-style: italic;
}

.blog-content :deep(code) {
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.85em;
  padding: 0.15em 0.45em;
  background: rgba(255, 255, 255, 0.07);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 5px;
  color: #06B6D4;
}

.blog-content :deep(pre) {
  background: #0C0C0F;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 1.25rem;
  overflow-x: auto;
  margin: 1.5rem 0;
}

.blog-content :deep(pre code) {
  background: transparent;
  border: none;
  padding: 0;
  color: #EDEDEF;
  font-size: 0.875rem;
}

.blog-content :deep(img) {
  max-width: 100%;
  border-radius: 12px;
  margin: 1.5rem 0;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.blog-content :deep(hr) {
  border: none;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  margin: 2.5rem 0;
}

.blog-content :deep(strong) {
  color: #EDEDEF;
  font-weight: 600;
}

.blog-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
}

.blog-content :deep(th) {
  padding: 0.75rem 1rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  text-align: left;
  font-family: 'JetBrains Mono', monospace;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #8A8F98;
}

.blog-content :deep(td) {
  padding: 0.75rem 1rem;
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: #A1A1AA;
  font-size: 0.9375rem;
}
</style>
