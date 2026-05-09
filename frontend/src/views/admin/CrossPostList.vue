<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCrossPostDraftsList } from '@/composables/useCrossPostDrafts'
import { PLATFORMS, statusMeta, resolvePostTitle, relativeTime } from './socialPlatformHelpers'
import SocialPlatformTabs from '@/components/admin/SocialPlatformTabs.vue'

/**
 * Generic admin list view for cross-post drafts. Single component handles
 * Facebook, Instagram, and TikTok via the `:platform` route param. Tabs
 * split queue (pending/generating/awaiting_review/failed) from feed
 * (publishing/published/cancelled) so operators can triage vs. audit.
 */

const route = useRoute()
const router = useRouter()

const platform = computed(() => route.params.platform)
const platformInfo = computed(() => PLATFORMS[platform.value])

if (!platformInfo.value) {
  // Invalid platform param — bounce to dashboard.
  router.replace('/admin')
}

// Initial scope: route path signals queue vs feed for FB/IG/TT.
// /admin/{platform}-queue → queue scope (default)
// /admin/{platform}-posts → feed scope (Phase 4: separate from queue)
// Fallback to query param `?scope=` for backward compatibility, then
// finally default to 'queue' which is the operator's daily-workflow tab.
//
// Trailing-slash robust via .replace(/\/$/, '') before suffix check.
function resolveScopeFromRoute(path, query) {
  const cleanPath = (path || '').replace(/\/$/, '')
  if (cleanPath.endsWith('-queue')) return 'queue'
  if (cleanPath.endsWith('-posts')) return 'feed'
  if (query?.scope === 'feed') return 'feed'
  return 'queue'
}

const activeTab = ref(resolveScopeFromRoute(route.path, route.query))

// Sync activeTab when the operator navigates between /admin/{platform}-posts
// and /admin/{platform}-queue while Vue Router reuses the same component
// instance (same matched route component, different path). Without this
// watcher, activeTab would stay stale after the navigation.
watch(
  () => route.path,
  (newPath) => {
    activeTab.value = resolveScopeFromRoute(newPath, route.query)
  }
)

const filters = computed(() => ({
  scope: activeTab.value === 'feed' ? 'feed' : 'queue',
  per_page: 50,
}))

const { drafts, isLoading, refetch } = useCrossPostDraftsList(platform, filters)

function openDetail(id) {
  router.push(`${platformInfo.value.detailPrefix}/${id}`)
}

const tabCounts = computed(() => {
  // Counts are scope-aware on the server but we don't have the alternate-
  // scope data here. Display-only badge — actual filtering happens server-side.
  return {
    queue: activeTab.value === 'queue' ? drafts.value.length : null,
    feed: activeTab.value === 'feed' ? drafts.value.length : null,
  }
})
</script>

<template>
  <div v-if="platformInfo" class="px-6 py-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          {{ platformInfo.label }} {{ activeTab === 'feed' ? 'Posts' : 'Queue' }}
        </h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Cross-post drafts fanned out from LinkedIn carousels.
        </p>
      </div>
      <button
        @click="refetch"
        class="px-3 py-2 text-sm rounded-md bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-300 transition-colors"
        :disabled="isLoading"
      >
        Refresh
      </button>
    </div>

    <!-- Platform switcher (Phase 4) — current platform highlighted, click
         another platform to navigate to its queue (or posts/feed view). -->
    <div class="mb-4">
      <SocialPlatformTabs :current="platform" :mode="activeTab === 'feed' ? 'posts' : 'queue'" />
    </div>

    <!-- Tab rail -->
    <div class="inline-flex rounded-lg bg-neutral-100 dark:bg-neutral-800 p-1 mb-6">
      <button
        v-for="tab in ['queue', 'feed']"
        :key="tab"
        @click="activeTab = tab"
        class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
        :class="activeTab === tab
          ? 'bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 shadow-sm'
          : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100'"
      >
        {{ tab === 'queue' ? 'Queue (triage)' : 'Feed (history)' }}
        <span
          v-if="tabCounts[tab] !== null"
          class="ml-2 inline-block min-w-[1.5rem] text-center text-xs px-1.5 py-0.5 rounded-full bg-neutral-200 dark:bg-neutral-700"
        >
          {{ tabCounts[tab] }}
        </span>
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isLoading && drafts.length === 0" class="space-y-3">
      <div
        v-for="n in 5"
        :key="n"
        class="h-20 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse"
      />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="drafts.length === 0"
      class="text-center py-16 rounded-lg border border-dashed border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900"
    >
      <div class="text-4xl mb-3">{{ activeTab === 'queue' ? '📭' : '🗂️' }}</div>
      <p class="text-neutral-700 dark:text-neutral-300 font-medium">
        {{ activeTab === 'queue' ? 'Inbox zero' : 'No history yet' }}
      </p>
      <p class="text-sm text-neutral-500 mt-1">
        {{ activeTab === 'queue'
          ? 'Nothing in the queue. New drafts arrive when LinkedIn carousels finish rendering.'
          : 'Approved + published drafts will appear here.' }}
      </p>
    </div>

    <!-- Draft list -->
    <div v-else class="bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-200 dark:divide-neutral-700">
      <button
        v-for="draft in drafts"
        :key="draft.id"
        @click="openDetail(draft.id)"
        class="w-full text-left px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors flex items-start gap-4"
      >
        <!-- Status pill -->
        <div class="flex-shrink-0">
          <span
            class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md"
            :class="[statusMeta(draft.status).bg, statusMeta(draft.status).text]"
          >
            {{ statusMeta(draft.status).label }}
          </span>
        </div>

        <!-- Title + meta -->
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">
            {{ resolvePostTitle(draft) || `(post #${draft.post_id})` }}
          </p>
          <div class="flex items-center gap-3 mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            <span>#{{ draft.id }}</span>
            <span v-if="draft.format" class="uppercase tracking-wider">{{ draft.format }}</span>
            <span v-if="draft.linkedin_post_id">LI #{{ draft.linkedin_post_id }}</span>
            <span>{{ relativeTime(draft.updated_at) }}</span>
          </div>
        </div>

        <!-- Caption preview -->
        <div v-if="draft.caption" class="hidden md:block text-xs text-neutral-500 dark:text-neutral-400 max-w-md truncate">
          {{ draft.caption }}
        </div>
      </button>
    </div>
  </div>
</template>
