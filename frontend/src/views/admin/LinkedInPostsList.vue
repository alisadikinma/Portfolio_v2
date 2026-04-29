<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import {
  useLinkedInDraftsList,
  useCancelLinkedInDraft,
  usePublishLinkedInDraftNow,
  useRegenerateLinkedInDraft,
  postTitle,
} from '@/composables/useLinkedInDrafts'
import {
  effectiveStatusMeta,
  MOOD_CLASSES,
  formatChip,
  relativeTime,
  countdownTo,
  formatDateTime,
  FEED_TAB_KEY,
  ICON,
} from './linkedinHelpers'

// Live ticker — re-renders countdown every second so "in 14m 25s" actually
// counts down instead of being a frozen value computed once on mount.
const tick = ref(0)
let tickerInterval
onMounted(() => {
  tickerInterval = setInterval(() => { tick.value++ }, 1000)
})
onBeforeUnmount(() => {
  if (tickerInterval) clearInterval(tickerInterval)
})

// Wrap countdownTo so reading it depends on `tick` — Vue will recompute
// each scheduled card's display every second.
function liveCountdown(datetime) {
  void tick.value
  return countdownTo(datetime)
}

const router = useRouter()

// Tab persistence
const activeTab = ref('published')
onMounted(() => {
  const saved = sessionStorage.getItem(FEED_TAB_KEY)
  if (saved && ['published', 'scheduled', 'cancelled', 'all'].includes(saved)) {
    activeTab.value = saved
  }
})
watch(activeTab, (v) => sessionStorage.setItem(FEED_TAB_KEY, v))

const statusFilter = computed(() => {
  if (activeTab.value === 'published') return 'published'
  if (activeTab.value === 'scheduled') return 'awaiting_publish'
  if (activeTab.value === 'cancelled') return 'cancelled'
  return null
})

const filters = computed(() => ({
  status: statusFilter.value || undefined,
  scope: 'feed',
  per_page: 24,
}))

const { drafts, isLoading, error } = useLinkedInDraftsList(filters)

const cancelMutation = useCancelLinkedInDraft()
const publishNowMutation = usePublishLinkedInDraftNow()
const regenerateMutation = useRegenerateLinkedInDraft()

const tabs = [
  { key: 'published', label: 'Published', accent: 'text-emerald-400' },
  { key: 'scheduled', label: 'Scheduled', accent: 'text-amber-400' },
  { key: 'cancelled', label: 'Cancelled', accent: 'text-neutral-400' },
  { key: 'all', label: 'Everything', accent: 'text-neutral-300' },
]

function openDetail(id) {
  sessionStorage.setItem('linkedin:detail:origin', 'feed')
  router.push({ name: 'admin-linkedin-draft-detail', params: { id } })
}

function depthTone(score) {
  if (score === null || score === undefined) return 'text-neutral-500'
  if (score >= 80) return 'text-emerald-400'
  if (score >= 70) return 'text-amber-400'
  return 'text-red-400'
}

async function doCancel(id) {
  if (!confirm('Cancel this LinkedIn post? It will not be published.')) return
  await cancelMutation.mutateAsync(id)
}

async function doPublishNow(id) {
  if (!confirm('Publish to LinkedIn now, skipping the cancel window?')) return
  await publishNowMutation.mutateAsync(id)
}

async function doRegenerate(id) {
  if (!confirm('Regenerate from scratch? This archives the current draft and queues a new generation.')) return
  await regenerateMutation.mutateAsync(id)
}

const emptyMessage = computed(() => ({
  published: { title: 'No posts shipped yet', body: 'Approved drafts appear here once they go live on LinkedIn.' },
  scheduled: { title: 'Nothing scheduled', body: 'Drafts that pass validation will queue here for the cancel window.' },
  cancelled: { title: 'Nothing cancelled', body: 'Cancelled drafts show here for record-keeping.' },
  all: { title: 'No posts yet', body: 'Publish a blog post to kick off the first conversion.' },
}[activeTab.value]))
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <!-- Header -->
    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-emerald-400/80 mb-1">LinkedIn pipeline</p>
        <h1 class="text-3xl sm:text-4xl font-display font-semibold tracking-tight text-neutral-100">
          Posts
        </h1>
        <p class="text-sm text-neutral-400 mt-2 max-w-xl">
          Drafts that have shipped or are queued to ship. For drafts that need attention,
          go to <router-link to="/admin/linkedin-queue" class="text-amber-400 hover:underline">Queue</router-link>.
        </p>
      </div>
      <router-link
        to="/admin/linkedin-queue"
        class="inline-flex items-center gap-2 px-3.5 py-2 text-sm text-neutral-300 border border-neutral-800 rounded-lg hover:border-amber-500/40 hover:bg-amber-500/5 hover:text-amber-300 transition"
      >
        Queue
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
          <path :d="ICON.chevronRight" />
        </svg>
      </router-link>
    </header>

    <!-- Tab rail -->
    <nav class="flex flex-wrap gap-1 p-1 rounded-xl bg-neutral-900/50 border border-neutral-800/80 w-max max-w-full">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all',
          activeTab === tab.key
            ? 'bg-neutral-800 text-neutral-100 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]'
            : 'text-neutral-400 hover:text-neutral-200',
        ]"
      >
        <span :class="activeTab === tab.key ? tab.accent : ''">{{ tab.label }}</span>
      </button>
    </nav>

    <!-- Loading -->
    <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 6" :key="i" class="h-56 rounded-2xl bg-neutral-900/40 animate-pulse" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/5 p-8 text-center">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 text-red-400 mx-auto mb-3">
        <path :d="ICON.alertCircle" />
      </svg>
      <p class="text-red-300 font-medium">Failed to load posts</p>
      <p class="text-xs text-neutral-500 mt-2 font-mono">{{ error.message || 'Unknown error' }}</p>
    </div>

    <!-- Empty -->
    <div
      v-else-if="drafts.length === 0"
      class="rounded-2xl border border-dashed border-neutral-800 p-12 text-center"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12 text-neutral-700 mx-auto mb-3">
        <path :d="ICON.linkedin" />
      </svg>
      <p class="text-base text-neutral-300 font-medium">{{ emptyMessage.title }}</p>
      <p class="text-sm text-neutral-500 mt-1 max-w-sm mx-auto">{{ emptyMessage.body }}</p>
    </div>

    <!-- Card grid -->
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <article
        v-for="draft in drafts"
        :key="draft.id"
        @click="openDetail(draft.id)"
        class="group rounded-2xl border border-neutral-800/80 bg-neutral-950/40 overflow-hidden cursor-pointer transition-all hover:border-amber-500/40 hover:bg-neutral-900/40 hover:-translate-y-[1px]"
      >
        <!-- Mood rail (top) — uses effectiveStatusMeta for carousel
             rendering-state awareness (see helper). -->
        <div
          class="h-[3px] w-full bg-gradient-to-r"
          :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.rail"
        />

        <div class="p-5 space-y-4">
          <!-- Status + format -->
          <div class="flex items-center justify-between gap-3">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase tracking-[0.12em]"
              :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.chip"
            >
              <span class="w-1 h-1 rounded-full" :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.dot" />
              {{ effectiveStatusMeta(draft).short }}
            </span>
            <span class="text-[10px] font-mono uppercase tracking-[0.14em] text-neutral-500">
              {{ formatChip(draft.format) }}
              <template v-if="draft.format === 'carousel' && Array.isArray(draft.carousel_slides)"> · {{ draft.carousel_slides.length }}</template>
            </span>
          </div>

          <!-- Body preview -->
          <p class="text-sm text-neutral-300 line-clamp-4 leading-relaxed min-h-[5.25rem]">
            {{ draft.content || '—' }}
          </p>

          <!-- Source line -->
          <div class="text-xs text-neutral-500 truncate">
            <span class="text-neutral-600">From: </span>{{ postTitle(draft) }}
          </div>

          <!-- Footer: depth + time. Scheduled cards show absolute time on top
               (so operator knows exactly when this fires) + live-ticking
               countdown below (in 14m 25s → 14m 24s → 14m 23s …). -->
          <div class="flex items-start justify-between text-xs pt-3 border-t border-neutral-800/60 gap-2">
            <span v-if="draft.depth_score" :class="['font-mono font-bold whitespace-nowrap', depthTone(draft.depth_score)]">
              Depth {{ draft.depth_score }}
            </span>
            <span v-else class="text-neutral-600 font-mono">—</span>
            <span class="text-right text-neutral-500 font-mono">
              <template v-if="draft.status === 'published' && draft.published_at">
                {{ relativeTime(draft.published_at) }}
              </template>
              <template v-else-if="draft.status === 'awaiting_publish' && draft.cancel_window_ends_at">
                <span class="block text-neutral-300">{{ formatDateTime(draft.cancel_window_ends_at) }}</span>
                <span class="block text-amber-400 mt-0.5">in {{ liveCountdown(draft.cancel_window_ends_at) }}</span>
              </template>
              <template v-else>{{ relativeTime(draft.updated_at) }}</template>
            </span>
          </div>

          <!-- Hover actions -->
          <div
            class="flex flex-wrap gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity -mb-1"
            @click.stop
          >
            <a
              v-if="draft.status === 'published' && draft.linkedin_post_url"
              :href="draft.linkedin_post_url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 ring-1 ring-amber-500/30 font-medium"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                <path :d="ICON.externalLink" />
              </svg>
              Open
            </a>
            <template v-if="draft.status === 'awaiting_publish'">
              <button
                @click="doPublishNow(draft.id)"
                :disabled="publishNowMutation.isPending.value"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20 ring-1 ring-emerald-500/30 font-medium disabled:opacity-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                  <path :d="ICON.send" />
                </svg>
                Publish now
              </button>
              <button
                @click="doCancel(draft.id)"
                :disabled="cancelMutation.isPending.value"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-neutral-800 text-neutral-400 hover:bg-red-500/10 hover:text-red-400 ring-1 ring-neutral-700 hover:ring-red-500/30 font-medium disabled:opacity-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                  <path :d="ICON.x" />
                </svg>
                Cancel
              </button>
            </template>
            <button
              v-if="draft.status === 'cancelled'"
              @click="doRegenerate(draft.id)"
              :disabled="regenerateMutation.isPending.value"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-cyan-500/10 text-cyan-300 hover:bg-cyan-500/20 ring-1 ring-cyan-500/30 font-medium disabled:opacity-50"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                <path :d="ICON.refresh" />
              </svg>
              Regenerate
            </button>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
