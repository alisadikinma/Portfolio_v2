<template>
  <div class="min-h-screen bg-gray-50 p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Carousel Drafts</h1>
        <p class="mt-2 text-gray-600">Review and approve carousel content</p>
      </div>

      <!-- Filters -->
      <div class="mb-6 flex gap-4">
        <select
          v-model="statusFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="scheduled">Scheduled</option>
          <option value="posted">Posted</option>
          <option value="failed">Failed</option>
        </select>

        <select
          v-model="platformFilter"
          class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Platforms</option>
          <option value="instagram">Instagram</option>
          <option value="tiktok">TikTok</option>
          <option value="linkedin">LinkedIn</option>
        </select>

        <button
          @click="applyFilters"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          Apply Filters
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="store.loading" class="text-center py-12">
        <div class="inline-flex items-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span class="ml-3 text-gray-600">Loading carousels...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="store.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-600">Error: {{ store.error }}</p>
      </div>

      <!-- Drafts Table -->
      <div v-if="!store.loading && store.drafts.length > 0" class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Platform</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="draft in store.drafts" :key="draft.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ draft.title }}</td>
              <td class="px-6 py-4 text-sm">
                <span :class="getStatusBadgeClass(draft.status)" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ draft.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ draft.target_platform }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ formatDate(draft.created_at) }}</td>
              <td class="px-6 py-4 text-sm">
                <router-link :to="`/admin/carousel-drafts/${draft.id}`" class="text-blue-600 hover:text-blue-800">
                  View Details
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div v-if="!store.loading && store.drafts.length === 0" class="text-center py-12">
        <p class="text-gray-600">No carousel drafts found</p>
      </div>

      <!-- Pagination -->
      <div v-if="store.pagination.last_page > 1" class="mt-6 flex justify-center gap-2">
        <button
          v-for="page in store.pagination.last_page"
          :key="page"
          @click="goToPage(page)"
          :class="page === currentPage ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border'"
          class="px-3 py-1 rounded"
        >
          {{ page }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useCarouselDraftsStore } from '@/stores/carouselDrafts'

const store = useCarouselDraftsStore()
const statusFilter = ref('')
const platformFilter = ref('')
const currentPage = ref(1)

const getStatusBadgeClass = (status) => {
  const classes = {
    scraping: 'bg-yellow-100 text-yellow-800',
    analyzing: 'bg-yellow-100 text-yellow-800',
    generating: 'bg-blue-100 text-blue-800',
    draft: 'bg-gray-100 text-gray-800',
    approved: 'bg-green-100 text-green-800',
    scheduled: 'bg-purple-100 text-purple-800',
    posted: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const applyFilters = () => {
  currentPage.value = 1
  store.fetchDrafts({
    status: statusFilter.value,
    target_platform: platformFilter.value,
    page: currentPage.value,
  })
}

const goToPage = (page) => {
  currentPage.value = page
  store.fetchDrafts({
    status: statusFilter.value,
    target_platform: platformFilter.value,
    page,
  })
}

onMounted(() => {
  store.fetchDrafts()
})
</script>
