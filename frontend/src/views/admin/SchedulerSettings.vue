<template>
  <section class="space-y-6">
    <!-- Section header + refresh -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50">
          Scheduler &amp; Cron Jobs
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Toggle, retime, and run-now any cron schedule. Operator changes apply within 60s of the next
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">schedule:run</code> tick.
        </p>
      </div>
      <button
        type="button"
        @click="refetch()"
        :disabled="isFetching"
        class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
      >
        {{ isFetching ? 'Refreshing…' : 'Refresh status' }}
      </button>
    </div>

    <!-- Loading -->
    <div
      v-if="isPending"
      class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8 text-center text-sm text-gray-500"
    >
      Loading scheduled commands…
    </div>

    <!-- Error -->
    <div
      v-else-if="isError"
      class="rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300"
    >
      Failed to load scheduler: {{ error?.message || 'Unknown error' }}
    </div>

    <!-- Empty -->
    <div
      v-else-if="!hasGroups"
      class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8 text-center text-sm text-gray-500"
    >
      No scheduled commands registered yet.
    </div>

    <!-- Grouped tables (organised under sub-tabs to keep the page short) -->
    <template v-else>
      <!-- Sub-tab strip -->
      <div
        role="tablist"
        aria-label="Scheduler categories"
        class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700"
      >
        <button
          v-for="tab in subTabsWithCounts"
          :key="tab.id"
          type="button"
          role="tab"
          :aria-selected="activeSubTab === tab.id"
          :aria-controls="`scheduler-subtab-${tab.id}`"
          :id="`scheduler-subtab-trigger-${tab.id}`"
          :class="[
            'relative px-4 py-2 text-sm font-medium transition-colors -mb-px border-b-2',
            activeSubTab === tab.id
              ? 'border-amber-500 text-gray-900 dark:text-amber-300'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200',
            tab.count === 0 ? 'opacity-60' : '',
          ]"
          @click="setSubTab(tab.id)"
        >
          {{ tab.label }}
          <span
            class="ml-2 inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-normal"
            :class="activeSubTab === tab.id
              ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
              : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Empty sub-tab message -->
      <div
        v-if="visibleGroups.length === 0"
        :id="`scheduler-subtab-${activeSubTab}`"
        role="tabpanel"
        :aria-labelledby="`scheduler-subtab-trigger-${activeSubTab}`"
        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8 text-center text-sm text-gray-500"
      >
        No schedules in this category yet.
      </div>

      <!-- Active sub-tab body — only render rows belonging to bucketed categories -->
      <section
        v-for="[category, rows] in visibleGroups"
        :key="category"
        :id="`scheduler-subtab-${activeSubTab}`"
        role="tabpanel"
        :aria-labelledby="`scheduler-subtab-trigger-${activeSubTab}`"
        class="space-y-2"
      >
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
          {{ formatCategoryLabel(category) }}
          <span class="ml-2 text-gray-400 dark:text-gray-500 normal-case font-normal">
            {{ rows.length }} {{ rows.length === 1 ? 'job' : 'jobs' }}
          </span>
        </h3>

        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Name</th>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Schedule</th>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Enabled</th>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Last Run</th>
                  <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Next Run</th>
                  <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                <tr
                  v-for="row in rows"
                  :key="row.id"
                  :class="row.is_placeholder ? 'bg-amber-50/40 dark:bg-amber-900/10' : ''"
                >
                  <!-- Name -->
                  <td class="px-4 py-3 align-top max-w-md">
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                      {{ row.display_name }}
                    </div>
                    <div class="mt-0.5 text-[11px] font-mono text-gray-400 dark:text-gray-500 break-all">
                      {{ row.signature }}<template v-if="row.arguments?.length"> {{ row.arguments.join(' ') }}</template>
                    </div>
                    <p
                      v-if="row.description"
                      class="mt-1.5 text-xs leading-relaxed text-gray-600 dark:text-gray-300"
                    >
                      {{ row.description }}
                    </p>
                    <span
                      v-if="row.is_placeholder"
                      class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300"
                    >
                      🔒 Coming soon
                    </span>
                  </td>

                  <!-- Schedule -->
                  <td class="px-4 py-3 align-top min-w-[260px]">
                    <select
                      :value="getPresetValue(row)"
                      @change="onPresetChange(row, $event.target.value)"
                      :disabled="row.is_placeholder || isUpdating(row.id)"
                      class="block w-full text-xs rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 disabled:opacity-50"
                    >
                      <option v-for="p in CRON_PRESETS" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <input
                      v-if="getPresetValue(row) === 'custom'"
                      type="text"
                      :value="row.cron_expression"
                      @blur="onCronInput(row, $event.target.value)"
                      :disabled="row.is_placeholder || isUpdating(row.id)"
                      placeholder="* * * * *"
                      class="mt-1 block w-full text-xs font-mono rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 disabled:opacity-50"
                    />
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" :title="row.cron_expression">
                      {{ row.cron_human_readable }}
                    </div>
                    <div v-if="row.timezone" class="mt-0.5 text-[10px] uppercase tracking-wide text-gray-400">
                      {{ row.timezone }}
                    </div>
                  </td>

                  <!-- Enabled -->
                  <td class="px-4 py-3 align-top">
                    <button
                      type="button"
                      role="switch"
                      :aria-checked="row.enabled"
                      @click="toggleEnabled(row)"
                      :disabled="row.is_placeholder || isUpdating(row.id)"
                      :class="[
                        row.enabled ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600',
                        'relative inline-flex h-6 w-11 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50',
                      ]"
                    >
                      <span
                        :class="row.enabled ? 'translate-x-6' : 'translate-x-1'"
                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                      />
                    </button>
                  </td>

                  <!-- Last run -->
                  <td class="px-4 py-3 align-top">
                    <div class="flex items-center gap-2">
                      <span
                        :class="statusPillClass(row.last_status)"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                      >
                        {{ row.last_status || 'never' }}
                      </span>
                      <span
                        class="text-xs text-gray-500 dark:text-gray-400"
                        :title="row.last_run_at || 'Has never run'"
                      >
                        {{ formatRelativeTime(row.last_run_at) }}
                      </span>
                    </div>
                    <div v-if="row.last_duration_ms" class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                      {{ formatDuration(row.last_duration_ms) }}
                    </div>
                    <div
                      v-if="row.last_status === 'failed' && row.last_error"
                      class="mt-1 text-[11px] text-red-600 dark:text-red-400 line-clamp-2"
                      :title="row.last_error"
                    >
                      {{ row.last_error }}
                    </div>
                  </td>

                  <!-- Next run -->
                  <td class="px-4 py-3 align-top">
                    <span
                      class="text-xs text-gray-700 dark:text-gray-300"
                      :title="row.next_run_at || 'Not scheduled'"
                    >
                      {{ formatRelativeTime(row.next_run_at, 'future') }}
                    </span>
                  </td>

                  <!-- Actions -->
                  <td class="px-4 py-3 text-right align-top">
                    <button
                      type="button"
                      @click="onRunNow(row)"
                      :disabled="row.is_placeholder || !row.enabled || isRunning(row.id)"
                      class="inline-flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                      :title="runButtonTitle(row)"
                    >
                      <span aria-hidden="true">▶</span>
                      {{ isRunning(row.id) ? 'Queued…' : 'Run Now' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </template>

    <!-- Mutation feedback (shared, transient) -->
    <div
      v-if="updateMutation.isError.value"
      class="rounded border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 p-3 text-xs text-red-700 dark:text-red-300"
    >
      Update failed: {{ updateMutation.error.value?.response?.data?.message || updateMutation.error.value?.message }}
    </div>
    <div
      v-if="runMutation.isError.value"
      class="rounded border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 p-3 text-xs text-red-700 dark:text-red-300"
    >
      Run-now failed: {{ runMutation.error.value?.response?.data?.message || runMutation.error.value?.message }}
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
  useScheduledCommands,
  useUpdateScheduledCommand,
  useRunScheduledCommand,
} from '@/composables/useScheduler'

// ---------------------------------------------------------------------------
// Data + mutations
// ---------------------------------------------------------------------------
const {
  data: groupsData,
  isPending,
  isError,
  isFetching,
  error,
  refetch,
} = useScheduledCommands()

const updateMutation = useUpdateScheduledCommand()
const runMutation = useRunScheduledCommand()

const groups = computed(() => groupsData.value || {})
const hasGroups = computed(() => Object.keys(groups.value).length > 0)

// ---------------------------------------------------------------------------
// Sub-tabs — bucket the 7 raw API categories into 4 operator-friendly tabs
// so the page doesn't require scrolling. `system` (currently only holds
// posting-rules:research --platform=linkedin) is bucketed under LinkedIn for
// v1 since the only seeded variant is linkedin-platform research. When future
// IG/TT/FB research commands ship under `system`, split into a 5th tab.
// ---------------------------------------------------------------------------
const SUB_TABS = [
  { id: 'content_engine', label: 'Content Engine', categories: ['content_engine'] },
  { id: 'linkedin',       label: 'LinkedIn',       categories: ['linkedin', 'system'] },
  { id: 'social',         label: 'Social Media',   categories: ['instagram', 'facebook', 'tiktok'] },
  { id: 'newsletter',     label: 'Newsletter',     categories: ['newsletter'] },
]

const SUBTAB_STORAGE_KEY = 'admin:scheduler:subtab'

const activeSubTab = ref(
  (typeof localStorage !== 'undefined' && localStorage.getItem(SUBTAB_STORAGE_KEY))
  || 'content_engine'
)

function setSubTab(id) {
  if (!SUB_TABS.find(t => t.id === id)) return
  activeSubTab.value = id
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(SUBTAB_STORAGE_KEY, id)
  }
}

// Sub-tab strip data: id + label + total job count across the tab's categories
const subTabsWithCounts = computed(() => SUB_TABS.map(tab => ({
  ...tab,
  count: tab.categories.reduce((sum, cat) => sum + (groups.value[cat]?.length || 0), 0),
})))

// Active sub-tab's groups as [category, rows] entries — preserves category
// sub-headers within the tab (e.g. Social tab shows separate IG / FB / TT
// sections)
const visibleGroups = computed(() => {
  const tab = SUB_TABS.find(t => t.id === activeSubTab.value)
  if (!tab) return []
  return tab.categories
    .map(cat => [cat, groups.value[cat]])
    .filter(([, rows]) => rows && rows.length > 0)
})

// Per-row pending markers — multiple rows may be in-flight concurrently
const updatingIds = ref(new Set())
const runningIds = ref(new Set())

function isUpdating(id) {
  return updatingIds.value.has(id)
}
function isRunning(id) {
  return runningIds.value.has(id)
}

// ---------------------------------------------------------------------------
// Cron presets (user-friendly dropdown)
// ---------------------------------------------------------------------------
const CRON_PRESETS = [
  { label: 'Every minute',                            value: '* * * * *' },
  { label: 'Every 5 minutes',                         value: '*/5 * * * *' },
  { label: 'Every 10 minutes',                        value: '*/10 * * * *' },
  { label: 'Every 15 minutes',                        value: '*/15 * * * *' },
  { label: 'Every 30 minutes',                        value: '*/30 * * * *' },
  { label: 'Hourly',                                  value: '0 * * * *' },
  { label: 'Daily 03:00',                             value: '0 3 * * *' },
  { label: 'Daily 04:00',                             value: '0 4 * * *' },
  { label: 'Daily 04:30',                             value: '30 4 * * *' },
  { label: 'Daily 05:00',                             value: '0 5 * * *' },
  { label: 'Weekly Friday 09:00',                     value: '0 9 * * 5' },
  { label: 'Quarterly (1st Jan/Apr/Jul/Oct 03:00)',   value: '0 3 1 */3 *' },
  { label: 'Custom…',                                 value: 'custom' },
]
const PRESET_VALUES = new Set(
  CRON_PRESETS.filter(p => p.value !== 'custom').map(p => p.value)
)

function getPresetValue(row) {
  return PRESET_VALUES.has(row.cron_expression) ? row.cron_expression : 'custom'
}

// ---------------------------------------------------------------------------
// Mutation handlers
// ---------------------------------------------------------------------------
function applyUpdate(row, payload) {
  updatingIds.value.add(row.id)
  updateMutation.mutate(
    { id: row.id, payload },
    {
      onSettled: () => {
        updatingIds.value.delete(row.id)
      },
    }
  )
}

function onPresetChange(row, value) {
  // Selecting "Custom" just reveals the text input; do not write the literal "custom"
  if (value === 'custom') return
  if (value === row.cron_expression) return
  applyUpdate(row, { cron_expression: value })
}

function onCronInput(row, value) {
  const trimmed = (value || '').trim()
  if (!trimmed || trimmed === row.cron_expression) return
  applyUpdate(row, { cron_expression: trimmed })
}

function toggleEnabled(row) {
  applyUpdate(row, { enabled: !row.enabled })
}

function onRunNow(row) {
  runningIds.value.add(row.id)
  runMutation.mutate(row.id, {
    onSettled: () => {
      runningIds.value.delete(row.id)
    },
  })
}

function runButtonTitle(row) {
  if (row.is_placeholder) return 'Placeholder schedule — implementation pending'
  if (!row.enabled) return 'Enable this schedule first'
  if (isRunning(row.id)) return 'Already queued'
  return 'Queue this command immediately (artisan job dispatched to the queue worker)'
}

// ---------------------------------------------------------------------------
// Display helpers
// ---------------------------------------------------------------------------
const CATEGORY_LABELS = {
  content_engine: 'Content Engine',
  linkedin:       'LinkedIn',
  newsletter:     'Newsletter',
  system:         'System',
  instagram:      'Instagram',
  facebook:       'Facebook',
  tiktok:         'TikTok',
  posting_rules:  'Posting Time Rules',
}

function formatCategoryLabel(key) {
  return CATEGORY_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function statusPillClass(status) {
  const map = {
    success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    failed:  'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    running: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300 animate-pulse',
    skipped: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    never:   'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
  }
  return map[status] || map.never
}

function formatRelativeTime(iso, hint = 'past') {
  if (!iso) return hint === 'future' ? 'not scheduled' : 'never'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return hint === 'future' ? 'not scheduled' : 'never'

  const now = Date.now()
  const diffMs = date.getTime() - now
  const absMs = Math.abs(diffMs)
  const sec = Math.round(absMs / 1000)
  const min = Math.round(sec / 60)
  const hr  = Math.round(min / 60)
  const day = Math.round(hr / 24)

  let value
  if (sec < 60)      value = `${sec}s`
  else if (min < 60) value = `${min}m`
  else if (hr < 24)  value = `${hr}h`
  else               value = `${day}d`

  return diffMs < 0 ? `${value} ago` : `in ${value}`
}

function formatDuration(ms) {
  if (!ms || ms < 0) return ''
  if (ms < 1000) return `${ms}ms`
  const sec = ms / 1000
  if (sec < 60) return `${sec.toFixed(1)}s`
  const min = Math.floor(sec / 60)
  const remSec = Math.round(sec - min * 60)
  return `${min}m ${remSec}s`
}
</script>
