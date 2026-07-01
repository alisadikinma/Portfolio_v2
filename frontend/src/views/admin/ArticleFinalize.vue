<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'
import { resolveImagePosition } from '@/utils/imagePositioning'

const route = useRoute()
const router = useRouter()
const { getIdea, approveAndPublish, translateArticle, isLoading } = useContentEngine()
const toast = useToast()

const idea = ref(null)
const loadError = ref(null)
const activeLang = ref('id')
const publishing = ref(false)
// Set true when the user makes a change on this page (e.g. re-runs translation)
// after the idea was already published — re-enables Publish so the updated
// content can be re-published. Reset on a fresh load.
const dirty = ref(false)

// Translation state
const translating = ref(false)
const translationError = ref(null)
const translationStartedAt = ref(null)
const translationElapsed = ref(0)
let translationTimer = null

function startTranslationTimer() {
  translationStartedAt.value = Date.now()
  translationElapsed.value = 0
  if (translationTimer) clearInterval(translationTimer)
  translationTimer = setInterval(() => {
    translationElapsed.value = Math.floor((Date.now() - translationStartedAt.value) / 1000)
  }, 1000)
}

function stopTranslationTimer() {
  if (translationTimer) {
    clearInterval(translationTimer)
    translationTimer = null
  }
  translationStartedAt.value = null
}

// The translate is an SSH → Claude CLI call that runs 90-300s+ — well past
// Cloudflare's ~100s edge timeout. So we kick it off (backend returns 202 +
// dispatches a queued job) then POLL generated_article.translation_status
// instead of holding one long request open (which always 524'd).
const POLL_MS = 5000
const MAX_POLLS = 130 // ~650s, past the 600s job timeout

async function runTranslation() {
  if (translating.value) return
  translating.value = true
  translationError.value = null
  startTranslationTimer()

  const id = idea.value.id
  try {
    const kickoff = await translateArticle(id)
    if (!kickoff.success) {
      translationError.value = kickoff.error || 'Failed to start translation.'
      translating.value = false
      stopTranslationTimer()
      return
    }
    if (kickoff.data) idea.value = kickoff.data

    // Already done (e.g. "already translated" short-circuit) — no need to poll.
    if (kickoff.data?.generated_article?.translation_status === 'done') {
      dirty.value = true
      toast.success('English translation ready.')
      translating.value = false
      stopTranslationTimer()
      return
    }

    for (let i = 0; i < MAX_POLLS; i++) {
      await new Promise((r) => setTimeout(r, POLL_MS))
      // Stop if the user navigated away / started another run.
      if (!translating.value) return

      const poll = await getIdea(id)
      if (!poll.success || !poll.data) continue // transient — keep polling

      const status = poll.data?.generated_article?.translation_status
      if (status === 'done') {
        idea.value = poll.data
        dirty.value = true
        toast.success('English translation ready.')
        translating.value = false
        stopTranslationTimer()
        return
      }
      if (status === 'failed') {
        idea.value = poll.data
        translationError.value =
          poll.data?.generated_article?.translation_error || 'Translation failed.'
        translating.value = false
        stopTranslationTimer()
        return
      }
      // status === 'translating' → keep waiting
    }
    translationError.value = 'Translation timed out. Click Retry to try again.'
  } catch (e) {
    translationError.value = e?.message || 'Translation failed.'
  } finally {
    translating.value = false
    stopTranslationTimer()
  }
}

onMounted(async () => {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
    const art = result.data?.generated_article
    // Prefer 'id' if available, fall back to 'en' if only English exists
    if (!art?.id?.content && art?.en?.content) {
      activeLang.value = 'en'
    }
    // Auto-kick off English translation if it's missing or a duplicate of
    // the primary language. Publish is gated until this completes.
    const primaryContent = art?.id?.content || ''
    const enContent = art?.en?.content || ''
    const enMissingOrDup = !enContent || enContent === primaryContent
    const alreadyRunning = art?.translation_status === 'translating'
    if (enMissingOrDup && !alreadyRunning) {
      runTranslation()
    }
  } else {
    loadError.value = result.error || 'Failed to load content idea'
  }
})

const article = computed(() => idea.value?.generated_article || null)

const availableLanguages = computed(() => {
  // Both tabs are always reachable so the user can see translation progress
  // / failure / retry UI on the EN tab even when EN content doesn't exist yet.
  if (!article.value) return ['id', 'en']
  const hasId = !!(article.value.id || article.value.title)
  const hasEn = !!article.value.en
  if (hasId && !hasEn) return ['id', 'en']
  if (hasEn && !hasId) return ['en']
  return ['id', 'en']
})

function getArticleContent(art, lang) {
  if (art?.[lang]) return { title: art[lang].title || '', content: art[lang].content || '' }
  if (art?.title) return { title: art.title || '', content: art.content || '' }
  return { title: '', content: '' }
}

const currentContent = computed(() => {
  if (!article.value) return { title: '', content: '' }
  return getArticleContent(article.value, activeLang.value)
})

const imagePrompts = computed(() => article.value?.image_prompts || [])

// Build content blocks with real images inserted at positions
const contentWithImages = computed(() => {
  const html = currentContent.value.content || ''
  if (!html) return ''

  // Parse to DOM once for link-target mutation + block extraction
  const parser = new DOMParser()
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html')
  doc.querySelectorAll('a').forEach(a => {
    a.setAttribute('target', '_blank')
    a.setAttribute('rel', 'noopener noreferrer')
  })
  const childElements = Array.from(doc.body.firstChild?.children || [])
  const blocks = childElements.map(el => el.outerHTML)

  // Resolve each body image's position using the same logic Article step uses.
  // Filter to body images only (cover renders separately) with a generated URL.
  // Pass the full imagePrompts length (not filtered count) so the even-distribute
  // fallback produces positions identical to ArticlePreview's initFromArticle.
  const bodyImages = imagePrompts.value.filter(img => img.generated_url && img.type !== 'cover')
  const totalAll = imagePrompts.value.length

  const positioned = bodyImages.map(img => {
    const origIndex = imagePrompts.value.indexOf(img)
    const pos = resolveImagePosition(img, origIndex, totalAll, childElements)
    return { img, pos }
  })

  // Splice in descending position order to keep indices stable
  positioned.sort((a, b) => b.pos - a.pos)

  for (const { img, pos } of positioned) {
    const imgHtml = `<figure class="my-8 not-prose"><img src="${img.generated_url}" alt="${img.concept || ''}" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-neutral-500 dark:text-neutral-400 mt-2 text-center">${img.concept || ''}</figcaption></figure>`
    const safePos = Math.max(0, Math.min(pos, blocks.length))
    blocks.splice(safePos, 0, imgHtml)
  }

  return blocks.join('\n')
})

const coverImage = computed(() => {
  const cover = imagePrompts.value.find(img => img.type === 'cover')
  return cover?.generated_url || ''
})

const isUntranslated = computed(() => {
  if (activeLang.value !== 'en') return false
  const en = article.value?.en?.content || ''
  const id = article.value?.id?.content || ''
  return !en || en === id
})

// True when EN is missing/duplicate — used to gate the Publish button regardless
// of which tab the user is looking at. Publish requires a real EN translation.
const translationMissing = computed(() => {
  const en = article.value?.en?.content || ''
  const id = article.value?.id?.content || ''
  return !en || en === id
})

// Idea already shipped to the blog (FSM terminal state). Re-publishing is only
// meaningful after a fresh modification on this page (tracked via `dirty`).
const alreadyPublished = computed(() => idea.value?.status === 'completed')

const canPublish = computed(() =>
  !publishing.value &&
  !translating.value &&
  !translationMissing.value &&
  (!alreadyPublished.value || dirty.value)
)

const publishButtonLabel = computed(() => {
  if (publishing.value) return 'Publishing...'
  if (translating.value) return 'Waiting for English translation...'
  if (translationMissing.value) return 'Translation needed to publish'
  if (alreadyPublished.value && !dirty.value) return 'Already Published'
  if (alreadyPublished.value && dirty.value) return 'Re-publish to Blog'
  return 'Publish to Blog'
})

const hasActiveContent = computed(() => !!currentContent.value.content)

async function handlePublish() {
  if (!idea.value) return
  publishing.value = true
  const result = await approveAndPublish(idea.value.id)
  publishing.value = false

  if (result.success) {
    if (result.data?.translation_pending) {
      toast.success('Published — English translation in progress (auto-retry)')
    } else {
      toast.success('Article published to blog!')
    }
    // Open the public blog post in a new tab so the user can verify render.
    const slug = result.data?.post_slug
    if (slug) {
      window.open(`/blog/${slug}`, '_blank', 'noopener')
    }
    router.push({ name: 'admin-content-engine' })
  } else {
    toast.error(result.error || 'Failed to publish')
  }
}
</script>

<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
    <!-- Loading -->
    <div v-if="isLoading && !idea" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 mx-auto text-amber-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Loading finalize...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="flex items-center justify-center min-h-screen">
      <div class="text-center max-w-md">
        <p class="text-red-600 dark:text-red-400 font-medium mb-2">Failed to load</p>
        <p class="text-sm text-neutral-500">{{ loadError }}</p>
      </div>
    </div>

    <!-- Main -->
    <template v-else-if="idea && article">
      <!-- Step Bar -->
      <PipelineStepBar :current-step="3" :idea-id="route.params.id" :idea-status="idea.status" />

      <!-- Persistent translation-progress pill (visible on any tab) -->
      <div v-if="translating" class="max-w-3xl mx-auto px-4 sm:px-6 pt-4">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
          <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
          <span>Translating to English</span>
          <span class="font-mono text-[11px] opacity-75">{{ translationElapsed }}s</span>
        </div>
      </div>

      <!-- Language Tabs -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-6">
        <div class="flex gap-1 border-b border-neutral-200 dark:border-neutral-700">
          <button
            v-for="lang in availableLanguages"
            :key="lang"
            @click="activeLang = lang"
            :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px', activeLang === lang ? 'border-amber-500 text-amber-700 dark:text-amber-400' : 'border-transparent text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300']"
          >
            <span v-if="lang === 'en'">&#127482;&#127480; English</span>
            <span v-else-if="lang === 'id'">&#127470;&#127465; Indonesia</span>
          </button>
        </div>
      </div>

      <!-- WYSIWYG Article -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
        <!-- Translation in progress (English tab) -->
        <div v-if="activeLang === 'en' && translating" class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-6">
          <div class="flex items-start gap-3 mb-4">
            <svg class="animate-spin h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <div class="text-sm text-amber-800 dark:text-amber-300">
              <p class="font-semibold text-base">Menerjemahkan ke Bahasa Inggris...</p>
              <p class="mt-1">Claude via VPS sedang memproses. Biasanya 30-60 detik. Jangan tutup halaman ini.</p>
            </div>
          </div>
          <div class="w-full bg-amber-200/50 dark:bg-amber-800/30 rounded-full h-1.5 overflow-hidden">
            <div class="bg-amber-500 dark:bg-amber-400 h-1.5 rounded-full animate-pulse" :style="{ width: Math.min(100, (translationElapsed / 120) * 100) + '%' }"></div>
          </div>
          <p class="mt-2 text-xs font-mono text-amber-700 dark:text-amber-400">
            {{ translationElapsed }}s elapsed<span v-if="translationElapsed < 120"> &middot; ~{{ 120 - translationElapsed }}s remaining</span><span v-else> &middot; taking longer than usual...</span>
          </p>
        </div>

        <!-- Translation failed (English tab) -->
        <div v-else-if="activeLang === 'en' && translationError" class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-5">
          <div class="flex items-start gap-3 mb-3">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.2 16a2 2 0 001.73 3z"/></svg>
            <div class="text-sm text-red-800 dark:text-red-300">
              <p class="font-semibold">Translation failed</p>
              <p class="mt-1 break-words">{{ translationError }}</p>
            </div>
          </div>
          <button @click="runTranslation" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Retry translation
          </button>
        </div>

        <!-- Untranslated (English tab) — happens briefly before auto-trigger fires -->
        <div v-else-if="isUntranslated" class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-5 flex items-start gap-3">
          <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
          </svg>
          <div class="text-sm text-amber-800 dark:text-amber-300">
            <p class="font-medium">Belum diterjemahkan.</p>
            <button @click="runTranslation" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-amber-600 hover:bg-amber-700 text-white transition-colors">
              Translate to English
            </button>
          </div>
        </div>

        <!-- Empty-content fallback (e.g. ID tab with no Indonesian content) -->
        <div v-else-if="!hasActiveContent" class="rounded-lg bg-neutral-100 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 p-5 text-sm text-neutral-600 dark:text-neutral-400">
          Konten untuk bahasa ini belum tersedia.
        </div>

        <!-- Translated content -->
        <template v-else>
          <!-- Cover Image -->
          <div v-if="coverImage" class="mb-8 rounded-xl overflow-hidden">
            <img :src="coverImage" :alt="currentContent.title" class="w-full aspect-video object-cover" />
          </div>

          <!-- Title -->
          <h1 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-8">
            {{ currentContent.title }}
          </h1>

          <!-- Article body with images -->
          <div class="prose dark:prose-invert prose-lg max-w-none prose-headings:font-bold prose-a:text-cyan-600 dark:prose-a:text-cyan-400 prose-blockquote:border-l-amber-500" v-html="contentWithImages">
          </div>
        </template>
      </div>

      <!-- Bottom Bar -->
      <div class="sticky bottom-0 z-30 bg-white dark:bg-neutral-800 border-t border-neutral-200 dark:border-neutral-700 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <button @click="router.push({ name: 'admin-content-engine' })" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
              Back to Content Engine
            </button>
            <button @click="router.push(`/admin/content-engine/${idea.id}/images`)" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
              Back to Images
            </button>
          </div>
          <button
            @click="handlePublish"
            :disabled="!canPublish"
            :title="translating ? 'English translation in progress — publish will enable when complete.' : (translationMissing ? 'Run English translation before publishing.' : (alreadyPublished && !dirty ? 'Already published — modify the article to re-publish.' : ''))"
            :class="[
              'inline-flex items-center gap-1.5 px-5 py-2 text-sm font-medium rounded-lg text-white transition-colors disabled:opacity-70 disabled:cursor-not-allowed',
              translating ? 'bg-amber-500 hover:bg-amber-500' :
              translationMissing ? 'bg-neutral-400 hover:bg-neutral-400' :
              (alreadyPublished && !dirty) ? 'bg-neutral-400 hover:bg-neutral-400' :
              'bg-green-600 hover:bg-green-700',
            ]"
          >
            <svg v-if="translating" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <svg v-if="alreadyPublished && !dirty" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ publishButtonLabel }}
            <svg v-if="!translating && !translationMissing && !(alreadyPublished && !dirty)" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
