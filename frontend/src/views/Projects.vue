<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="container-custom relative py-18">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            My <span class="text-gradient">Projects</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            {{ totalProjectsCount }} {{ totalProjectsCount === 1 ? 'project' : 'projects' }} showcasing innovation and excellence
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
            @click="changeFilter(filter.value)"
          >
            {{ filter.label }}
          </BaseButton>
        </div>

        <BaseLoader v-if="isLoading" text="Loading projects..." />

        <div v-else-if="paginatedProjects.length === 0" class="text-center py-12">
          <p class="text-neutral-600 dark:text-neutral-400">No projects found.</p>
        </div>

        <div v-else>
          <!-- Projects Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <BaseCard
              v-for="project in paginatedProjects"
              :key="project.id"
              hover
              class="cursor-pointer"
              @click="$router.push(`/projects/${project.slug}`)"
            >
              <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-lg mb-4 overflow-hidden">
                <img
                  v-if="project.image"
                  :src="project.image"
                  :alt="project.title"
                  class="w-full h-full object-cover"
                />
              </div>
              <div class="flex items-center gap-2 mb-3">
                <BaseBadge v-if="project.domain" variant="accent" size="sm">{{ project.domain }}</BaseBadge>
                <BaseBadge variant="success" size="sm">{{ project.status }}</BaseBadge>
                <span class="text-sm text-neutral-500">{{ formatDate(project.created_at) }}</span>
              </div>
              <h3 class="text-xl font-semibold mb-2">{{ project.title }}</h3>

              <!-- Impact Statement (added Nov 3, 2025) -->
              <p v-if="project.impact_statement" class="text-primary-600 dark:text-primary-400 text-sm font-semibold mb-2 line-clamp-2">
                {{ project.impact_statement }}
              </p>

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
          <div v-if="totalPages > 1" class="flex flex-wrap justify-center items-center gap-2 mt-12">
            <BaseButton
              variant="outline"
              size="sm"
              :disabled="currentPage === 1"
              @click="goToPage(currentPage - 1)"
            >
              Previous
            </BaseButton>
            
            <!-- Show all pages if totalPages <= 10 -->
            <template v-if="totalPages <= 10">
              <BaseButton
                v-for="page in totalPages"
                :key="page"
                :variant="currentPage === page ? 'primary' : 'outline'"
                size="sm"
                @click="goToPage(page)"
              >
                {{ page }}
              </BaseButton>
            </template>

            <!-- Smart pagination for > 10 pages -->
            <template v-else>
              <!-- First page -->
              <BaseButton
                :variant="currentPage === 1 ? 'primary' : 'outline'"
                size="sm"
                @click="goToPage(1)"
              >
                1
              </BaseButton>

              <!-- Dots before current range -->
              <span v-if="currentPage > 3" class="px-2 text-neutral-500">...</span>

              <!-- Pages around current -->
              <BaseButton
                v-for="page in visiblePages"
                :key="page"
                :variant="currentPage === page ? 'primary' : 'outline'"
                size="sm"
                @click="goToPage(page)"
              >
                {{ page }}
              </BaseButton>

              <!-- Dots after current range -->
              <span v-if="currentPage < totalPages - 2" class="px-2 text-neutral-500">...</span>

              <!-- Last page -->
              <BaseButton
                :variant="currentPage === totalPages ? 'primary' : 'outline'"
                size="sm"
                @click="goToPage(totalPages)"
              >
                {{ totalPages }}
              </BaseButton>
            </template>

            <BaseButton
              variant="outline"
              size="sm"
              :disabled="currentPage === totalPages"
              @click="goToPage(currentPage + 1)"
            >
              Next
            </BaseButton>
          </div>

          <!-- Results Info -->
          <div class="text-center mt-6 text-sm text-neutral-600 dark:text-neutral-400">
            Showing {{ startIndex + 1 }}-{{ endIndex }} of {{ totalProjectsCount }} projects
          </div>
        </div>
      </div>
    </section>
    <div class="h-10"></div>
    <!-- CTA Section (Component) -->
    <CTASection
      v-if="showCTASection"
      heading="Let's Build Something Amazing"
      description="Have a project in mind? Let's discuss how I can help turn your ideas into reality with <strong>innovative solutions & cutting-edge technology</strong>."
      whatsapp-message="Hi! I'm interested in working together on a project. Can we discuss?"
      :social-links="aboutSettings?.social_links"
      :show-social-links="true"
    />    
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useProjects } from '@/composables/useProjects'
import { usePageSections } from '@/composables/usePageSections'
import { useAboutSettings } from '@/composables/useAboutSettings'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'
import CTASection from '@/components/CTASection.vue'
import { useRouter } from 'vue-router'

const { projects, isLoading, fetchProjects } = useProjects()
const { sections, fetchActiveSections } = usePageSections()
const { aboutSettings } = useAboutSettings()
const router = useRouter()

const activeFilter = ref('all')
const currentPage = ref(1)
const perPage = 9 // Show 9 projects per page

// CTA visibility (controlled by page sections)
const showCTASection = computed(() => {
  const section = sections.value.find(s => s.section_type === 'cta')
  return section ? section.is_active : false
})

// Dynamic domain filters based on available domains in projects
const availableDomains = computed(() => {
  const domains = new Set()
  projects.value.forEach(project => {
    if (project.domain) {
      domains.add(project.domain)
    }
  })
  return Array.from(domains).sort()
})

// Build filters dynamically
const filters = computed(() => {
  const baseFilters = [
    { label: 'All Projects', value: 'all' },
    { label: 'Featured', value: 'featured' }
  ]

  // Add domain filters
  const domainFilters = availableDomains.value.map(domain => ({
    label: domain,
    value: domain
  }))

  return [...baseFilters, ...domainFilters]
})

// Filtered projects based on active filter
const filteredProjects = computed(() => {
  if (activeFilter.value === 'all') {
    return projects.value
  }
  if (activeFilter.value === 'featured') {
    return projects.value.filter(p => p.is_featured)
  }
  // Filter by domain
  return projects.value.filter(p => p.domain === activeFilter.value)
})

// Total filtered projects count
const totalProjectsCount = computed(() => filteredProjects.value.length)

// Total pages
const totalPages = computed(() => {
  return Math.ceil(filteredProjects.value.length / perPage)
})

// Paginated projects (slice the array)
const paginatedProjects = computed(() => {
  const start = (currentPage.value - 1) * perPage
  const end = start + perPage
  return filteredProjects.value.slice(start, end)
})

// Start and end index for display
const startIndex = computed(() => (currentPage.value - 1) * perPage)
const endIndex = computed(() => {
  const end = currentPage.value * perPage
  return end > totalProjectsCount.value ? totalProjectsCount.value : end
})

// Visible page numbers (smart pagination)
const visiblePages = computed(() => {
  const pages = []
  const current = currentPage.value
  const total = totalPages.value

  // Show pages around current page
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (i > 1 && i < total) {
      pages.push(i)
    }
  }

  return pages
})

// Go to specific page
const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    // Scroll to top smoothly
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

// Change filter
const changeFilter = (filterValue) => {
  activeFilter.value = filterValue
  currentPage.value = 1 // Reset to first page
}

// Format date
const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short'
  })
}

// Watch filter changes
watch(activeFilter, () => {
  currentPage.value = 1
})

// Fetch projects and page sections on mount
onMounted(async () => {
  await fetchActiveSections('projects') // Fetch page sections for Projects Page
  await fetchProjects()
})
</script>
