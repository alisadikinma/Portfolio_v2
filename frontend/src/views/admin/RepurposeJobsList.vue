<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useRepurposeJobsList } from '@/composables/useRepurposeJobs'
import { statusTone, statusLabel, modeLabel, relativeTime, inferFailedStep } from './repurposeHelpers.js'

const router = useRouter()

const TABS = [
  { key: '', label: 'All' },
  { key: 'failed', label: 'Failed' },
  { key: 'drafted', label: 'Drafted' },
]
const activeTab = ref('')

const filters = computed(() => ({ status: activeTab.value || undefined, per_page: 50 }))
const { jobs, pagination, isLoading, isFetching, refetch } = useRepurposeJobsList(filters)

function openDetail(id) {
  router.push({ name: 'admin-repurpose-detail', params: { id } })
}
function shortUrl(url) {
  if (!url) return '—'
  return url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')
}
</script>

<template>
  <div class="px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-slate-100">IG Repurpose</h1>
        <p class="mt-1 text-sm text-slate-400">
          Telegram → Instagram repurpose jobs. Live status updates while a pipeline runs.
        </p>
      </div>
      <button
        class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-slate-200 ring-1 ring-slate-700 transition hover:bg-slate-700 active:scale-[0.98]"
        :disabled="isFetching"
        @click="refetch()"
      >
        <span v-if="isFetching">Refreshing…</span>
        <span v-else>Refresh</span>
      </button>
    </div>

    <div class="mb-4 flex gap-2">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="rounded-full px-4 py-1.5 text-sm font-medium ring-1 transition"
        :class="activeTab === tab.key
          ? 'bg-cyan-500/20 text-cyan-200 ring-cyan-500/40'
          : 'bg-slate-800/60 text-slate-300 ring-slate-700 hover:bg-slate-800'"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="overflow-hidden rounded-xl ring-1 ring-slate-800">
      <table class="min-w-full divide-y divide-slate-800 text-sm">
        <thead class="bg-slate-900/60 text-left text-xs uppercase tracking-wide text-slate-400">
          <tr>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Mode</th>
            <th class="px-4 py-3 font-medium">Source</th>
            <th class="px-4 py-3 font-medium">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-900/30">
          <tr v-if="isLoading">
            <td colspan="4" class="px-4 py-10 text-center text-slate-500">Loading…</td>
          </tr>
          <tr v-else-if="jobs.length === 0">
            <td colspan="4" class="px-4 py-10 text-center text-slate-500">
              No repurpose jobs yet. Send an Instagram post URL to the Telegram bot to start one.
            </td>
          </tr>
          <tr
            v-for="job in jobs"
            :key="job.id"
            class="cursor-pointer transition hover:bg-slate-800/50"
            @click="openDetail(job.id)"
          >
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                :class="statusTone(job.status)"
              >
                {{ statusLabel(job.status) }}
              </span>
              <span
                v-if="job.status === 'failed' && inferFailedStep(job.pipeline_state_log)"
                class="ml-2 text-xs text-red-400/80"
              >
                at {{ inferFailedStep(job.pipeline_state_log) }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-300">{{ modeLabel(job.mode) }}</td>
            <td class="max-w-[22rem] truncate px-4 py-3 text-slate-400">{{ shortUrl(job.source_url) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ relativeTime(job.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="pagination" class="mt-3 text-xs text-slate-500">
      {{ pagination.total }} job(s) · page {{ pagination.current_page }} / {{ pagination.last_page }}
    </p>
  </div>
</template>
