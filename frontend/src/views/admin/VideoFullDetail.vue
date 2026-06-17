<script setup>
import { computed, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useVideoFullJob, useRegenerateSegment, usePublishVideoFull } from '@/composables/useVideoFull.js'
import { statusLabel, segmentDot, VIDEO_FULL_PLATFORMS } from './videoFullHelpers.js'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { job, segments, workerOnline, isLoading } = useVideoFullJob(id)
const regen = useRegenerateSegment()
const publish = usePublishVideoFull()

const PLATFORMS = VIDEO_FULL_PLATFORMS
const selected = ref(['instagram', 'tiktok', 'threads'])

function regenerate(index) {
  if (!confirm(`Regenerate segmen ${index}? Worker akan render ulang segmen ini.`)) return
  regen.mutate({ id: id.value, index })
}

function doPublish() {
  if (!selected.value.length) return
  if (!confirm(`Publish reel ke: ${selected.value.join(', ')}?`)) return
  publish.mutate({ id: id.value, platforms: selected.value })
}
</script>

<template>
  <div class="p-6 text-slate-100">
    <RouterLink :to="{ name: 'admin-video-full' }" class="text-sm text-slate-400 hover:underline">← Semua job</RouterLink>

    <div v-if="isLoading" class="text-slate-400 mt-6">Loading…</div>
    <template v-else-if="job">
      <div class="flex items-center justify-between mt-3 mb-4">
        <h1 class="text-xl font-semibold">Job #{{ job.id }} · {{ statusLabel(job.status) }}</h1>
        <span :class="workerOnline ? 'text-emerald-400' : 'text-amber-400'" class="text-sm">
          {{ workerOnline ? '● Worker online' : '○ Worker offline — nyalakan daemon di MacBook' }}
        </span>
      </div>

      <div class="w-full h-2 bg-slate-800 rounded mb-1">
        <div class="h-2 bg-amber-500 rounded transition-all" :style="{ width: (job.worker_progress || 0) + '%' }" />
      </div>
      <p class="text-xs text-slate-500 mb-6">{{ job.worker_progress || 0 }}% · {{ job.worker_step || '—' }}</p>

      <div v-if="job.final_video_url" class="mb-8">
        <h2 class="text-sm font-semibold text-slate-300 mb-2">Final reel</h2>
        <video :src="job.final_video_url" controls class="max-w-[360px] rounded-lg border border-slate-800" />
        <a :href="job.final_video_url" download class="block text-sm text-amber-400 hover:underline mt-2">⬇ Download MP4</a>

        <div class="mt-4 p-3 rounded bg-slate-900/60 border border-slate-800 max-w-[360px]">
          <p class="text-sm font-semibold text-slate-300 mb-2">Publish via Zernio</p>
          <label v-for="p in PLATFORMS" :key="p" class="inline-flex items-center mr-3 text-xs text-slate-300">
            <input type="checkbox" :value="p" v-model="selected" class="mr-1" /> {{ p }}
          </label>
          <button class="mt-3 block w-full px-3 py-2 rounded bg-amber-600 hover:bg-amber-500 text-sm font-semibold disabled:opacity-50"
                  :disabled="publish.isPending.value || !selected.length" @click="doPublish">
            {{ publish.isPending.value ? 'Publishing…' : 'Approve & Publish' }}
          </button>
          <p v-if="publish.data.value" class="text-xs text-emerald-400 mt-2">
            Dispatched: {{ (publish.data.value.dispatched || []).join(', ') || '—' }}
            <span v-if="(publish.data.value.skipped || []).length" class="text-slate-500">· skipped: {{ publish.data.value.skipped.join(', ') }}</span>
          </p>
        </div>
      </div>

      <h2 class="text-sm font-semibold text-slate-300 mb-3">Segmen ({{ segments.length }})</h2>
      <div class="space-y-2">
        <div v-for="s in segments" :key="s.segment_index"
             class="flex items-center gap-3 p-3 rounded bg-slate-900/60 border border-slate-800">
          <span class="w-2 h-2 rounded-full" :class="segmentDot(s.status)" />
          <span class="w-8 text-slate-500 text-sm">#{{ s.segment_index }}</span>
          <span class="text-xs px-2 py-0.5 rounded bg-slate-800">{{ s.type }}</span>
          <span class="text-xs text-slate-500">{{ s.strategy }}</span>
          <span class="flex-1 text-sm text-slate-300 truncate">{{ s.text_id || s.source_text_en }}</span>
          <video v-if="s.preview_url" :src="s.preview_url" class="h-12 rounded" muted />
          <button class="text-xs px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-amber-400"
                  :disabled="regen.isPending.value" @click="regenerate(s.segment_index)">
            ↻ Regenerate
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
