<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Manage Testimonials
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Total: {{ pagination.total }} testimonials
        </p>
      </div>
      <BaseButton @click="router.push('/admin/testimonials/create')">
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Testimonial
      </BaseButton>
    </div>

    <!-- Search and Filters -->
    <BaseCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Search -->
        <div class="md:col-span-2">
          <BaseInput
            v-model="searchQuery"
            type="text"
            placeholder="Search by client name, company, job title..."
            @input="debouncedSearch"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </BaseInput>
        </div>

        <!-- Rating Filter -->
        <div>
          <select
            v-model="ratingFilter"
            @change="fetchTestimonials"
            class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All Ratings</option>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
          </select>
        </div>

        <!-- Active Status Filter -->
        <div>
          <select
            v-model="statusFilter"
            @change="fetchTestimonials"
            class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
          >
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
    </BaseCard>

    <!-- Testimonials Table -->
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
        <BaseButton @click="fetchTestimonials">Retry</BaseButton>
      </div>

      <!-- Empty State -->
      <div v-else-if="testimonials.length === 0" class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Testimonials Found
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          {{ searchQuery ? 'Try adjusting your search criteria' : 'Get started by adding your first testimonial' }}
        </p>
        <BaseButton v-if="!searchQuery" @click="router.push('/admin/testimonials/create')">
          Create Testimonial
        </BaseButton>
      </div>

      <!-- Testimonials Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
          <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Client
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Company / Role
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Rating
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
            <tr v-for="testimonial in testimonials" :key="testimonial.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
              <!-- Client Info -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div v-if="testimonial.client_photo" class="flex-shrink-0 h-10 w-10">
                    <img :src="testimonial.client_photo" :alt="testimonial.client_name" class="h-10 w-10 rounded-full object-cover">
                  </div>
                  <div :class="testimonial.client_photo ? 'ml-4' : ''">
                    <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                      {{ testimonial.client_name }}
                    </div>
                    <div v-if="testimonial.sort_order !== null" class="text-sm text-neutral-500 dark:text-neutral-400">
                      Order: {{ testimonial.sort_order }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Company / Role -->
              <td class="px-6 py-4">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ testimonial.company_name || '-' }}
                </div>
                <div v-if="testimonial.job_title" class="text-sm text-neutral-500 dark:text-neutral-400">
                  {{ testimonial.job_title }}
                </div>
              </td>

              <!-- Rating -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <svg
                    v-for="star in 5"
                    :key="star"
                    class="w-5 h-5"
                    :class="star <= testimonial.star_rating ? 'text-yellow-400' : 'text-neutral-300 dark:text-neutral-600'"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                  <span class="ml-2 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ testimonial.star_rating }}/5
                  </span>
                </div>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                    testimonial.is_active
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-400'
                  ]"
                >
                  {{ testimonial.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                  <!-- Edit -->
                  <button
                    @click="router.push(`/admin/testimonials/${testimonial.id}/edit`)"
                    class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                    title="Edit testimonial"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>

                  <!-- Delete -->
                  <button
                    @click="confirmDelete(testimonial)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete testimonial"
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
        v-if="testimonialToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="testimonialToDelete = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Delete Testimonial
          </h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Are you sure you want to delete the testimonial from "<strong>{{ testimonialToDelete.client_name }}</strong>"? This action cannot be undone.
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton
              button-type="secondary"
              @click="testimonialToDelete = null"
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
              Delete Testimonial
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
import { useTestimonialsStore } from '@/stores/testimonials'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const router = useRouter()
const testimonialsStore = useTestimonialsStore()
const uiStore = useUiStore()

const searchQuery = ref('')
const ratingFilter = ref('')
const statusFilter = ref('')
const testimonialToDelete = ref(null)
const isDeleting = ref(false)

const testimonials = computed(() => testimonialsStore.testimonials)
const pagination = computed(() => testimonialsStore.pagination)
const loading = computed(() => testimonialsStore.loading)
const error = computed(() => testimonialsStore.error)

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
  fetchTestimonials()
})

async function fetchTestimonials() {
  try {
    const filters = {}
    if (searchQuery.value) filters.search = searchQuery.value
    if (ratingFilter.value) filters.rating = ratingFilter.value
    if (statusFilter.value) filters.is_active = statusFilter.value

    await testimonialsStore.fetchTestimonials(pagination.value.current_page, 10, filters)
  } catch (err) {
    console.error('Failed to fetch testimonials:', err)
  }
}

// Debounced search
let searchTimeout
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchTestimonials()
  }, 300)
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page) {
    testimonialsStore.pagination.current_page = page
    fetchTestimonials()
  }
}

function confirmDelete(testimonial) {
  testimonialToDelete.value = testimonial
}

async function handleDelete() {
  if (!testimonialToDelete.value) return

  isDeleting.value = true

  try {
    await testimonialsStore.deleteTestimonial(testimonialToDelete.value.id)

    uiStore.showSuccess(
      `Testimonial from "${testimonialToDelete.value.client_name}" has been deleted successfully.`,
      'Testimonial Deleted'
    )

    testimonialToDelete.value = null

    // Reload testimonials if current page is now empty
    if (testimonials.value.length === 0 && pagination.value.current_page > 1) {
      pagination.value.current_page--
    }
    await fetchTestimonials()
  } catch (err) {
    console.error('Failed to delete testimonial:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to delete testimonial. Please try again.',
      'Delete Failed',
      0 // Persistent error
    )
  } finally {
    isDeleting.value = false
  }
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
