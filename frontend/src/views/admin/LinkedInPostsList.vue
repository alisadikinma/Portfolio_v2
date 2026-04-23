<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  useLinkedInDraftsList,
  useCancelLinkedInDraft,
  usePublishLinkedInDraftNow,
  useRegenerateLinkedInDraft,
  postTitle,
} from '@/composables/useLinkedInDrafts'

const router = useRouter()

const activeTab = ref('published') // 'published' | 'scheduled' | 'cancelled' | 'all'

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
  { key: 'published', label: 'Published' },
  { key: 'scheduled', label: 'Scheduled' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'all', label: 'All' },
]

function openDetail(id) {
  router.push({ name: 'admin-linkedin-draft-detail', params: { id } })
}

function statusBadgeClass(status) {
  return {
    published: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40',
    awaiting_publish: 'bg-amber-500/20 text-amber-400 border-amber-500/40',
    cancelled: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/40',
  }[status] || 'bg-neutral-500/20 text-neutral-400'
}

function statusLabel(status) {
  return {
    published: 'Published',
    awaiting_publish: 'Scheduled',
    cancelled: 'Cancelled',
  }[status] || status
}

function depthScoreClass(score) {
  if (score === null || score === undefined) return 'text-neutral-500'
  if (score >= 80) return 'text-amber-400'
  if (score >= 70) return 'text-yellow-500'
  return 'text-red-400'
}

function relativeTime(datetime) {
  if (!datetime) return ''
  const diff = Date.now() - new Date(datetime).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

function countdownTo(datetime) {
  if (!datetime) return ''
  const diff = new Date(datetime).getTime() - Date.now()
  if (diff < 0) return 'publishing now…'
  const mins = Math.floor(diff / 60000)
  const secs = Math.floor((diff % 60000) / 1000)
  if (mins === 0) return `in ${secs}s`
  return `in ${mins}m ${secs}s`
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
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
          LinkedIn Posts
        </h1>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
          Posts that have shipped or are scheduled to ship. For drafts needing attention, see
          <router-link to="/admin/linkedin-queue" class="text-amber-600 hover:underline">Queue</router-link>.
        </p>
      </div>
      <router-link
        to="/admin/linkedin-queue"
        class="px-4 py-2 text-sm font-medium text-neutral-700 dark:text-neutral-300 border border-neutral-300 dark:border-neutral-600 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700"
      >
        Go to Queue →
      </router-link>
    </div>

    <!-- Filter tabs -->
    <div class="mb-6 flex flex-wrap gap-2 border-b border-neutral-200 dark:border-neutral-700 pb-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
          activeTab === tab.key
            ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400'
            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="py-12 text-center text-neutral-500">
      Loading posts…
    </div>

    <!-- Error -->
    <div v-else-if="error" class="py-12 text-center">
      <p class="text-red-500 mb-2">Failed to load posts</p>
      <p class="text-xs text-neutral-500">{{ error.message || 'Unknown error' }}</p>
    </div>

    <!-- Empty -->
    <div v-else-if="drafts.length === 0" class="py-16 text-center">
      <p class="text-neutral-500">
        <template v-if="activeTab === 'published'">No published posts yet. Approved drafts will appear here after they ship.</template>
        <template v-else-if="activeTab === 'scheduled'">No scheduled posts. When drafts pass validation they'll queue here for the cancel window.</template>
        <template v-else-if="activeTab === 'cancelled'">No cancelled posts.</template>
        <template v-else>No posts yet. Publish a blog post to kick off the first conversion.</template>
      </p>
    </div>

    <!-- Card grid -->
    <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="draft in drafts"
        :key="draft.id"
        @click="openDetail(draft.id)"
        class="group rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 overflow-hidden cursor-pointer transition-all hover:border-amber-500/50 hover:shadow-lg"
      >
        <!-- Header: status + format chip -->
        <div class="flex items-center justify-between px-4 pt-4">
          <span
            class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider border"
            :class="statusBadgeClass(draft.status)"
          >
            ● {{ statusLabel(draft.status) }}
          </span>
          <span class="text-[10px] font-mono text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
            {{ draft.format }}{{ draft.format === 'carousel' ? ` · ${(draft.carousel_slides || []).length} slides` : '' }}
          </span>
        </div>

        <!-- Content preview -->
        <div class="px-4 py-3 min-h-[110px]">
          <p class="text-sm text-neutral-700 dark:text-neutral-300 line-clamp-4">
            {{ draft.content || '—' }}
          </p>
        </div>

        <!-- Footer: source + depth + time -->
        <div class="px-4 py-3 border-t border-neutral-100 dark:border-neutral-800 text-xs space-y-1.5">
          <p class="text-neutral-600 dark:text-neutral-400 truncate">
            From:
            <span class="font-medium">{{ postTitle(draft) }}</span>
          </p>
          <div class="flex items-center justify-between">
            <span v-if="draft.depth_score" :class="['font-mono font-semibold', depthScoreClass(draft.depth_score)]">
              Depth {{ draft.depth_score }}
            </span>
            <span v-else class="text-neutral-500">—</span>
            <span class="text-neutral-500">
              <template v-if="draft.status === 'published' && draft.published_at">
                {{ relativeTime(draft.published_at) }}
              </template>
              <template v-else-if="draft.status === 'awaiting_publish' && draft.cancel_window_ends_at">
                {{ countdownTo(draft.cancel_window_ends_at) }}
              </template>
              <template v-else>{{ relativeTime(draft.updated_at) }}</template>
            </span>
          </div>
        </div>

        <!-- Hover actions -->
        <div class="px-4 pb-4 opacity-0 group-hover:opacity-100 transition-opacity flex flex-wrap gap-1.5">
          <a
            v-if="draft.status === 'published' && draft.linkedin_post_url"
            :href="draft.linkedin_post_url"
            target="_blank"
            rel="noopener"
            @click.stop
            class="px-2.5 py-1 text-[11px] rounded-md bg-amber-500/20 text-amber-600 dark:text-amber-400 hover:bg-amber-500/30 font-medium"
          >
            Open ↗
          </a>
          <template v-if="draft.status === 'awaiting_publish'">
            <button
              @click.stop="doPublishNow(draft.id)"
              :disabled="publishNowMutation.isPending.value"
              class="px-2.5 py-1 text-[11px] rounded-md bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/30 font-medium disabled:opacity-50"
            >
              Publish now
            </button>
            <button
              @click.stop="doCancel(draft.id)"
              :disabled="cancelMutation.isPending.value"
              class="px-2.5 py-1 text-[11px] rounded-md bg-neutral-500/20 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-500/30 font-medium disabled:opacity-50"
            >
              Cancel
            </button>
          </template>
          <button
            v-if="draft.status === 'cancelled'"
            @click.stop="doRegenerate(draft.id)"
            :disabled="regenerateMutation.isPending.value"
            class="px-2.5 py-1 text-[11px] rounded-md bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-500/30 font-medium disabled:opacity-50"
          >
            Regenerate
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
