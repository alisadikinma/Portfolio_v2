<script setup>
import { ref, computed } from 'vue'
import {
  useSubscribersList,
  useDeleteSubscriber,
  useDigestPreview,
  useSendTest,
  useSendNow,
  useSendsList,
  exportSubscribersCsv,
} from '@/composables/useNewsletterAdmin'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const authStore = useAuthStore()
const uiStore = useUiStore()

const activeTab = ref('subscribers') // 'subscribers' | 'history'

// --- Subscribers tab ---
const searchQuery = ref('')
const sourceFilter = ref('')
const currentPage = ref(1)
const subFilters = computed(() => ({
  search: searchQuery.value || undefined,
  source: sourceFilter.value || undefined,
  per_page: 20,
  page: currentPage.value,
}))

const { subscribers, pagination, isLoading: subsLoading, error: subsError } = useSubscribersList(subFilters)

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    currentPage.value = 1
  }, 300)
}

const subToDelete = ref(null)
const deleteMutation = useDeleteSubscriber()

function confirmDelete(sub) {
  subToDelete.value = sub
}

async function handleDelete() {
  if (!subToDelete.value) return
  try {
    await deleteMutation.mutateAsync(subToDelete.value.id)
    uiStore.showSuccess(`${subToDelete.value.email} removed.`, 'Subscriber Deleted')
    subToDelete.value = null
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Delete failed.', 'Error')
  }
}

async function handleExport() {
  try {
    await exportSubscribersCsv()
    uiStore.showSuccess('CSV downloaded.', 'Export Complete')
  } catch (err) {
    uiStore.showError('Export failed.', 'Error')
  }
}

// --- Compose Digest panel ---
const previewEnabled = ref(false)
const previewQuery = useDigestPreview(previewEnabled)
const previewHtml = computed(() => previewQuery.data.value?.data?.html || '')
const previewMeta = computed(() => previewQuery.data.value?.data || null)

function openPreview() {
  previewEnabled.value = true
}

function closePreview() {
  previewEnabled.value = false
}

const sendTestRecipient = ref('')
const sendTestMutation = useSendTest()

async function handleSendTest() {
  try {
    const res = await sendTestMutation.mutateAsync({
      recipient: sendTestRecipient.value || undefined,
    })
    uiStore.showSuccess(res.message || 'Test sent.', 'Test Email Sent')
    sendTestRecipient.value = ''
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Send failed.', 'Error')
  }
}

const showSendNowConfirm = ref(false)
const sendNowConfirmed = ref(false)
const sendNowMutation = useSendNow()

function openSendNowConfirm() {
  sendNowConfirmed.value = false
  showSendNowConfirm.value = true
}

async function handleSendNow() {
  if (!sendNowConfirmed.value) return
  try {
    const res = await sendNowMutation.mutateAsync()
    uiStore.showSuccess(res.message || 'Digest queued.', 'Send Now')
    showSendNowConfirm.value = false
    activeTab.value = 'history'
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Send failed.', 'Error')
  }
}

// --- Send History tab ---
const historyStatusFilter = ref('')
const historyPage = ref(1)
const historyFilters = computed(() => ({
  status: historyStatusFilter.value || undefined,
  per_page: 20,
  page: historyPage.value,
}))
const { sends, pagination: historyPagination, isLoading: historyLoading } = useSendsList(historyFilters)

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function statusBadgeClass(status) {
  return {
    sent: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    skipped: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400',
    partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
  }[status] || 'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-400'
}

function sourceLabel(source) {
  return {
    blog_inline: 'Blog inline',
    inline_card: 'Inline card',
    floating_banner: 'Floating banner',
    footer_bar: 'Footer bar',
  }[source] || source || '—'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Newsletter
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          {{ pagination?.total ?? 0 }} subscribers · weekly digest every Friday 09:00 WIB
        </p>
      </div>

      <BaseButton @click="handleExport" button-type="secondary" :disabled="(pagination?.total ?? 0) === 0">
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export CSV
      </BaseButton>
    </div>

    <!-- Tab switcher -->
    <div class="border-b border-neutral-200 dark:border-neutral-700 mb-6">
      <nav class="flex gap-6">
        <button
          @click="activeTab = 'subscribers'"
          class="pb-3 px-1 text-sm font-medium transition-colors"
          :class="activeTab === 'subscribers'
            ? 'border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
            : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100'"
        >
          Subscribers ({{ pagination?.total ?? 0 }})
        </button>
        <button
          @click="activeTab = 'history'"
          class="pb-3 px-1 text-sm font-medium transition-colors"
          :class="activeTab === 'history'
            ? 'border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
            : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100'"
        >
          Send History ({{ historyPagination?.total ?? 0 }})
        </button>
      </nav>
    </div>

    <!-- ===== SUBSCRIBERS TAB ===== -->
    <div v-if="activeTab === 'subscribers'" class="space-y-6">
      <!-- Search & filters -->
      <BaseCard>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <BaseInput
            v-model="searchQuery"
            type="text"
            placeholder="Search name or email..."
            @input="debouncedSearch"
          />
          <select
            v-model="sourceFilter"
            class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All sources</option>
            <option value="blog_inline">Blog inline</option>
            <option value="inline_card">Inline card</option>
            <option value="floating_banner">Floating banner</option>
            <option value="footer_bar">Footer bar</option>
          </select>
        </div>
      </BaseCard>

      <!-- Table -->
      <BaseCard>
        <div v-if="subsLoading" class="flex items-center justify-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
        </div>

        <div v-else-if="subsError" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">Failed to load subscribers.</p>
        </div>

        <div v-else-if="subscribers.length === 0" class="text-center py-12">
          <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">No subscribers yet</h3>
          <p class="text-neutral-600 dark:text-neutral-400 text-sm">
            {{ searchQuery || sourceFilter ? 'No matches for current filters.' : 'Forms on /blog will collect them once visitors subscribe.' }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">WhatsApp</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Source</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Subscribed</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
              <tr
                v-for="sub in subscribers"
                :key="sub.id"
                class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                :class="(!sub.name || !sub.whatsapp_number) ? 'bg-amber-50/40 dark:bg-amber-900/10' : ''"
              >
                <td class="px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                  {{ sub.name || '—' }}
                  <span v-if="!sub.name" class="ml-1 text-[10px] text-amber-600 dark:text-amber-400 uppercase tracking-wider">legacy</span>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ sub.email }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300 font-mono">{{ sub.whatsapp_number || '—' }}</td>
                <td class="px-6 py-4 text-xs text-neutral-600 dark:text-neutral-400">{{ sourceLabel(sub.source) }}</td>
                <td class="px-6 py-4 text-xs text-neutral-600 dark:text-neutral-400">{{ formatDate(sub.created_at) }}</td>
                <td class="px-6 py-4 text-right">
                  <button
                    @click="confirmDelete(sub)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete subscriber"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="(pagination?.last_page ?? 1) > 1" class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
              Page {{ pagination?.current_page }} of {{ pagination?.last_page }}
            </p>
            <div class="flex gap-2">
              <button
                @click="currentPage = Math.max(1, currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Previous
              </button>
              <button
                @click="currentPage = Math.min(pagination?.last_page ?? 1, currentPage + 1)"
                :disabled="currentPage === pagination?.last_page"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Compose Digest panel -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-1">
          Compose digest
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-5">
          Cron fires automatically Friday 09:00 WIB. Use these for ad-hoc previews or sends.
        </p>

        <div class="space-y-3">
          <div class="flex flex-wrap gap-3">
            <BaseButton @click="openPreview" button-type="secondary">
              📧 Preview next Friday's digest
            </BaseButton>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-neutral-200 dark:border-neutral-700">
            <BaseInput
              v-model="sendTestRecipient"
              type="email"
              :placeholder="`${authStore.user?.email || 'recipient@example.com'} (default: your email)`"
              class="flex-1 min-w-[280px]"
            />
            <BaseButton @click="handleSendTest" button-type="secondary" :loading="sendTestMutation.isPending.value">
              📤 Send test
            </BaseButton>
          </div>

          <div class="pt-3 border-t border-neutral-200 dark:border-neutral-700">
            <BaseButton @click="openSendNowConfirm" button-type="primary" :disabled="(pagination?.total ?? 0) === 0">
              🚀 Send NOW to all {{ pagination?.total ?? 0 }} subscribers
            </BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- ===== SEND HISTORY TAB ===== -->
    <div v-else>
      <BaseCard class="mb-4">
        <select
          v-model="historyStatusFilter"
          class="border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option value="">All statuses</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="skipped">Skipped</option>
          <option value="partial">Partial</option>
        </select>
      </BaseCard>

      <BaseCard>
        <div v-if="historyLoading" class="flex items-center justify-center py-12">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
        </div>

        <div v-else-if="sends.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400 text-sm">No send history yet.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
            <thead class="bg-neutral-50 dark:bg-neutral-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Sent At</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Subscribers</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Posts</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Trigger</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Duration</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
              <tr v-for="send in sends" :key="send.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800">
                <td class="px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ formatDate(send.sent_at) }}</td>
                <td class="px-6 py-4">
                  <span :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full uppercase tracking-wider', statusBadgeClass(send.status)]">
                    {{ send.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ send.subscriber_count }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-300">{{ send.posts_count }}</td>
                <td class="px-6 py-4 text-xs text-neutral-600 dark:text-neutral-400">
                  {{ send.triggered_by }}
                  <span v-if="send.test_recipient" class="block text-[10px] text-neutral-500">→ {{ send.test_recipient }}</span>
                </td>
                <td class="px-6 py-4 text-xs text-neutral-600 dark:text-neutral-400">{{ send.duration_seconds ?? 0 }}s</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>
    </div>

    <!-- Delete confirm modal -->
    <Teleport to="body">
      <div v-if="subToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="subToDelete = null">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">Delete subscriber</h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Remove <strong>{{ subToDelete.email }}</strong>? They'll stop receiving the weekly digest. This is a hard delete (GDPR right-to-erasure).
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton button-type="secondary" @click="subToDelete = null" :disabled="deleteMutation.isPending.value">Cancel</BaseButton>
            <BaseButton button-type="danger" @click="handleDelete" :loading="deleteMutation.isPending.value">Delete</BaseButton>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Send-now confirm modal -->
    <Teleport to="body">
      <div v-if="showSendNowConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showSendNowConfirm = false">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">Send digest now</h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-4">
            This queues the weekly digest for delivery to all <strong>{{ pagination?.total ?? 0 }}</strong> subscribers right now. Cannot be undone once queued.
          </p>
          <label class="flex items-start gap-2 mb-6 cursor-pointer">
            <input v-model="sendNowConfirmed" type="checkbox" class="mt-1" />
            <span class="text-sm text-neutral-700 dark:text-neutral-300">
              I confirm I want to send to all {{ pagination?.total ?? 0 }} people right now.
            </span>
          </label>
          <div class="flex items-center justify-end gap-3">
            <BaseButton button-type="secondary" @click="showSendNowConfirm = false">Cancel</BaseButton>
            <BaseButton
              button-type="primary"
              :disabled="!sendNowConfirmed || sendNowMutation.isPending.value"
              :loading="sendNowMutation.isPending.value"
              @click="handleSendNow"
            >
              Send now
            </BaseButton>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Preview modal -->
    <Teleport to="body">
      <div v-if="previewEnabled" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70" @click.self="closePreview">
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-neutral-200 dark:border-neutral-700">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Digest preview</h3>
              <p v-if="previewMeta" class="text-xs text-neutral-500">
                {{ previewMeta.posts_count }} posts · {{ previewMeta.subscriber_count }} live subscribers · campaign {{ previewMeta.campaign }}
              </p>
            </div>
            <button @click="closePreview" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div v-if="previewQuery.isLoading.value" class="flex items-center justify-center flex-1 py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
          </div>
          <iframe
            v-else
            :srcdoc="previewHtml"
            class="flex-1 w-full bg-white"
            style="min-height: 600px;"
            sandbox="allow-same-origin"
          ></iframe>
        </div>
      </div>
    </Teleport>
  </div>
</template>
