<template>
  <div class="max-w-5xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-12">
      <BaseCard>
        <div class="text-center">
          <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h2 class="text-xl font-bold text-neutral-900 dark:text-neutral-100 mb-2">
            Failed to Load Award
          </h2>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            {{ error }}
          </p>
          <BaseButton @click="router.push('/admin/awards')">
            Back to Awards
          </BaseButton>
        </div>
      </BaseCard>
    </div>

    <!-- Edit Form -->
    <div v-else-if="award">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
          <router-link
            to="/admin/awards"
            class="p-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
            aria-label="Back to awards"
          >
            <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </router-link>
          <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
            Edit Award
          </h1>
        </div>
        <p class="text-neutral-600 dark:text-neutral-400 ml-14">
          Update award details for "{{ award.title }}"
        </p>
      </div>

      <!-- Form -->
      <BaseCard class="mb-6">
        <AwardForm
          :award="award"
          submit-label="Update Award"
          :is-submitting="isSubmitting"
          @submit="handleSubmit"
          @cancel="handleCancel"
        />
      </BaseCard>

      <!-- Gallery Management -->
      <BaseCard>
        <GalleryManager
          :award-id="award.id"
          @updated="handleGalleryUpdate"
        />
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAwardsStore } from '@/stores/awards'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import AwardForm from '@/components/awards/AwardForm.vue'
import GalleryManager from '@/components/awards/GalleryManager.vue'

const route = useRoute()
const router = useRouter()
const awardsStore = useAwardsStore()
const uiStore = useUiStore()

const award = ref(null)
const loading = ref(true)
const error = ref(null)
const isSubmitting = ref(false)

onMounted(async () => {
  await fetchAward()
})

async function fetchAward() {
  loading.value = true
  error.value = null

  try {
    const awardId = route.params.id
    const fetchedAward = await awardsStore.fetchAward(awardId)

    // Handle both nested and flat response structures
    if (fetchedAward && fetchedAward.title) {
      award.value = fetchedAward
    } else {
      throw new Error('Invalid award data received')
    }
  } catch (err) {
    console.error('Failed to fetch award:', err)
    error.value = err.response?.data?.message || 'Failed to load award. Please try again.'
  } finally {
    loading.value = false
  }
}

async function handleSubmit(awardData) {
  isSubmitting.value = true

  try {
    const awardId = route.params.id
    const updatedAward = await awardsStore.updateAward(awardId, awardData)

    uiStore.showSuccess(
      `"${updatedAward.title}" has been updated successfully.`,
      'Award Updated'
    )

    router.push('/admin/awards')
  } catch (err) {
    console.error('Failed to update award:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to update award. Please try again.',
      'Update Failed',
      0 // Persistent
    )
  } finally {
    isSubmitting.value = false
  }
}

function handleCancel() {
  router.push('/admin/awards')
}

function handleGalleryUpdate() {
  // Refresh award data to update gallery count
  fetchAward()
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
