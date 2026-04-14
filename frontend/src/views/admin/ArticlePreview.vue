<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'

const route = useRoute()
const router = useRouter()
const { getIdea, approveArticle, regenerateArticle, isLoading } = useContentEngine()
const toast = useToast()

// ── State ──
const idea = ref(null)
const loadError = ref(null)
const activeLang = ref('id')
const editedTitles = ref({ en: '', id: '' })
const targetKeyword = ref('')
const showSeoPanel = ref(false)
const imageMarkers = ref([])
const approving = ref(false)
const showRegenConfirm = ref(false)
const regenerating = ref(false)
const regenInstructions = ref('')

// ── Load idea on mount ──
onMounted(async () => {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
    initFromArticle()
  } else {
    loadError.value = result.error || 'Failed to load content idea'
  }
})

function initFromArticle() {
  const article = idea.value?.generated_article
  if (!article) return

  // Auto-detect active language: pick first language that has content
  if (article.id?.content) activeLang.value = 'id'
  else if (article.en?.content) activeLang.value = 'en'
  else if (article.content) activeLang.value = 'en'

  // Initialize editable titles from nested or flat format
  const en = getArticleContent(article, 'en')
  const id = getArticleContent(article, 'id')
  editedTitles.value = { en: en.title, id: id.title }
  targetKeyword.value = article.target_keyword || ''

  // Initialize image markers with positions
  imageMarkers.value = (article.image_prompts || []).map((img, i) => ({
    ...img,
    position: img.suggested_position ?? i,
    removed: false,
  }))
}

// ── Backward-compatible content getter ──
function getArticleContent(article, lang) {
  if (article?.[lang]) {
    return {
      title: article[lang].title || '',
      content: article[lang].content || '',
      wordCount: article[lang].word_count || 0,
    }
  }
  // Legacy flat format — treat as English
  if (article?.title) {
    return {
      title: article.title || '',
      content: article.content || '',
      wordCount: article.word_count || 0,
    }
  }
  return { title: '', content: '', wordCount: 0 }
}

// ── Computed ──
const article = computed(() => idea.value?.generated_article || null)

const availableLanguages = computed(() => {
  if (!article.value) return ['en']
  const langs = []
  if (article.value.en || article.value.title) langs.push('en')
  if (article.value.id) langs.push('id')
  if (langs.length === 0) langs.push('en')
  return langs
})

const currentContent = computed(() => {
  if (!article.value) return { title: '', content: '', wordCount: 0 }
  return getArticleContent(article.value, activeLang.value)
})

const currentTitle = computed({
  get: () => editedTitles.value[activeLang.value] || '',
  set: (val) => { editedTitles.value[activeLang.value] = val },
})

const titleCharCount = computed(() => currentTitle.value.length)

const titleLengthStatus = computed(() => {
  const len = titleCharCount.value
  if (len >= 50 && len <= 60) return 'good'
  if ((len >= 40 && len < 50) || (len > 60 && len <= 70)) return 'warn'
  return 'bad'
})

// ── SEO Analysis ──
const seoAnalysis = computed(() => {
  const content = currentContent.value.content
  const title = currentTitle.value
  const keyword = targetKeyword.value
  if (!keyword || !content) return null
  return computeSeoAnalysis(content, title, keyword)
})

function computeSeoAnalysis(content, title, keyword) {
  const text = content.replace(/<[^>]*>/g, '')
  const words = text.split(/\s+/).filter(Boolean)
  const kw = keyword.toLowerCase()
  const kwRegex = new RegExp(escapeRegex(kw), 'gi')

  const bodyMatches = text.match(kwRegex) || []
  const density = words.length ? (bodyMatches.length / words.length) * 100 : 0

  const inTitle = title.toLowerCase().includes(kw)
  const first100 = words.slice(0, 100).join(' ').toLowerCase()
  const inFirst100 = first100.includes(kw)

  const headingRegex = /<h[23][^>]*>(.*?)<\/h[23]>/gi
  let headingCount = 0
  let m
  while ((m = headingRegex.exec(content)) !== null) {
    if (m[1].toLowerCase().includes(kw)) headingCount++
  }

  return {
    density: { value: +density.toFixed(1), status: seoStatus(density, 0.5, 1.5, 0.3, 2.5) },
    inTitle: { value: inTitle, status: inTitle ? 'good' : 'bad' },
    inFirst100: { value: inFirst100, status: inFirst100 ? 'good' : 'bad' },
    inHeadings: { value: headingCount, status: seoStatus(headingCount, 1, 2, 0, 4) },
    titleLength: { value: title.length, status: seoStatus(title.length, 50, 60, 40, 70) },
    titleWords: { value: title.split(/\s+/).length, status: seoStatus(title.split(/\s+/).length, 6, 10, 5, 12) },
    totalWords: words.length,
    keywordCount: bodyMatches.length,
  }
}

function seoStatus(val, goodMin, goodMax, warnMin, warnMax) {
  if (val >= goodMin && val <= goodMax) return 'good'
  if (val >= warnMin && val <= warnMax) return 'warn'
  return 'bad'
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// ── Content blocks with image markers ──
const contentBlocks = computed(() => {
  const html = currentContent.value.content || ''
  if (!html) return []
  const parser = new DOMParser()
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html')
  // Make all links open in new tab
  doc.querySelectorAll('a').forEach(a => {
    a.setAttribute('target', '_blank')
    a.setAttribute('rel', 'noopener noreferrer')
  })
  const children = Array.from(doc.body.firstChild?.children || [])
  return children.map((el, i) => ({ index: i, html: el.outerHTML }))
})

const activeMarkers = computed(() =>
  imageMarkers.value.filter(m => !m.removed)
)

function markersAtPosition(pos) {
  return activeMarkers.value.filter(m => m.position === pos)
}

function moveMarker(markerIndex, direction) {
  const marker = imageMarkers.value[markerIndex]
  if (!marker || marker.removed) return
  const maxPos = contentBlocks.value.length
  const newPos = marker.position + direction
  if (newPos < 0 || newPos > maxPos) return
  marker.position = newPos
}

function removeMarker(markerIndex) {
  imageMarkers.value[markerIndex].removed = true
}

// ── Score accessors (backward compat with flat + nested) ──
const qualityScore = computed(() => article.value?.quality_gate?.score ?? article.value?.quality_score ?? 0)
const qualityMax = computed(() => article.value?.quality_gate?.max_score ?? 10)
const viralityScoreVal = computed(() => article.value?.virality_score?.score ?? article.value?.virality_score_value ?? (typeof article.value?.virality_score === 'number' ? article.value.virality_score : 0))
const viralityMax = computed(() => article.value?.virality_score?.max_score ?? 5)
const seoScoreVal = computed(() => article.value?.seo_analysis?.score ?? article.value?.seo_score ?? null)
const seoMax = computed(() => article.value?.seo_analysis?.max_score ?? 6)
const qualityGate = computed(() => article.value?.quality_gate ?? null)
const viralityGate = computed(() => (typeof article.value?.virality_score === 'object' ? article.value.virality_score : null))
const serverSeo = computed(() => article.value?.seo_analysis ?? null)

function scoreColor(score, max) {
  const ratio = score / max
  if (ratio >= 0.7) return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  if (ratio >= 0.5) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
  return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
}

function seoIndicatorClass(status) {
  if (status === 'good') return 'text-green-600 dark:text-green-400'
  if (status === 'warn') return 'text-amber-600 dark:text-amber-400'
  return 'text-red-600 dark:text-red-400'
}

function seoIcon(status) {
  if (status === 'good') return '\u2713'
  if (status === 'warn') return '\u26A0'
  return '\u2717'
}

// ── Actions ──
async function handleRegenerate() {
  if (!idea.value) return
  regenerating.value = true

  const payload = {}
  if (regenInstructions.value.trim()) {
    payload.instructions = regenInstructions.value.trim()
  }

  const result = await regenerateArticle(idea.value.id, payload)

  regenerating.value = false
  showRegenConfirm.value = false
  regenInstructions.value = ''

  if (result.success) {
    toast.success('Article regeneration started! Redirecting to Content Engine...')
    router.push({ name: 'admin-content-engine' })
  } else {
    toast.error(result.error || 'Failed to start regeneration')
  }
}

async function handleApprove() {
  if (!idea.value) return
  approving.value = true

  // Build updated generated_article with edited titles + image positions
  const updatedArticle = { ...article.value }

  // Update titles per language
  for (const lang of availableLanguages.value) {
    if (updatedArticle[lang]) {
      updatedArticle[lang] = { ...updatedArticle[lang], title: editedTitles.value[lang] }
    } else if (lang === 'en' && updatedArticle.title) {
      updatedArticle.title = editedTitles.value.en
    }
  }

  // Update target keyword
  updatedArticle.target_keyword = targetKeyword.value

  // Update image positions
  updatedArticle.image_prompts = imageMarkers.value
    .filter(m => !m.removed)
    .map(m => {
      const { removed, ...rest } = m
      return rest
    })

  const result = await approveArticle(idea.value.id, {
    generated_article: updatedArticle,
  })

  approving.value = false

  if (result.success) {
    toast.success('Article approved! Proceeding to image generation.')
    router.push(`/admin/content-engine/${idea.value.id}/images`)
    return
  } else {
    toast.error(result.error || 'Failed to approve article')
  }
}

</script>

<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
    <!-- Loading -->
    <div v-if="isLoading && !idea" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 mx-auto text-amber-500 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">Loading article preview...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="flex items-center justify-center min-h-screen">
      <div class="text-center max-w-md">
        <p class="text-red-600 dark:text-red-400 font-medium mb-2">Failed to load article</p>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ loadError }}</p>
        <button @click="window.close()" class="mt-4 px-4 py-2 text-sm rounded-lg bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-300 dark:hover:bg-neutral-600">
          Close Tab
        </button>
      </div>
    </div>

    <!-- Main Content -->
    <template v-else-if="idea && article">
      <!-- Step Bar -->
      <PipelineStepBar :current-step="1" :idea-id="route.params.id" :idea-status="idea.status" />

      <!-- Sticky Top Bar -->
      <div class="sticky top-0 z-40 bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
          <!-- Left: Back -->
          <button @click="$router.push({ name: 'admin-content-engine' })" class="inline-flex items-center gap-1.5 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Content Engine
          </button>

          <!-- Center: Score Badges -->
          <div class="hidden sm:flex items-center gap-2 text-xs font-medium">
            <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full', scoreColor(qualityScore, qualityMax)]">
              QS {{ qualityScore }}/{{ qualityMax }}
            </span>
            <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full', scoreColor(viralityScoreVal, viralityMax)]">
              VS {{ viralityScoreVal }}/{{ viralityMax }}
            </span>
            <span v-if="seoScoreVal !== null" :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full', scoreColor(seoScoreVal, seoMax)]">
              SEO {{ seoScoreVal }}/{{ seoMax }}
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-neutral-300 dark:border-neutral-600 text-neutral-500 dark:text-neutral-400">
              {{ currentContent.wordCount?.toLocaleString() || '—' }} words
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-cyan-400/40 text-cyan-600 dark:border-cyan-500/30 dark:text-cyan-400">
              {{ article.framework }}
            </span>
            <span v-if="article.hook_boost" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-purple-400/40 text-purple-600 dark:border-purple-500/30 dark:text-purple-400">
              {{ article.hook_type }} {{ article.hook_boost }}
            </span>
            <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-purple-400/40 text-purple-600 dark:border-purple-500/30 dark:text-purple-400">
              {{ article.hook_type }}
            </span>
          </div>

          <!-- Right: Regenerate + SEO toggle + Approve -->
          <div class="flex items-center gap-2">
            <button @click="showRegenConfirm = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 hover:border-cyan-400 hover:text-cyan-600 dark:hover:border-cyan-500 dark:hover:text-cyan-400 transition-colors" title="Regenerate article with improved plugin">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.016 4.657v4.992"/></svg>
              <span class="hidden md:inline">Regenerate</span>
            </button>
            <button @click="showSeoPanel = !showSeoPanel" :class="['inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors', showSeoPanel ? 'border-amber-400 bg-amber-50 text-amber-700 dark:border-amber-600 dark:bg-amber-900/20 dark:text-amber-400' : 'border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700']">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              SEO
            </button>
            <button @click="handleApprove" :disabled="approving" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
              <template v-if="approving">Approving...</template>
              <template v-else>Approve &amp; Continue</template>
            </button>
          </div>
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
            <span v-else>{{ lang.toUpperCase() }}</span>
          </button>
        </div>
      </div>

      <!-- Title Editor -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 pt-6 pb-2">
        <input
          v-model="currentTitle"
          type="text"
          class="w-full text-2xl font-bold bg-transparent border-0 border-b-2 border-neutral-200 dark:border-neutral-700 focus:border-amber-500 dark:focus:border-amber-500 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:ring-0 pb-2 placeholder-neutral-400"
          placeholder="Article title..."
        />
        <div class="flex items-center gap-3 mt-2 text-xs">
          <span :class="[titleLengthStatus === 'good' ? 'text-green-600 dark:text-green-400' : titleLengthStatus === 'warn' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400']">
            {{ titleCharCount }}/60 chars
          </span>
          <span class="text-neutral-400">|</span>
          <span class="text-neutral-500 dark:text-neutral-400">
            {{ currentTitle.split(/\s+/).filter(Boolean).length }} words
          </span>
          <template v-if="targetKeyword">
            <span class="text-neutral-400">|</span>
            <span :class="currentTitle.toLowerCase().includes(targetKeyword.toLowerCase()) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
              {{ currentTitle.toLowerCase().includes(targetKeyword.toLowerCase()) ? '\u2713' : '\u2717' }} Keyword in title
            </span>
          </template>
        </div>
      </div>

      <!-- Article Body with Image Placeholders -->
      <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
        <div class="prose dark:prose-invert prose-lg max-w-none prose-headings:font-bold prose-a:text-cyan-600 dark:prose-a:text-cyan-400 prose-blockquote:border-l-amber-500">
          <template v-for="(block, blockIndex) in contentBlocks" :key="blockIndex">
            <!-- Image placeholders BEFORE this block -->
            <div
              v-for="marker in markersAtPosition(blockIndex)"
              :key="'marker-' + imageMarkers.indexOf(marker)"
              class="not-prose my-6"
            >
              <div
                :class="['relative rounded-xl overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/80', marker.aspect_ratio === '16:9' ? 'aspect-video' : 'aspect-[4/3]']"
              >
                <!-- Placeholder visual -->
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-4">
                  <svg class="w-12 h-12 text-neutral-300 dark:text-neutral-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                  </svg>
                  <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400 text-center max-w-xs">{{ marker.concept }}</span>
                  <div class="flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
                    <span class="px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700">{{ marker.aspect_ratio }}</span>
                    <span class="px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700">{{ marker.style }}</span>
                  </div>
                </div>

                <!-- Controls overlay -->
                <div class="absolute top-2 right-2 flex items-center gap-1">
                  <button @click="moveMarker(imageMarkers.indexOf(marker), -1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-black/50 text-white/80 hover:bg-black/70 hover:text-white backdrop-blur-sm transition-colors" title="Move up">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                  </button>
                  <button @click="moveMarker(imageMarkers.indexOf(marker), 1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-black/50 text-white/80 hover:bg-black/70 hover:text-white backdrop-blur-sm transition-colors" title="Move down">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                  </button>
                  <button @click="removeMarker(imageMarkers.indexOf(marker))" class="w-7 h-7 flex items-center justify-center rounded-md bg-red-500/70 text-white hover:bg-red-600/90 backdrop-blur-sm transition-colors" title="Remove image">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>

                <!-- Position badge -->
                <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-black/50 text-white/80 text-xs backdrop-blur-sm">
                  Image {{ imageMarkers.indexOf(marker) + 1 }} of {{ activeMarkers.length }}
                </div>
              </div>
            </div>

            <!-- Content block -->
            <div v-html="block.html"></div>
          </template>

          <!-- Image placeholders AFTER last block -->
          <div
            v-for="marker in markersAtPosition(contentBlocks.length)"
            :key="'marker-end-' + imageMarkers.indexOf(marker)"
            class="not-prose my-6"
          >
            <div
              :class="['relative rounded-xl overflow-hidden border border-neutral-200 dark:border-neutral-700 bg-neutral-100 dark:bg-neutral-800/80', marker.aspect_ratio === '16:9' ? 'aspect-video' : 'aspect-[4/3]']"
            >
              <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-4">
                <svg class="w-12 h-12 text-neutral-300 dark:text-neutral-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                </svg>
                <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400 text-center max-w-xs">{{ marker.concept }}</span>
                <div class="flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
                  <span class="px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700">{{ marker.aspect_ratio }}</span>
                  <span class="px-2 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700">{{ marker.style }}</span>
                </div>
              </div>
              <div class="absolute top-2 right-2 flex items-center gap-1">
                <button @click="moveMarker(imageMarkers.indexOf(marker), -1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-black/50 text-white/80 hover:bg-black/70 hover:text-white backdrop-blur-sm transition-colors" title="Move up">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                </button>
                <button @click="moveMarker(imageMarkers.indexOf(marker), 1)" class="w-7 h-7 flex items-center justify-center rounded-md bg-black/50 text-white/80 hover:bg-black/70 hover:text-white backdrop-blur-sm transition-colors" title="Move down">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <button @click="removeMarker(imageMarkers.indexOf(marker))" class="w-7 h-7 flex items-center justify-center rounded-md bg-red-500/70 text-white hover:bg-red-600/90 backdrop-blur-sm transition-colors" title="Remove image">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
              <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-black/50 text-white/80 text-xs backdrop-blur-sm">
                Image {{ imageMarkers.indexOf(marker) + 1 }} of {{ activeMarkers.length }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEO Slide Panel -->
      <div
        :class="['fixed right-0 top-0 h-full w-80 bg-white dark:bg-neutral-800 border-l border-neutral-200 dark:border-neutral-700 shadow-xl z-50 transform transition-transform duration-300 ease-in-out overflow-y-auto', showSeoPanel ? 'translate-x-0' : 'translate-x-full']"
      >
        <div class="p-5">
          <!-- Panel Header -->
          <div class="flex items-center justify-between mb-5">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 uppercase tracking-wide">SEO Analysis</h3>
            <button @click="showSeoPanel = false" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Target Keyword -->
          <div class="mb-5">
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1.5 uppercase tracking-wide">Target Keyword</label>
            <input
              v-model="targetKeyword"
              type="text"
              class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400"
              placeholder="e.g. AI strategy"
            />
          </div>

          <!-- SEO Checks -->
          <template v-if="seoAnalysis">
            <div class="space-y-3 mb-5">
              <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">Placement Checks</h4>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">In title</span>
                <span :class="seoIndicatorClass(seoAnalysis.inTitle.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.inTitle.status) }} {{ seoAnalysis.inTitle.value ? 'Yes' : 'No' }}
                </span>
              </div>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">In first 100 words</span>
                <span :class="seoIndicatorClass(seoAnalysis.inFirst100.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.inFirst100.status) }} {{ seoAnalysis.inFirst100.value ? 'Yes' : 'No' }}
                </span>
              </div>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">In headings</span>
                <span :class="seoIndicatorClass(seoAnalysis.inHeadings.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.inHeadings.status) }} {{ seoAnalysis.inHeadings.value }}&times;
                </span>
              </div>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">Body density</span>
                <span :class="seoIndicatorClass(seoAnalysis.density.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.density.status) }} {{ seoAnalysis.density.value }}%
                </span>
              </div>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">Keyword count</span>
                <span class="text-neutral-600 dark:text-neutral-400 font-medium">
                  {{ seoAnalysis.keywordCount }}&times; in {{ seoAnalysis.totalWords }} words
                </span>
              </div>
            </div>

            <!-- Title Analysis -->
            <div class="space-y-3 mb-5">
              <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">Title Analysis</h4>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">Length</span>
                <span :class="seoIndicatorClass(seoAnalysis.titleLength.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.titleLength.status) }} {{ seoAnalysis.titleLength.value }} chars
                </span>
              </div>

              <div class="flex items-center justify-between text-sm">
                <span class="text-neutral-700 dark:text-neutral-300">Word count</span>
                <span :class="seoIndicatorClass(seoAnalysis.titleWords.status)" class="font-medium">
                  {{ seoIcon(seoAnalysis.titleWords.status) }} {{ seoAnalysis.titleWords.value }} words
                </span>
              </div>

              <!-- Optimal ranges reference -->
              <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-2 space-y-1">
                <p>Optimal title: 50-60 chars, 6-10 words</p>
                <p>Optimal density: 0.5-1.5%</p>
              </div>
            </div>
          </template>

          <div v-else class="text-sm text-neutral-400 dark:text-neutral-500 italic mb-5">
            Enter a target keyword to see SEO analysis
          </div>

          <!-- Quality Gate (when available) -->
          <div v-if="qualityGate" class="mb-5">
            <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
              Quality Gate
              <span :class="qualityGate.pass ? 'text-green-500' : 'text-red-500'" class="ml-1">{{ qualityGate.score }}/{{ qualityGate.max_score }}</span>
            </h4>
            <div class="space-y-1.5">
              <div v-for="(val, key) in qualityGate.criteria" :key="key" class="flex items-start gap-2 text-xs">
                <span :class="val.pass ? 'text-green-500' : 'text-red-400'" class="mt-0.5 shrink-0">{{ val.pass ? '\u2713' : '\u2717' }}</span>
                <div class="min-w-0">
                  <span class="text-neutral-600 dark:text-neutral-300 capitalize">{{ key.replace(/_/g, ' ') }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Virality Triggers (when available) -->
          <div v-if="viralityGate" class="mb-5">
            <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
              Virality Triggers
              <span :class="viralityGate.pass ? 'text-green-500' : 'text-red-500'" class="ml-1">{{ viralityGate.score }}/{{ viralityGate.max_score }}</span>
            </h4>
            <div class="space-y-1.5">
              <div v-for="(val, key) in viralityGate.triggers" :key="key" class="flex items-start gap-2 text-xs">
                <span :class="val.pass ? 'text-green-500' : 'text-red-400'" class="mt-0.5 shrink-0">{{ val.pass ? '\u2713' : '\u2717' }}</span>
                <div class="min-w-0">
                  <span class="text-neutral-600 dark:text-neutral-300 capitalize">{{ key.replace(/_/g, ' ') }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Sources -->
          <div v-if="article.sources?.length" class="mb-5">
            <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">Sources ({{ article.sources.length }})</h4>
            <ul class="space-y-2">
              <li v-for="(src, i) in article.sources" :key="i" class="text-sm">
                <a :href="src.url" target="_blank" rel="noopener noreferrer" class="text-cyan-600 dark:text-cyan-400 hover:underline truncate block">
                  {{ src.name || src.title }}
                </a>
              </li>
            </ul>
          </div>

          <!-- Image Prompts Summary -->
          <div v-if="activeMarkers.length">
            <h4 class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">Image Prompts ({{ activeMarkers.length }})</h4>
            <ul class="space-y-2">
              <li v-for="(marker, i) in activeMarkers" :key="i" class="text-sm text-neutral-600 dark:text-neutral-400">
                <span class="text-amber-500">&bull;</span>
                <span v-if="marker.type" class="text-xs px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-500 mr-1">{{ marker.type }}</span>
                {{ marker.concept }}
                <span class="text-xs text-neutral-400">(pos {{ marker.position }})</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Regenerate Confirmation Modal -->
      <Teleport to="body">
        <div v-if="showRegenConfirm" class="fixed inset-0 z-[60] flex items-center justify-center">
          <div @click="showRegenConfirm = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
          <div class="relative w-full max-w-md mx-4 bg-white dark:bg-neutral-800 rounded-xl shadow-2xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-6">
              <!-- Header -->
              <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.016 4.657v4.992"/></svg>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Regenerate Article</h3>
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">Re-run with improved plugin version</p>
                </div>
              </div>

              <!-- Warning -->
              <div class="mb-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <p class="text-sm text-amber-800 dark:text-amber-300">This will discard the current article and start a fresh generation. The process may take several minutes.</p>
              </div>

              <!-- Additional Instructions -->
              <div class="mb-5">
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1.5">Additional Instructions (optional)</label>
                <textarea
                  v-model="regenInstructions"
                  rows="4"
                  class="w-full px-3 py-2 text-sm rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 focus:ring-cyan-500 focus:border-cyan-500 placeholder-neutral-400 resize-none"
                  placeholder="e.g. Make the content deeper with real examples and data. Avoid generic listicles. Add expert insights and actionable takeaways..."
                ></textarea>
                <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">Guide the AI to improve content quality, depth, or focus areas.</p>
              </div>

              <!-- Actions -->
              <div class="flex items-center justify-end gap-3">
                <button @click="showRegenConfirm = false" :disabled="regenerating" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors disabled:opacity-50">
                  Cancel
                </button>
                <button @click="handleRegenerate" :disabled="regenerating" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white transition-colors disabled:opacity-50">
                  <svg v-if="regenerating" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.016 4.657v4.992"/></svg>
                  {{ regenerating ? 'Starting...' : 'Regenerate Article' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- SEO Panel Overlay (click to close) -->
      <div v-if="showSeoPanel" @click="showSeoPanel = false" class="fixed inset-0 bg-black/20 z-40"></div>

      <!-- Mobile Score Bar (visible on small screens) -->
      <div class="sm:hidden sticky top-[57px] z-30 bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 px-4 py-2 flex items-center gap-2 overflow-x-auto text-xs font-medium">
        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full whitespace-nowrap', scoreColor(qualityScore, qualityMax)]">
          QS {{ qualityScore }}/{{ qualityMax }}
        </span>
        <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full whitespace-nowrap', scoreColor(viralityScoreVal, viralityMax)]">
          VS {{ viralityScoreVal }}/{{ viralityMax }}
        </span>
        <span v-if="seoScoreVal !== null" :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full whitespace-nowrap', scoreColor(seoScoreVal, seoMax)]">
          SEO {{ seoScoreVal }}/{{ seoMax }}
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-300 whitespace-nowrap">
          {{ currentContent.wordCount?.toLocaleString() || '—' }}w
        </span>
      </div>
    </template>

    <!-- No article data -->
    <div v-else-if="idea && !article" class="flex items-center justify-center min-h-screen">
      <div class="text-center max-w-md">
        <p class="text-neutral-600 dark:text-neutral-400 font-medium mb-2">No article content yet</p>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">This idea hasn't been through article generation yet.</p>
        <button @click="$router.push({ name: 'admin-content-engine' })" class="mt-4 px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700">
          Back to Content Engine
        </button>
      </div>
    </div>
  </div>
</template>
