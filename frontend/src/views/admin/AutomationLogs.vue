<template>
  <div class="automation-logs-page">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
        Automation Logs
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Monitor API requests and automation activity
      </p>
    </div>

    <!-- Filters -->
    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Action</label>
          <select
            v-model="filters.action"
            @change="fetchLogs"
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
          >
            <option value="">All Actions</option>
            <option value="posts.index">List Posts</option>
            <option value="posts.show">Get Post</option>
            <option value="posts.create">Create Post</option>
            <option value="posts.update">Update Post</option>
            <option value="posts.delete">Delete Post</option>
            <option value="posts.bulk_create">Bulk Create</option>
            <option value="categories.index">List Categories</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date From</label>
          <input
            type="date"
            v-model="filters.date_from"
            @change="fetchLogs"
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date To</label>
          <input
            type="date"
            v-model="filters.date_to"
            @change="fetchLogs"
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
          />
        </div>

        <div class="flex items-end">
          <button
            @click="clearFilters"
            class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Clear Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Logs</h2>
          <button
            @click="confirmClearAll"
            class="text-sm text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
          >
            Clear All Logs
          </button>
        </div>
      </div>

      <div v-if="automationStore.loading" class="flex items-center justify-center p-12">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
      </div>

      <div v-else-if="automationStore.logs.length === 0" class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No logs yet</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          API requests will appear here once you start using the automation endpoints
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Timestamp</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Action</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Token</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">IP Address</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Metadata</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="log in automationStore.logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="getActionBadgeClass(log.action)"
                  class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                >
                  {{ log.action }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ log.token?.name || 'N/A' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ log.ip_address }}
              </td>
              <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                <button
                  v-if="log.metadata"
                  @click="viewMetadata(log)"
                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  View Details
                </button>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="viewMetadata(log)"
                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="automationStore.logs.length > 0" class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700 dark:text-gray-300">
            Showing {{ (automationStore.pagination.currentPage - 1) * automationStore.pagination.perPage + 1 }}
            to {{ Math.min(automationStore.pagination.currentPage * automationStore.pagination.perPage, automationStore.pagination.total) }}
            of {{ automationStore.pagination.total }} results
          </div>

          <div class="flex space-x-2">
            <button
              @click="changePage(automationStore.pagination.currentPage - 1)"
              :disabled="automationStore.pagination.currentPage === 1"
              class="rounded-lg border border-gray-300 px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Previous
            </button>
            <button
              @click="changePage(automationStore.pagination.currentPage + 1)"
              :disabled="automationStore.pagination.currentPage === automationStore.pagination.lastPage"
              class="rounded-lg border border-gray-300 px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Metadata Modal -->
    <div
      v-if="showMetadataModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showMetadataModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Log Details</h3>

          <div class="mt-4 space-y-4">
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Action:</label>
              <p class="text-sm text-gray-900 dark:text-white">{{ selectedLog?.action }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">IP Address:</label>
              <p class="text-sm text-gray-900 dark:text-white">{{ selectedLog?.ip_address }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">User Agent:</label>
              <p class="text-sm text-gray-900 dark:text-white">{{ selectedLog?.user_agent }}</p>
            </div>

            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Metadata:</label>
              <pre class="mt-2 rounded-lg bg-gray-100 p-4 text-xs text-gray-900 dark:bg-gray-700 dark:text-white overflow-x-auto">{{ formatMetadata(selectedLog?.metadata) }}</pre>
            </div>
          </div>

          <div class="mt-6">
            <button
              @click="showMetadataModal = false"
              class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clear All Confirmation Modal -->
    <div
      v-if="showClearModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showClearModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Clear All Logs</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to delete all logs? This action cannot be undone.
          </p>

          <div class="mt-6 flex space-x-3">
            <button
              @click="clearAllLogs"
              class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
              Clear All
            </button>
            <button
              @click="showClearModal = false"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAutomation } from '@/stores/automation'
import { useToast } from '@/stores/ui'

const automationStore = useAutomation()
const toast = useToast()

const showMetadataModal = ref(false)
const showClearModal = ref(false)
const selectedLog = ref(null)

const filters = ref({
  action: '',
  date_from: '',
  date_to: '',
  page: 1
})

onMounted(async () => {
  await fetchLogs()
})

const fetchLogs = async () => {
  try {
    await automationStore.fetchLogs(filters.value)
  } catch (error) {
    toast.error('Failed to load logs')
  }
}

const changePage = (page) => {
  filters.value.page = page
  fetchLogs()
}

const clearFilters = () => {
  filters.value = { action: '', date_from: '', date_to: '', page: 1 }
  fetchLogs()
}

const viewMetadata = (log) => {
  selectedLog.value = log
  showMetadataModal.value = true
}

const confirmClearAll = () => {
  showClearModal.value = true
}

const clearAllLogs = async () => {
  try {
    await automationStore.clearLogs()
    showClearModal.value = false
    toast.success('All logs cleared successfully')
  } catch (error) {
    toast.error('Failed to clear logs')
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

const formatMetadata = (metadata) => {
  if (typeof metadata === 'string') {
    try {
      return JSON.stringify(JSON.parse(metadata), null, 2)
    } catch {
      return metadata
    }
  }
  return JSON.stringify(metadata, null, 2)
}

const getActionBadgeClass = (action) => {
  if (action.includes('create')) {
    return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
  } else if (action.includes('delete') || action.includes('failed')) {
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
  } else if (action.includes('update')) {
    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
  } else {
    return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  }
}
</script>
