<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import PipelineStepBar from '@/components/admin/PipelineStepBar.vue'

const route = useRoute()
const router = useRouter()
const { getIdea, approveAndPublish, isLoading } = useContentEngine()
const toast = useToast()

const idea = ref(null)
const loadError = ref(null)
const activeLang = ref('en')
const publishing = ref(false)

onMounted(async () => {
  const id = route.params.id
  const result = await getIdea(id)
  if (result.success && result.data) {
    idea.value = result.data
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
  const parser = new DOMParser()
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html')

  // Make links open in new tab
  doc.querySelectorAll('a').forEach(a => {
    a.setAttribute('target', '_blank')
    a.setAttribute('rel', 'noopener noreferrer')
  })

  const children = Array.from(doc.body.firstChild?.children || [])
  const blocks = children.map(el => el.outerHTML)

  // Insert images at their suggested positions
  const sortedImages = [...imagePrompts.value]
    .filter(img => img.generated_url && img.type !== 'cover')
    .sort((a, b) => (b.suggested_position || 0) - (a.suggested_position || 0))

  for (const img of sortedImages) {
    const pos = img.suggested_position ?? 0
    const imgHtml = `<figure class="my-8 not-prose"><img src="${img.generated_url}" alt="${img.concept || ''}" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-neutral-500 dark:text-neutral-400 mt-2 text-center">${img.concept || ''}</figcaption></figure>`
    if (pos >= 0 && pos <= blocks.length) {
      blocks.splice(pos, 0, imgHtml)
    }
  }

  return blocks.join('\n')
})

const coverImage = computed(() => {
  const cover = imagePrompts.value.find(img => img.type === 'cover')
  return cover?.generated_url || ''
})

async function handlePublish() {
  if (!idea.value) return
  publishing.value = true
  const result = await approveAndPublish(idea.value.id)
  publishing.value = false

  if (result.success) {
    toast.success('Article published to blog!')
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
