<template>
  <div class="min-h-screen bg-gray-50 p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Back Button -->
      <router-link to="/admin/carousel-drafts" class="text-blue-600 hover:text-blue-800 mb-6">
        ← Back to Drafts
      </router-link>

      <!-- Loading State -->
      <div v-if="store.loading" class="text-center py-12">
        <div class="inline-flex items-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span class="ml-3 text-gray-600">Loading draft...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="store.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-600">Error: {{ store.error }}</p>
      </div>

      <!-- Draft Detail -->
      <div v-if="!store.loading && store.selectedDraft" class="space-y-6">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg p-6">
          <h1 class="text-3xl font-bold text-gray-900">{{ store.selectedDraft.title }}</h1>
          <p class="mt-2 text-gray-600">{{ store.selectedDraft.creative_direction }}</p>
          <div class="mt-4 flex gap-4">
            <div>
              <span class="text-sm text-gray-500">Status</span>
              <p :class="getStatusBadgeClass(store.selectedDraft.status)" class="px-3 py-1 rounded-full text-sm font-semibold w-fit">
                {{ store.selectedDraft.status }}
              </p>
            </div>
            <div>
              <span class="text-sm text-gray-500">Platform</span>
              <p class="text-gray-900 font-semibold">{{ store.selectedDraft.target_platform }}</p>
            </div>
          </div>
        </div>

        <!-- Rejection Reason -->
        <div v-if="store.selectedDraft.rejection_reason" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <h3 class="font-semibold text-yellow-900">Rejection Reason</h3>
          <p class="text-yellow-800">{{ store.selectedDraft.rejection_reason }}</p>
        </div>

        <!-- Slides Grid -->
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Carousel Slides</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="slide in store.selectedDraft.slides" :key="slide.id" class="border border-gray-200 rounded-lg overflow-hidden">
              <!-- Image -->
              <div v-if="slide.generated_image_url" class="relative w-full h-48 bg-gray-100">
                <img :src="slide.generated_image_url" :alt="`Slide ${slide.slide_index}`" class="w-full h-full object-cover">
                <div class="absolute top-2 left-2">
                  <span :class="getSlideStatusBadgeClass(slide.generation_status)" class="px-2 py-1 text-xs rounded-full font-semibold">
                    {{ slide.generation_status }}
                  </span>
                </div>
              </div>

              <!-- Slide Info -->
              <div class="p-4">
                <p class="text-sm font-semibold text-gray-900">Slide {{ slide.slide_index }}</p>
                <p class="text-xs text-gray-600">{{ slide.slide_type }}</p>
                <div v-if="slide.wow_score" class="mt-2 p-2 bg-gray-50 rounded">
                  <p class="text-xs text-gray-600"><strong>WOW Score:</strong></p>
                  <p class="text-sm font-semibold text-blue-600">{{ slide.wow_score.overall || 'N/A' }}/100</p>
                </div>
                <p v-if="slide.error_message" class="text-xs text-red-600 mt-2">Error: {{ slide.error_message }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="store.selectedDraft.status === 'draft'" class="bg-white shadow rounded-lg p-6 flex gap-4">
          <button
            @click="approve"
            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Approve
          </button>
          <button
            @click="showRejectModal = true"
            class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
          >
            Reject
          </button>
        </div>

        <div v-if="store.selectedDraft.status === 'approved'" class="bg-white shadow rounded-lg p-6 flex gap-4">
          <button
            @click="showScheduleModal = true"
            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
          >
            Schedule for Posting
          </button>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Reject Draft</h2>
            <textarea
              v-model="rejectionReason"
              placeholder="Reason for rejection..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
              rows="4"
            ></textarea>
            <div class="mt-4 flex gap-3">
              <button
                @click="doReject"
                class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
              >
                Reject
              </button>
              <button
                @click="showRejectModal = false"
                class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>

        <!-- Schedule Modal -->
        <div v-if="showScheduleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Schedule Posting</h2>
            <input
              v-model="scheduledDate"
              type="datetime-local"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
            <div class="mt-4 flex gap-3">
              <button
                @click="doSchedule"
                class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
              >
                Schedule
              </button>
              <button
                @click="showScheduleModal = false"
                class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCarouselDraftsStore } from '@/stores/carouselDrafts'

const route = useRoute()
const store = useCarouselDraftsStore()
const draftId = parseInt(route.params.id)

const showRejectModal = ref(false)
const showScheduleModal = ref(false)
const rejectionReason = ref('')
const scheduledDate = ref('')

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

const getSlideStatusBadgeClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const approve = async () => {
  await store.approveDraftAction(draftId)
}

const doReject = async () => {
  if (!rejectionReason.value.trim()) {
    alert('Please enter a rejection reason')
    return
  }
  await store.rejectDraftAction(draftId, rejectionReason.value)
  showRejectModal.value = false
  rejectionReason.value = ''
}

const doSchedule = async () => {
  if (!scheduledDate.value) {
    alert('Please select a date and time')
    return
  }
  await store.scheduleDraftAction(draftId, scheduledDate.value)
  showScheduleModal.value = false
  scheduledDate.value = ''
}

const pollStatus = async () => {
  const interval = setInterval(async () => {
    if (store.selectedDraft && store.pendingGenerations.length > 0) {
      await store.fetchDraft(draftId)
    } else {
      clearInterval(interval)
    }
  }, 5000)
}

onMounted(async () => {
  await store.fetchDraft(draftId)
  pollStatus()
})
</script>
