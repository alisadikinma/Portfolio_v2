<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Contact Messages
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Total: {{ pagination.total }} messages ({{ unreadCount }} unread)
        </p>
      </div>

      <!-- Export Button -->
      <BaseButton
        @click="handleExport"
        button-type="secondary"
        :disabled="loading || contacts.length === 0"
      >
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Export CSV
      </BaseButton>
    </div>

    <!-- Search and Filters -->
    <BaseCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Search -->
        <div>
          <BaseInput
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, email, subject..."
            @input="debouncedSearch"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </BaseInput>
        </div>

        <!-- Read Status Filter -->
        <div>
          <select
            v-model="readFilter"
            @change="fetchContacts"
            class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All Messages</option>
            <option value="false">Unread Only</option>
            <option value="true">Read Only</option>
          </select>
        </div>
      </div>
    </BaseCard>

    <!-- Contacts Table -->
    <BaseCard>
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-neutral-600 dark:text-neutral-400 mb-4">{{ error }}</p>
        <BaseButton @click="fetchContacts">Retry</BaseButton>
      </div>

      <!-- Empty State -->
      <div v-else-if="contacts.length === 0" class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Contact Messages
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400">
          {{ searchQuery ? 'No messages found matching your search' : 'No contact messages have been received yet' }}
        </p>
      </div>

      <!-- Contacts Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
          <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                From
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Subject
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Date
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
            <tr
              v-for="contact in contacts"
              :key="contact.id"
              class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
              :class="{ 'font-semibold': !contact.is_read }"
              @click="viewContact(contact)"
            >
              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                    !contact.is_read
                      ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                      : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-400'
                  ]"
                >
                  {{ contact.is_read ? 'Read' : 'Unread' }}
                </span>
              </td>

              <!-- From -->
              <td class="px-6 py-4">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ contact.name }}
                </div>
                <div class="text-sm text-neutral-500 dark:text-neutral-400">
                  {{ contact.email }}
                </div>
              </td>

              <!-- Subject -->
              <td class="px-6 py-4">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ contact.subject }}
                </div>
              </td>

              <!-- Date -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ formatDate(contact.created_at) }}
                </div>
                <div v-if="contact.read_at" class="text-xs text-neutral-500 dark:text-neutral-400">
                  Read: {{ formatDate(contact.read_at) }}
                </div>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" @click.stop>
                <div class="flex items-center justify-end gap-2">
                  <!-- View -->
                  <button
                    @click="viewContact(contact)"
                    class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                    title="View message"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>

                  <!-- Mark as Read/Unread -->
                  <button
                    @click="toggleReadStatus(contact)"
                    class="text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-300"
                    :title="contact.is_read ? 'Mark as unread' : 'Mark as read'"
                  >
                    <svg v-if="!contact.is_read" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </button>

                  <!-- Delete -->
                  <button
                    @click="confirmDelete(contact)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete message"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between">
            <div class="text-sm text-neutral-600 dark:text-neutral-400">
              Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to
              {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
              {{ pagination.total }} results
            </div>

            <div class="flex items-center gap-2">
              <!-- Previous -->
              <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Previous
              </button>

              <!-- Page Numbers -->
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="changePage(page)"
                :class="[
                  'px-3 py-1 border rounded-md text-sm',
                  page === pagination.current_page
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'border-neutral-300 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                ]"
              >
                {{ page }}
              </button>

              <!-- Next -->
              <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </BaseCard>

    <!-- View Contact Modal -->
    <Teleport to="body">
      <div
        v-if="selectedContact"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="selectedContact = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-6 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
              Contact Message
            </h3>
            <button
              @click="selectedContact = null"
              class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-4">
            <!-- From -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                From:
              </label>
              <div class="text-neutral-900 dark:text-neutral-100">
                {{ selectedContact.name }} ({{ selectedContact.email }})
              </div>
            </div>

            <!-- Subject -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Subject:
              </label>
              <div class="text-neutral-900 dark:text-neutral-100">
                {{ selectedContact.subject }}
              </div>
            </div>

            <!-- Message -->
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Message:
              </label>
              <div class="text-neutral-900 dark:text-neutral-100 whitespace-pre-wrap bg-neutral-50 dark:bg-neutral-900 p-4 rounded-lg">
                {{ selectedContact.message }}
              </div>
            </div>

            <!-- Metadata -->
            <div class="flex items-center gap-6 text-sm text-neutral-600 dark:text-neutral-400">
              <div>
                <span class="font-medium">Received:</span> {{ formatDate(selectedContact.created_at) }}
              </div>
              <div v-if="selectedContact.read_at">
                <span class="font-medium">Read:</span> {{ formatDate(selectedContact.read_at) }}
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-end gap-3 p-6 border-t border-neutral-200 dark:border-neutral-700">
            <BaseButton
              button-type="secondary"
              @click="selectedContact = null"
            >
              Close
            </BaseButton>
            <a
              :href="`mailto:${selectedContact.email}?subject=Re: ${selectedContact.subject}`"
              class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 transition-colors"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Reply
            </a>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="contactToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="contactToDelete = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Delete Contact Message
          </h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Are you sure you want to delete the message from "<strong>{{ contactToDelete.name }}</strong>"? This action cannot be undone.
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton
              button-type="secondary"
              @click="contactToDelete = null"
              :disabled="isDeleting"
            >
              Cancel
            </BaseButton>
            <BaseButton
              button-type="danger"
              @click="handleDelete"
              :loading="isDeleting"
              :disabled="isDeleting"
            >
              Delete Message
            </BaseButton>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useContactsStore } from '@/stores/contacts'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const contactsStore = useContactsStore()
const uiStore = useUiStore()

const searchQuery = ref('')
const readFilter = ref('')
const selectedContact = ref(null)
const contactToDelete = ref(null)
const isDeleting = ref(false)

const contacts = computed(() => contactsStore.contacts)
const pagination = computed(() => contactsStore.pagination)
const loading = computed(() => contactsStore.loading)
const error = computed(() => contactsStore.error)
const unreadCount = computed(() => contactsStore.unreadCount)

// Visible page numbers for pagination
const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  const pages = []

  let start = Math.max(1, current - 2)
  let end = Math.min(last, start + 4)

  if (end - start < 4) {
    start = Math.max(1, end - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})

onMounted(() => {
  fetchContacts()
})

async function fetchContacts() {
  try {
    const filters = {}
    if (searchQuery.value) filters.search = searchQuery.value
    if (readFilter.value) filters.is_read = readFilter.value

    await contactsStore.fetchContacts(pagination.value.current_page, 20, filters)
  } catch (err) {
    console.error('Failed to fetch contacts:', err)
  }
}

let searchTimeout
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchContacts()
  }, 300)
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    contactsStore.pagination.current_page = page
    fetchContacts()
  }
}

async function viewContact(contact) {
  try {
    // Fetch full contact details (marks as read automatically)
    await contactsStore.fetchContact(contact.id)
    selectedContact.value = contactsStore.currentContact
  } catch (err) {
    console.error('Failed to view contact:', err)
    uiStore.showError('Failed to load contact message', 'Error')
  }
}

async function toggleReadStatus(contact) {
  try {
    if (!contact.is_read) {
      await contactsStore.markAsRead(contact.id)
      uiStore.showSuccess('Message marked as read', 'Success')
    }
    // Note: Backend doesn't have markAsUnread, so we only support marking as read
  } catch (err) {
    console.error('Failed to update read status:', err)
    uiStore.showError('Failed to update message status', 'Error')
  }
}

function confirmDelete(contact) {
  contactToDelete.value = contact
}

async function handleDelete() {
  if (!contactToDelete.value) return

  isDeleting.value = true

  try {
    await contactsStore.deleteContact(contactToDelete.value.id)

    uiStore.showSuccess(
      `Message from "${contactToDelete.value.name}" has been deleted.`,
      'Message Deleted'
    )

    contactToDelete.value = null

    // Reload contacts if current page is now empty
    if (contacts.value.length === 0 && pagination.value.current_page > 1) {
      pagination.value.current_page--
    }
    await fetchContacts()
  } catch (err) {
    console.error('Failed to delete contact:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to delete message. Please try again.',
      'Delete Failed',
      0
    )
  } finally {
    isDeleting.value = false
  }
}

async function handleExport() {
  try {
    const filters = {}
    if (searchQuery.value) filters.search = searchQuery.value
    if (readFilter.value) filters.is_read = readFilter.value

    await contactsStore.exportContacts(filters)

    uiStore.showSuccess('Contacts exported successfully', 'Export Complete')
  } catch (err) {
    console.error('Failed to export contacts:', err)
    uiStore.showError('Failed to export contacts. Please try again.', 'Export Failed')
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
