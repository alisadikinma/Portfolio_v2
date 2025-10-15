<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Manage Awards
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Total: {{ pagination.total }} awards
        </p>
      </div>
      <BaseButton @click="router.push('/admin/awards/create')">
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Award
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
            placeholder="Search awards by title or organization..."
            @input="debouncedSearch"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </BaseInput>
        </div>
      </div>
    </BaseCard>

    <!-- Awards Table -->
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
        <BaseButton @click="fetchAwards">Retry</BaseButton>
      </div>

      <!-- Empty State -->
      <div v-else-if="awards.length === 0" class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Awards Found
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          {{ searchQuery ? 'Try adjusting your search criteria' : 'Get started by creating your first award' }}
        </p>
        <BaseButton v-if="!searchQuery" @click="router.push('/admin/awards/create')">
          Create Award
        </BaseButton>
      </div>

      <!-- Awards Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
          <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Award
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Organization
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Date
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Credential
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Galleries
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
            <tr v-for="award in awards" :key="award.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
              <!-- Award Info -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div v-if="award.image" class="flex-shrink-0 h-10 w-10">
                    <img :src="award.image" :alt="award.title" class="h-10 w-10 rounded-lg object-cover">
                  </div>
                  <div :class="award.image ? 'ml-4' : ''">
                    <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                      {{ award.title }}
                    </div>
                    <div v-if="award.order !== null" class="text-sm text-neutral-500 dark:text-neutral-400">
                      Order: {{ award.order }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Organization -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ award.organization }}
                </div>
              </td>

              <!-- Date -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ formatDate(award.received_at) }}
                </div>
              </td>

              <!-- Credential -->
              <td class="px-6 py-4 whitespace-nowrap">
                <a
                  v-if="award.credential_url"
                  :href="award.credential_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                >
                  {{ award.credential_id || 'View' }}
                </a>
                <span v-else class="text-sm text-neutral-500 dark:text-neutral-400">
                  {{ award.credential_id || '-' }}
                </span>
              </td>

              <!-- Galleries -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ award.gallery_count || 0 }} {{ award.gallery_count === 1 ? 'photo' : 'photos' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                  <!-- Edit -->
                  <button
                    @click="router.push(`/admin/awards/${award.id}/edit`)"
                    class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                    title="Edit award"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>

                  <!-- Delete -->
                  <button
                    @click="confirmDelete(award)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete award"
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

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="awardToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="awardToDelete = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Delete Award
          </h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Are you sure you want to delete "<strong>{{ awardToDelete.title }}</strong>"? This action cannot be undone.
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton
              button-type="secondary"
              @click="awardToDelete = null"
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
              Delete Award
            </BaseButton>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAwardsStore } from '@/stores/awards'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const router = useRouter()
const awardsStore = useAwardsStore()
const uiStore = useUiStore()

const searchQuery = ref('')
const awardToDelete = ref(null)
const isDeleting = ref(false)

const awards = computed(() => awardsStore.awards)
const pagination = computed(() => awardsStore.pagination)
const loading = computed(() => awardsStore.loading)
const error = computed(() => awardsStore.error)

// Visible page numbers for pagination
const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  const pages = []

  // Show max 5 page numbers
  let start = Math.max(1, current - 2)
  let end = Math.min(last, start + 4)

  // Adjust start if we're near the end
  if (end - start < 4) {
    start = Math.max(1, end - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})

onMounted(() => {
  fetchAwards()
})

async function fetchAwards() {
  try {
    const filters = {}
    if (searchQuery.value) filters.search = searchQuery.value

    await awardsStore.fetchAwards(pagination.value.current_page, 10, filters)
  } catch (err) {
    console.error('Failed to fetch awards:', err)
  }
}

// Debounced search
let searchTimeout
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchAwards()
  }, 300)
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    awardsStore.pagination.current_page = page
    fetchAwards()
  }
}

function confirmDelete(award) {
  awardToDelete.value = award
}

async function handleDelete() {
  if (!awardToDelete.value) return

  isDeleting.value = true

  try {
    await awardsStore.deleteAward(awardToDelete.value.id)

    uiStore.showSuccess(
      `"${awardToDelete.value.title}" has been deleted successfully.`,
      'Award Deleted'
    )

    awardToDelete.value = null

    // Reload awards if current page is now empty
    if (awards.value.length === 0 && pagination.value.current_page > 1) {
      pagination.value.current_page--
    }
    await fetchAwards()
  } catch (err) {
    console.error('Failed to delete award:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to delete award. Please try again.',
      'Delete Failed',
      0 // Persistent error
    )
  } finally {
    isDeleting.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
