<template>
  <div class="max-w-5xl mx-auto">
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
          Create New Award
        </h1>
      </div>
      <p class="text-neutral-600 dark:text-neutral-400 ml-14">
        Add a new award to your achievements
      </p>
    </div>

    <!-- Form -->
    <BaseCard>
      <AwardForm
        submit-label="Create Award"
        :is-submitting="isSubmitting"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </BaseCard>

    <!-- Gallery Management Info -->
    <BaseCard>
      <div class="flex items-start gap-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
            Gallery Management
          </h3>
          <p class="text-sm text-blue-800 dark:text-blue-300">
            After creating this award, you'll be able to link photo galleries to showcase your achievement.
          </p>
        </div>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAwardsStore } from '@/stores/awards'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import AwardForm from '@/components/awards/AwardForm.vue'

const router = useRouter()
const awardsStore = useAwardsStore()
const uiStore = useUiStore()

const isSubmitting = ref(false)

async function handleSubmit(awardData) {
  isSubmitting.value = true

  try {
    const createdAward = await awardsStore.createAward(awardData)

    uiStore.showSuccess(
      `"${createdAward.title}" has been created successfully.`,
      'Award Created'
    )

    router.push('/admin/awards')
  } catch (error) {
    console.error('Failed to create award:', error)

    uiStore.showError(
      error.response?.data?.message || 'Failed to create award. Please try again.',
      'Creation Failed',
      0 // Persistent
    )
  } finally {
    isSubmitting.value = false
  }
}

function handleCancel() {
  router.push('/admin/awards')
}
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
