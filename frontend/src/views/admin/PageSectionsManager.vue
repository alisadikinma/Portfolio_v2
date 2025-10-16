<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePageSections } from '@/composables/usePageSections'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import DragDropList from '@/components/admin/DragDropList.vue'

const { sections, isLoading, error, fetchSections, updateSection, reorderSections } = usePageSections()
const { showSuccess, showError } = useToast()

const selectedPage = ref('homepage')

const pageTypes = [
  { value: 'homepage', label: 'Homepage' },
  { value: 'about', label: 'About Page' },
  { value: 'projects', label: 'Projects Page' },
  { value: 'blog', label: 'Blog Page' },
]

const sectionTypeLabels = {
  hero: '🎯 Hero Section',
  featured_projects: '💼 Featured Projects',
  latest_blog: '📝 Latest Blog Posts',
  testimonials: '💬 Testimonials',
  cta: '📢 Call to Action',
  about_intro: '👋 About Introduction',
  skills: '🎨 Skills Section',
  experience: '💡 Experience Timeline',
  awards: '🏆 Awards & Certifications',
  services: '⚙️ Services',
  stats: '📊 Statistics',
  team: '👥 Team Members',
  faq: '❓ FAQ Section',
  contact_form: '📬 Contact Form',
  gallery: '🖼️ Gallery',
}

const filteredSections = computed(() => {
  return sections.value.filter(section => section.page_type === selectedPage.value)
})

onMounted(async () => {
  await loadSections()
})

async function loadSections() {
  const result = await fetchSections(selectedPage.value)
  if (!result.success) {
    showError(error.value || 'Failed to load page sections')
  }
}

async function handlePageChange() {
  await loadSections()
}

async function toggleActive(section) {
  const result = await updateSection(section.id, {
    is_active: !section.is_active,
    sequence: section.sequence
  })

  if (result.success) {
    showSuccess(`Section ${result.data.is_active ? 'activated' : 'deactivated'}`)
    await loadSections()
  } else {
    showError('Failed to update section status')
  }
}

async function handleReorder(reorderedItems) {
  const result = await reorderSections(reorderedItems)

  if (result.success) {
    showSuccess('Sections reordered successfully')
  } else {
    showError('Failed to reorder sections')
    await loadSections()
  }
}

function getSectionLabel(sectionType) {
  return sectionTypeLabels[sectionType] || sectionType
}

function getSectionDescription(sectionType) {
  const descriptions = {
    hero: 'Main banner with headline and call-to-action',
    featured_projects: 'Showcase your best projects',
    latest_blog: 'Display recent blog posts',
    testimonials: 'Client testimonials and reviews',
    cta: 'Call to action section for conversions',
    about_intro: 'Introduction about yourself',
    skills: 'Display your skills and expertise',
    experience: 'Work experience timeline',
    awards: 'Awards and certifications',
    services: 'Services you offer',
    stats: 'Statistics and achievements',
    team: 'Team members section',
    faq: 'Frequently asked questions',
    contact_form: 'Contact form section',
    gallery: 'Image gallery',
  }
  return descriptions[sectionType] || 'Section description'
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Page Sections
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Manage page sections visibility and order (Drag to reorder)
        </p>
      </div>
    </div>

    <!-- Page Type Selector -->
    <BaseCard class="mb-6">
      <div class="flex items-center gap-4">
        <label class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
          Select Page:
        </label>
        <div class="flex gap-2">
          <button
            v-for="page in pageTypes"
            :key="page.value"
            @click="selectedPage = page.value; handlePageChange()"
            :class="[
              'px-4 py-2 rounded-lg font-medium transition-all',
              selectedPage === page.value
                ? 'bg-primary-600 text-white shadow-md'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700'
            ]"
          >
            {{ page.label }}
          </button>
        </div>
      </div>
    </BaseCard>

    <!-- Sections List -->
    <BaseCard>
      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <p class="text-neutral-600 dark:text-neutral-400 mb-4">{{ error }}</p>
        <BaseButton @click="loadSections">Retry</BaseButton>
      </div>

      <!-- Sections List with Drag & Drop -->
      <div v-else-if="filteredSections.length > 0">
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-300">
              <strong>Tip:</strong> Drag sections to reorder them. Toggle the switch to show/hide sections. Only active sections will be displayed on the frontend.
            </div>
          </div>
        </div>

        <DragDropList
          :items="filteredSections"
          item-key="id"
          @reorder="handleReorder"
          class="space-y-3"
        >
          <div
            v-for="section in filteredSections"
            :key="section.id"
            :class="[
              'border rounded-lg p-5 transition-all',
              section.is_active
                ? 'bg-white dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700 shadow-sm'
                : 'bg-neutral-50 dark:bg-neutral-900 border-neutral-200 dark:border-neutral-800 opacity-60'
            ]"
          >
            <div class="flex items-center justify-between">
              <!-- Left: Drag Handle + Section Info -->
              <div class="flex items-center gap-4 flex-1">
                <!-- Drag Handle -->
                <div class="drag-handle cursor-move text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                  </svg>
                </div>

                <!-- Section Info -->
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                      {{ getSectionLabel(section.section_type) }}
                    </h3>
                    <span
                      :class="[
                        'px-2 py-0.5 text-xs font-medium rounded-full',
                        section.is_active
                          ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                          : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                      ]"
                    >
                      {{ section.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ getSectionDescription(section.section_type) }}
                  </p>
                  <div class="flex items-center gap-3 mt-2 text-xs text-neutral-500 dark:text-neutral-500">
                    <span class="flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                      </svg>
                      {{ section.page_type }}
                    </span>
                    <span class="flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                      </svg>
                      Sequence: {{ section.sequence }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Right: Toggle Switch -->
              <div class="flex items-center gap-3">
                <button
                  @click="toggleActive(section)"
                  :class="[
                    'relative inline-flex h-7 w-14 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                    section.is_active
                      ? 'bg-primary-600'
                      : 'bg-neutral-200 dark:bg-neutral-700'
                  ]"
                  :title="section.is_active ? 'Click to deactivate' : 'Click to activate'"
                >
                  <span
                    :class="[
                      'inline-block h-5 w-5 transform rounded-full bg-white transition-transform',
                      section.is_active ? 'translate-x-8' : 'translate-x-1'
                    ]"
                  />
                </button>
              </div>
            </div>
          </div>
        </DragDropList>

        <!-- Footer Info -->
        <div class="mt-6 p-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-800">
          <div class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <svg class="w-5 h-5 text-neutral-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <span>
              <strong>{{ filteredSections.filter(s => s.is_active).length }}</strong> of <strong>{{ filteredSections.length }}</strong> sections active for {{ pageTypes.find(p => p.value === selectedPage)?.label }}
            </span>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Sections Found
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400">
          No sections configured for this page type yet.
        </p>
      </div>
    </BaseCard>
  </div>
</template>
