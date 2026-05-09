<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useCrossPostDraft,
  useApproveCrossPostDraft,
  useCancelCrossPostDraft,
  useRegenerateCrossPostDraft,
  useUpdateCrossPostDraft,
} from '@/composables/useCrossPostDrafts'
import {
  PLATFORMS,
  statusMeta,
  resolvePostTitle,
  relativeTime,
  formatHashtags,
  transitionSummary,
} from './socialPlatformHelpers'
import { useUiStore } from '@/stores/ui'

/**
 * Generic admin detail view for cross-post drafts. Single component
 * handles Facebook, Instagram, and TikTok via the `:platform` route
 * param. Renders status hero + content preview + state log + action
 * panel (Approve / Regenerate / Cancel).
 */

const route = useRoute()
const router = useRouter()
const ui = useUiStore()

const platform = computed(() => route.params.platform)
const id = computed(() => route.params.id)
const platformInfo = computed(() => PLATFORMS[platform.value])

if (!platformInfo.value) {
  router.replace('/admin')
}

const { draft, isLoading, refetch } = useCrossPostDraft(platform, id)

const approveMutation = useApproveCrossPostDraft(platform.value)
const cancelMutation = useCancelCrossPostDraft(platform.value)
const regenerateMutation = useRegenerateCrossPostDraft(platform.value)
const updateMutation = useUpdateCrossPostDraft(platform.value)

const editing = ref(false)
const editForm = ref({
  title: '',
  caption: '',
  hashtags: '',
  link_url: '',
})

watch(draft, (d) => {
  if (d && !editing.value) {
    editForm.value = {
      title: d.title || '',
      caption: d.caption || '',
      hashtags: Array.isArray(d.hashtags) ? d.hashtags.join(' ') : '',
      link_url: d.link_url || '',
    }
  }
}, { immediate: true })

const meta = computed(() => statusMeta(draft.value?.status))

const stateLog = computed(() => {
  const log = draft.value?.pipeline_state_log
  if (!Array.isArray(log)) return []
  return log.slice(-10).reverse()
})

const canApprove = computed(() => draft.value?.status === 'awaiting_review')
const canCancel = computed(() =>
  draft.value && !['cancelled', 'published'].includes(draft.value.status)
)
const canRegenerate = computed(() =>
  draft.value && draft.value.status !== 'published'
)

async function onApprove() {
  if (!canApprove.value) return
  try {
    await approveMutation.mutateAsync(id.value)
    ui.showSuccess(`${platformInfo.value.label} draft approved.`, 'Approved')
  } catch (err) {
    const msg = err.response?.data?.error?.message || err.message || 'Approve failed'
    if (err.response?.status === 503) {
      ui.showError(msg, 'Publer not yet enabled')
    } else {
      ui.showError(msg, 'Approve Failed')
    }
  }
}

async function onCancel() {
  if (!canCancel.value) return
  if (!window.confirm('Cancel this draft? Cancelled drafts can still be regenerated.')) return
  try {
    await cancelMutation.mutateAsync(id.value)
    ui.showSuccess('Draft cancelled.', 'Cancelled')
  } catch (err) {
    ui.showError(err.response?.data?.error?.message || err.message, 'Cancel Failed')
  }
}

async function onRegenerate() {
  if (!canRegenerate.value) return
  if (!window.confirm('Regenerate? This soft-deletes the current draft and creates a new one in pending_generation.')) return
  try {
    const result = await regenerateMutation.mutateAsync(id.value)
    ui.showSuccess('New draft created — generation started.', 'Regenerating')
    if (result?.data?.id) {
      router.push(`${platformInfo.value.detailPrefix}/${result.data.id}`)
    }
  } catch (err) {
    ui.showError(err.response?.data?.error?.message || err.message, 'Regenerate Failed')
  }
}

async function onSave() {
  const payload = {
    title: editForm.value.title || null,
    caption: editForm.value.caption || null,
    hashtags: editForm.value.hashtags
      ? editForm.value.hashtags
          .split(/\s+/)
          .map((t) => t.trim())
          .filter(Boolean)
      : null,
  }
  if (platform.value === 'facebook') {
    payload.link_url = editForm.value.link_url || null
  }
  try {
    await updateMutation.mutateAsync({ id: id.value, payload })
    ui.showSuccess('Draft saved.', 'Saved')
    editing.value = false
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data?.errors
      const firstError = errors ? Object.values(errors).flat()[0] : null
      ui.showError(firstError || 'Validation failed.', 'Save Failed')
    } else {
      ui.showError(err.response?.data?.error?.message || err.message, 'Save Failed')
    }
  }
}

function backToList() {
  router.push(platformInfo.value.routePrefix)
}
</script>

<template>
  <div v-if="platformInfo" class="px-6 py-8 max-w-5xl mx-auto">
    <!-- Back nav -->
    <button
      @click="backToList"
      class="inline-flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 mb-6 group transition-colors"
    >
      <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
      </svg>
      Back to {{ platformInfo.label }} Posts
    </button>

    <!-- Loading -->
    <div v-if="isLoading && !draft" class="space-y-4">
      <div class="h-32 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
      <div class="h-64 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
    </div>

    <!-- Detail body -->
    <div v-else-if="draft" class="space-y-6">
      <!-- Status hero -->
      <div
        class="rounded-lg border p-5 flex items-start justify-between gap-4"
        :class="[meta.bg, meta.border]"
      >
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-md bg-white/70 dark:bg-black/30" :class="meta.text">
              {{ meta.label }}
            </span>
            <span class="text-xs text-neutral-600 dark:text-neutral-400 uppercase tracking-wider font-mono">
              {{ platformInfo.short }} · #{{ draft.id }}
            </span>
            <span v-if="draft.format" class="text-xs px-2 py-1 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
              {{ draft.format }}
            </span>
          </div>
          <p class="text-base font-medium text-neutral-900 dark:text-neutral-100 mt-3">
            {{ resolvePostTitle(draft) || `(post #${draft.post_id})` }}
          </p>
          <p class="text-sm text-neutral-700 dark:text-neutral-300 mt-2">
            {{ meta.description }}
          </p>
          <p v-if="draft.last_error" class="mt-3 px-3 py-2 rounded bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300 font-mono">
            {{ draft.last_error }}
          </p>
        </div>
      </div>

      <!-- Content preview / edit -->
      <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">Caption</h2>
          <button
            v-if="!editing"
            @click="editing = true"
            class="text-xs px-2 py-1 rounded text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
          >
            Edit
          </button>
          <div v-else class="flex gap-2">
            <button
              @click="editing = false"
              class="text-xs px-2 py-1 rounded text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="onSave"
              :disabled="updateMutation.isPending.value"
              class="text-xs px-3 py-1 rounded bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50 transition-colors"
            >
              {{ updateMutation.isPending.value ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </div>

        <div class="p-5 space-y-4">
          <!-- Read mode -->
          <div v-if="!editing">
            <p v-if="draft.title" class="text-base font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
              {{ draft.title }}
            </p>
            <p v-if="draft.caption" class="whitespace-pre-wrap text-sm text-neutral-800 dark:text-neutral-200 leading-relaxed">
              {{ draft.caption }}
            </p>
            <p v-else class="text-sm italic text-neutral-500">No caption authored yet.</p>
            <div v-if="draft.hashtags?.length" class="mt-3 text-sm text-cyan-600 dark:text-cyan-400 font-mono">
              {{ formatHashtags(draft.hashtags) }}
            </div>
            <div v-if="draft.link_url" class="mt-3 text-sm">
              <span class="text-neutral-500">Link: </span>
              <a :href="draft.link_url" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 hover:underline break-all">
                {{ draft.link_url }}
              </a>
            </div>
          </div>

          <!-- Edit mode -->
          <div v-else class="space-y-3">
            <div>
              <label class="block text-xs text-neutral-600 dark:text-neutral-400 mb-1">Title</label>
              <input
                v-model="editForm.title"
                type="text"
                class="w-full px-3 py-2 rounded border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs text-neutral-600 dark:text-neutral-400 mb-1">Caption</label>
              <textarea
                v-model="editForm.caption"
                rows="8"
                class="w-full px-3 py-2 rounded border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm font-mono"
              />
              <p class="text-xs text-neutral-500 mt-1">{{ editForm.caption.length }} chars</p>
            </div>
            <div>
              <label class="block text-xs text-neutral-600 dark:text-neutral-400 mb-1">
                Hashtags (space-separated)
              </label>
              <input
                v-model="editForm.hashtags"
                type="text"
                placeholder="#aibuilders #vibecoding"
                class="w-full px-3 py-2 rounded border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm font-mono"
              />
              <p class="text-xs text-neutral-500 mt-1">
                {{ platform === 'instagram' ? 'IG: 3-5 hardcap (Dec 2025 algorithm change)' : '' }}
                {{ platform === 'tiktok' ? 'TikTok: 5-8 (search-index signal)' : '' }}
                {{ platform === 'facebook' ? 'FB: 0-5 (algorithm barely uses hashtags)' : '' }}
              </p>
            </div>
            <div v-if="platform === 'facebook'">
              <label class="block text-xs text-neutral-600 dark:text-neutral-400 mb-1">Link URL (text format only)</label>
              <input
                v-model="editForm.link_url"
                type="url"
                placeholder="https://alisadikinma.com/blog/..."
                class="w-full px-3 py-2 rounded border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Action panel -->
      <div class="rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5 flex flex-wrap gap-3">
        <button
          v-if="canApprove"
          @click="onApprove"
          :disabled="approveMutation.isPending.value"
          class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors"
        >
          {{ approveMutation.isPending.value ? 'Approving…' : 'Approve & Publish' }}
        </button>
        <button
          v-if="canRegenerate"
          @click="onRegenerate"
          :disabled="regenerateMutation.isPending.value"
          class="px-4 py-2 rounded-md bg-cyan-600 text-white text-sm font-medium hover:bg-cyan-700 disabled:opacity-50 transition-colors"
        >
          {{ regenerateMutation.isPending.value ? 'Starting…' : '↻ Regenerate' }}
        </button>
        <button
          v-if="canCancel"
          @click="onCancel"
          :disabled="cancelMutation.isPending.value"
          class="px-4 py-2 rounded-md border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 text-sm font-medium hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-50 transition-colors"
        >
          Cancel
        </button>
        <button
          @click="refetch"
          class="ml-auto px-3 py-2 text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100 transition-colors"
        >
          Refresh
        </button>
      </div>

      <!-- State log -->
      <div v-if="stateLog.length" class="rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-5">
        <h2 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider mb-3">Pipeline History</h2>
        <ul class="space-y-2 text-sm">
          <li
            v-for="(entry, i) in stateLog"
            :key="i"
            class="flex items-start gap-3 py-1.5 border-b border-neutral-100 dark:border-neutral-800 last:border-b-0"
          >
            <span class="w-2 h-2 mt-1.5 rounded-full bg-neutral-300 dark:bg-neutral-600 flex-shrink-0" />
            <div class="flex-1">
              <p class="text-neutral-800 dark:text-neutral-200">{{ transitionSummary(entry) }}</p>
              <p class="text-xs text-neutral-500 dark:text-neutral-500 mt-0.5">
                {{ relativeTime(entry.timestamp) }}
              </p>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <div v-else class="text-center py-16">
      <p class="text-neutral-700 dark:text-neutral-300">Draft not found.</p>
      <button
        @click="backToList"
        class="mt-4 px-4 py-2 rounded text-sm text-primary-600 dark:text-primary-400 hover:underline"
      >
        Back to list
      </button>
    </div>
  </div>
</template>
