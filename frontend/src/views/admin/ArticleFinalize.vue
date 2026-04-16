<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'
import { resolveImagePosition } from '@/utils/imagePositioning'

const route = useRoute()
const router = useRouter()
const { getIdea, approveAndPublish, isLoading } = useContentEngine()
const toast = useToast()

const idea = ref(null)
const loadError = ref(null)
const activeLang = ref('id')
const publishing = ref(false)

onMounted(async () => {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
    // Prefer 'id' if available, fall back to 'en' if only English exists
    const art = result.data?.generated_article
    if (!art?.id?.content && art?.en?.content) {
      activeLang.value = 'en'
    }
  } else {
    loadError.value = result.error || 'Failed to load content idea'
  }
})

const article = computed(() => idea.value?.generated_article || null)

const availableLanguages = computed(() => {
  if (!article.value) return ['en']
  const langs = []
  if (article.value.en || article.value.title) langs.push('en')
  if (article.value.id) langs.push('id')
  return langs.length ? langs : ['en']
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

const isUntranslated = computed(() =>
  activeLang.value === 'en' && !article.value?.en?.content
)

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
        <!-- Untranslated notice (English tab, no EN content yet) -->
        <div v-if="isUntranslated" class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-5 flex items-start gap-3">
          <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
          </svg>
          <div class="text-sm text-amber-800 dark:text-amber-300">
            <p class="font-medium">Belum diterjemahkan.</p>
            <p class="mt-1">Terjemahan otomatis ke bahasa Inggris akan berjalan saat Anda klik <strong>Publish to Blog</strong>.</p>
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
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
          <button @click="router.push(`/admin/content-engine/${idea.id}/images`)" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Images
          </button>
          <button @click="handlePublish" :disabled="publishing" class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white transition-colors disabled:opacity-50">
            {{ publishing ? 'Publishing...' : 'Publish to Blog' }}
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
