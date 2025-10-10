<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            My <span class="text-gradient">Projects</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            A collection of work I'm proud to share
          </p>
        </div>
      </div>
    </section>

    <!-- Filters Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="flex flex-wrap gap-4 justify-center mb-8">
          <BaseButton
            v-for="filter in filters"
            :key="filter.value"
            :variant="activeFilter === filter.value ? 'primary' : 'outline'"
            @click="activeFilter = filter.value"
          >
            {{ filter.label }}
          </BaseButton>
        </div>

        <BaseLoader v-if="isLoading" text="Loading projects..." />

        <div v-else-if="filteredProjects.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">No projects found.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <BaseCard
            v-for="project in filteredProjects"
            :key="project.id"
            hover
            class="cursor-pointer"
            @click="$router.push(`/projects/${project.slug}`)"
          >
            <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-lg mb-4"></div>
            <div class="flex items-center gap-2 mb-3">
              <BaseBadge variant="success" size="sm">{{ project.status }}</BaseBadge>
              <span class="text-sm text-neutral-500">{{ formatDate(project.created_at) }}</span>
            </div>
            <h3 class="text-xl font-semibold mb-2">{{ project.title }}</h3>
            <p class="text-neutral-600 dark:text-neutral-400 text-sm mb-4 line-clamp-2">
              {{ project.description }}
            </p>
            <div class="flex flex-wrap gap-2">
              <BaseBadge
                v-for="tech in project.technologies?.slice(0, 3)"
                :key="tech"
                variant="primary"
                size="sm"
              >
                {{ tech }}
              </BaseBadge>
              <BaseBadge
                v-if="project.technologies?.length > 3"
                variant="outline"
                size="sm"
              >
                +{{ project.technologies.length - 3 }}
              </BaseBadge>
            </div>
          </BaseCard>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-center gap-2 mt-12">
          <BaseButton
            variant="outline"
            size="sm"
            :disabled="currentPage === 1"
            @click="currentPage--"
          >
            Previous
          </BaseButton>
          <BaseButton
            v-for="page in totalPages"
            :key="page"
            :variant="currentPage === page ? 'primary' : 'outline'"
            size="sm"
            @click="currentPage = page"
          >
            {{ page }}
          </BaseButton>
          <BaseButton
            variant="outline"
            size="sm"
            :disabled="currentPage === totalPages"
            @click="currentPage++"
          >
            Next
          </BaseButton>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useProjects } from '@/composables/useProjects'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'

const { projects, isLoading, fetchProjects } = useProjects()

const activeFilter = ref('all')
const currentPage = ref(1)
const perPage = 9

const filters = [
  { label: 'All Projects', value: 'all' },
  { label: 'Featured', value: 'featured' },
  { label: 'Web Apps', value: 'web' },
  { label: 'Mobile', value: 'mobile' },
  { label: 'Open Source', value: 'opensource' }
]

const filteredProjects = computed(() => {
  if (activeFilter.value === 'all') {
    return projects.value
  }
  if (activeFilter.value === 'featured') {
    return projects.value.filter(p => p.featured)
  }
  return projects.value.filter(p =>
    p.category?.toLowerCase() === activeFilter.value
  )
})

const totalPages = computed(() => {
  return Math.ceil(filteredProjects.value.length / perPage)
})

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short'
  })
}

watch(activeFilter, () => {
  currentPage.value = 1
})

onMounted(async () => {
  await fetchProjects()
})
</script>
