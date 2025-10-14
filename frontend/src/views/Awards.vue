<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Page Header -->
    <section class="relative pt-32 pb-16 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
      <div class="container-custom">
        <div class="max-w-3xl mx-auto text-center">
          <h1 class="text-5xl md:text-6xl font-display font-bold text-gray-900 dark:text-white mb-6">
            Awards & <span class="text-gradient">Recognition</span>
          </h1>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Celebrating achievements, milestones, and industry recognition that drive excellence
          </p>
        </div>
      </div>
    </section>

    <!-- Awards Grid -->
    <section class="py-16">
      <div class="container-custom">
        <BaseLoader v-if="isLoading" text="Loading awards..." />

        <div v-else-if="awards.length > 0" class="space-y-12">
          <!-- Awards by Year -->
          <div v-for="(yearGroup, year) in groupedAwards" :key="year" class="space-y-6">
            <!-- Year Header -->
            <div class="flex items-center gap-4">
              <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ year }}
              </h2>
              <div class="flex-1 h-px bg-gradient-to-r from-primary-500/50 to-transparent"></div>
              <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ yearGroup.length }} {{ yearGroup.length === 1 ? 'Award' : 'Awards' }}
              </span>
            </div>

            <!-- Awards Grid for Year -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="award in yearGroup"
                :key="award.id"
                class="card-elevated p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
              >
                <!-- Award Icon -->
                <div class="flex items-start gap-4 mb-4">
                  <div class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-accent-500 to-secondary-500 rounded-xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">
                      {{ award.award_title }}
                    </h3>
                    <p class="text-sm text-accent-600 dark:text-accent-400 font-semibold">
                      {{ award.issuing_organization }}
                    </p>
                  </div>
                </div>

                <!-- Award Description -->
                <p v-if="award.description" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                  {{ award.description }}
                </p>

                <!-- Award Details -->
                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-500">
                  <div class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    {{ formatDate(award.award_date) }}
                  </div>
                  <div v-if="award.credential_id" class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    ID: {{ award.credential_id }}
                  </div>
                </div>

                <!-- Credential URL -->
                <div v-if="award.credential_url" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <a
                    :href="award.credential_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors"
                  >
                    View Credential
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20">
          <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">
            No Awards Yet
          </h3>
          <p class="text-gray-500 dark:text-gray-400">
            Check back later for updates on achievements and recognition.
          </p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="text-center py-20">
          <div class="w-24 h-24 mx-auto mb-6 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">
            Failed to Load Awards
          </h3>
          <p class="text-gray-500 dark:text-gray-400 mb-4">
            {{ error }}
          </p>
          <button
            @click="fetchAwards()"
            class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useAwards } from '@/composables/useAwards'
import { BaseLoader } from '@/components/base'

const { awards, isLoading, error, fetchAwards } = useAwards()

// Group awards by year
const groupedAwards = computed(() => {
  if (!awards.value || awards.value.length === 0) return {}
  
  const groups = {}
  
  awards.value.forEach(award => {
    const year = new Date(award.award_date).getFullYear()
    if (!groups[year]) {
      groups[year] = []
    }
    groups[year].push(award)
  })
  
  // Sort years in descending order (newest first)
  const sortedYears = Object.keys(groups).sort((a, b) => b - a)
  const sortedGroups = {}
  sortedYears.forEach(year => {
    sortedGroups[year] = groups[year]
  })
  
  return sortedGroups
})

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

onMounted(async () => {
  await fetchAwards()
})
</script>
