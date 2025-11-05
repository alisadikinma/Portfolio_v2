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
            <div
              v-for="project in paginatedProjects"
              :key="project.id"
              @click="$router.push(`/projects/${project.slug}`)"
              class="group relative cursor-pointer bg-white dark:bg-gray-800 rounded-xl overflow-hidden hover:shadow-2xl transition-all duration-500 hover:-translate-y-2"
            >
              <!-- Featured Badge (if featured) -->
              <div v-if="project.is_featured" class="absolute top-4 left-4 z-10 px-3 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold rounded-full shadow-lg flex items-center gap-1.5">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                FEATURED
              </div>

              <!-- Image Section -->
              <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-primary-50 to-secondary-50 dark:from-gray-900 dark:to-gray-800">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500/10 to-secondary-500/10 group-hover:opacity-0 transition-opacity duration-500"></div>
                <img
                  v-if="project.image"
                  :src="project.image"
                  :alt="project.title"
                  class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700 ease-out"
                />
                <!-- Gradient Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent opacity-60 group-hover:opacity-90 transition-opacity duration-300"></div>
                
                <!-- Hover CTA -->
                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:scale-100 scale-95">
                  <div class="px-6 py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm rounded-xl shadow-2xl flex items-center gap-2 border border-white/20">
                    <span class="text-primary-600 dark:text-primary-400 font-bold text-sm">View Details</span>
                    <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Content Section -->
              <div class="p-5 space-y-3">
                <!-- Client Name -->
                <div v-if="project.client" class="flex items-center gap-2">
                  <svg class="w-4 h-4 text-primary-600 dark:text-primary-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                  <span class="text-sm font-semibold text-primary-600 dark:text-primary-400 truncate">
                    {{ project.client }}
                  </span>
                </div>
                
                <!-- Title -->
                <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                  {{ project.title }}
                </h3>
                
                <!-- Description -->
                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed">
                  {{ project.description }}
                </p>
                
                <!-- Tech Stack -->
                <div class="flex flex-wrap gap-1.5 pt-2">
                  <span
                    v-for="tech in project.technologies?.slice(0, 3)"
                    :key="tech"
                    class="px-2.5 py-1 bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 text-primary-700 dark:text-primary-300 rounded-md text-xs font-semibold border border-primary-100 dark:border-primary-800/50 hover:border-primary-300 dark:hover:border-primary-600 transition-colors"
                  >
                    {{ tech }}
                  </span>
                  <span
                    v-if="project.technologies?.length > 3"
                    class="px-2.5 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-md text-xs font-semibold"
                  >
                    +{{ project.technologies.length - 3 }}
                  </span>
                </div>
              </div>

              <!-- Bottom Glow Effect -->
              <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
            </div>
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
import { BaseButton, BaseLoader } from '@/components/base'
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

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
