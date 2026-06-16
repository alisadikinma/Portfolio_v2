<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useVideoFullList } from '@/composables/useVideoFull.js'
import { statusLabel, workerOnline } from './videoFullHelpers.js'

const filters = ref({ per_page: 25 })
const { jobs, isLoading, isFetching, refetch } = useVideoFullList(filters)
</script>

<template>
  <div class="p-6 text-slate-100">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold">🎥 Video 60s</h1>
        <p class="text-sm text-slate-400">Full talking-head reels — rendered by the MacBook worker.</p>
      </div>
      <button class="text-sm px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700"
              :disabled="isFetching" @click="refetch">
        {{ isFetching ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="isLoading" class="text-slate-400">Loading…</div>
    <div v-else-if="!jobs.length" class="text-slate-500 py-12 text-center">
      Belum ada job. Kirim URL IG ke bot Telegram → tombol “🎥 Video 60s”.
    </div>

    <table v-else class="w-full text-sm">
      <thead class="text-left text-slate-400 border-b border-slate-800">
        <tr>
          <th class="py-2">#</th><th>Source</th><th>Status</th>
          <th>Progress</th><th>Segmen</th><th>Worker</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="j in jobs" :key="j.id" class="border-b border-slate-900 hover:bg-slate-900/50">
          <td class="py-3">{{ j.id }}</td>
          <td class="max-w-[280px] truncate">
            <a :href="j.source_url" target="_blank" class="text-cyan-400 hover:underline">{{ j.source_url }}</a>
          </td>
          <td>{{ statusLabel(j.status) }}</td>
          <td>
            <div class="w-24 h-1.5 bg-slate-800 rounded">
              <div class="h-1.5 bg-amber-500 rounded" :style="{ width: (j.worker_progress || 0) + '%' }" />
            </div>
          </td>
          <td>{{ j.video_full_segments_count ?? '—' }}</td>
          <td>
            <span :class="workerOnline(j.worker_heartbeat_at) ? 'text-emerald-400' : 'text-slate-500'">
              {{ workerOnline(j.worker_heartbeat_at) ? '● online' : '○ offline' }}
            </span>
          </td>
          <td class="text-right">
            <RouterLink :to="{ name: 'admin-video-full-detail', params: { id: j.id } }"
                        class="text-amber-400 hover:underline">Detail →</RouterLink>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
