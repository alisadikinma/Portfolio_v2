<template>
  <div class="min-h-screen bg-white dark:bg-neutral-900">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-4">Project Not Found</h1>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">Sorry, we couldn't find the project you're looking for.</p>
        <router-link
          to="/projects"
          class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
        >
          ← Back to Projects
        </router-link>
      </div>
    </div>

    <!-- Project Content -->
    <div v-else-if="project" class="space-y-12">
      <!-- Hero Section -->
      <div class="relative h-96 bg-gradient-to-br from-primary-600 to-primary-800 overflow-hidden">
        <!-- Background Image -->
        <img
          v-if="project.featured_image"
          :src="project.featured_image"
          :alt="project.title"
          class="absolute inset-0 w-full h-full object-cover opacity-50"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-neutral-900/80 to-transparent"></div>

        <!-- Content -->
        <div class="relative h-full flex flex-col justify-end p-6 md:p-12">
          <div class="max-w-4xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">{{ project.title }}</h1>

            <!-- Meta Info -->
            <div class="flex flex-wrap gap-4 items-center text-neutral-200">
              <div v-if="project.status" class="inline-flex items-center gap-2">
                <span class="text-sm font-medium capitalize px-3 py-1 bg-primary-500/30 rounded-full">
                  {{ project.status.replace('_', ' ') }}
                </span>
              </div>

              <div v-if="project.start_date" class="text-sm">
                📅 {{ formatDate(project.start_date) }}
                <span v-if="project.end_date"> - {{ formatDate(project.end_date) }}</span>
              </div>

              <div v-if="project.client_name" class="text-sm">
                👤 {{ project.client_name }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="max-w-4xl mx-auto px-6 md:px-0 space-y-12">
        <!-- Description -->
        <div v-if="project.description" class="space-y-4 text-neutral-700 dark:text-neutral-300">
          <div 
            v-html="project.description"
            class="prose prose-neutral dark:prose-invert max-w-none [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-neutral-900 [&_h2]:dark:text-neutral-100 [&_h2]:mt-8 [&_h2]:mb-4 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-neutral-900 [&_h3]:dark:text-neutral-100 [&_h3]:mt-6 [&_h3]:mb-3 [&_p]:mb-4 [&_p]:leading-relaxed [&_a]:text-primary-600 [&_a]:dark:text-primary-400 [&_a]:hover:text-primary-700 [&_a]:dark:hover:text-primary-300 [&_a]:font-medium [&_img]:rounded-lg [&_img]:my-6 [&_img]:max-w-full [&_img]:h-auto [&_code]:px-2 [&_code]:py-1 [&_code]:bg-neutral-100 [&_code]:dark:bg-neutral-800 [&_code]:rounded [&_code]:font-mono [&_code]:text-sm [&_pre]:p-4 [&_pre]:bg-neutral-900 [&_pre]:dark:bg-neutral-950 [&_pre]:text-neutral-100 [&_pre]:rounded-lg [&_pre]:overflow-x-auto [&_pre]:mb-4 [&_li]:mb-2 [&_li]:ml-6"
          ></div>
        </div>

        <!-- Technologies -->
        <div v-if="project.technologies && project.technologies.length > 0" class="space-y-4">
          <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Technologies Used</h2>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="tech in project.technologies"
              :key="tech"
              class="px-4 py-2 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm font-medium"
            >
              {{ tech }}
            </span>
          </div>
        </div>

        <!-- Project Links -->
        <div class="space-y-4">
          <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Project Links</h2>
          <div class="flex flex-wrap gap-4">
            <a
              v-if="project.project_url"
              :href="project.project_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
            >
              🔗 Visit Project
            </a>

            <a
              v-if="project.github_url"
              :href="project.github_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-2 px-6 py-2 bg-neutral-800 hover:bg-neutral-700 dark:bg-neutral-700 dark:hover:bg-neutral-600 text-white rounded-lg font-medium transition-colors"
            >
              🐙 View on GitHub
            </a>
          </div>
        </div>

        <!-- Divider -->
        <hr class="border-neutral-200 dark:border-neutral-800" />

        <!-- CTA Section -->
        <div v-if="project.cta && project.cta.has_cta" class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl p-8 md:p-12 text-white space-y-4">
          <h2 class="text-3xl font-bold">{{ project.cta.title }}</h2>
          <p class="text-lg opacity-90 max-w-2xl">{{ project.cta.description }}</p>

          <div class="flex flex-col sm:flex-row gap-4 pt-6">
            <a
              v-if="project.cta.phone_number && project.cta.phone_number.includes('@')"
              :href="`mailto:${project.cta.phone_number}`"
              class="inline-flex items-center justify-center gap-2 px-8 py-3 bg-white text-primary-600 hover:bg-neutral-100 rounded-lg font-bold transition-colors"
            >
              ✉️ {{ project.cta.button_text || 'Get in Touch' }}
            </a>

            <a
              v-else-if="project.cta.phone_number"
              :href="`tel:${project.cta.phone_number}`"
              class="inline-flex items-center justify-center gap-2 px-8 py-3 bg-white text-primary-600 hover:bg-neutral-100 rounded-lg font-bold transition-colors"
            >
              📞 {{ project.cta.button_text || 'Call Now' }}
            </a>

            <router-link
              to="/contact"
              class="inline-flex items-center justify-center gap-2 px-8 py-3 bg-primary-800 hover:bg-primary-900 text-white rounded-lg font-bold transition-colors border border-white/20"
            >
              💬 {{ project.cta.button_text || 'Contact Us' }}
            </router-link>
          </div>
        </div>

        <!-- Related Projects Section -->
        <div v-if="project.related_projects && project.related_projects.length > 0" class="space-y-6">
          <h2 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">Related Projects</h2>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
              v-for="relatedProject in project.related_projects"
              :key="relatedProject.id"
              class="group cursor-pointer"
              @click="navigateToProject(relatedProject.slug)"
            >
              <!-- Image -->
              <div class="relative h-48 bg-neutral-200 dark:bg-neutral-800 rounded-lg overflow-hidden mb-4">
                <img
                  v-if="relatedProject.featured_image"
                  :src="relatedProject.featured_image"
                  :alt="relatedProject.title"
                  class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                />
                <div v-else class="w-full h-full flex items-center justify-center text-neutral-400">
                  📷 No Image
                </div>
              </div>

              <!-- Content -->
              <div class="space-y-2">
                <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                  {{ relatedProject.title }}
                </h3>
                <p class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-2">
                  {{ relatedProject.description }}
                </p>
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400 group-hover:underline">
                  View Project →
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex items-center justify-between pt-8 border-t border-neutral-200 dark:border-neutral-800">
          <router-link
            to="/projects"
            class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium transition-colors"
          >
            ← Back to Projects
          </router-link>

          <router-link
            to="/contact"
            class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
          >
            Get in Touch →
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjects } from '@/stores/projects'

const route = useRoute()
const router = useRouter()
const projectsStore = useProjects()

// Data
const project = ref(null)
const loading = ref(true)
const error = ref(false)

// Helper to format date
function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

// Navigate to related project
function navigateToProject(slug) {
  router.push(`/projects/${slug}`)
}

// Fetch project data
const fetchProject = async () => {
  try {
    loading.value = true
    error.value = false

    // Get slug from route params
    const slug = route.params.slug

    if (!slug) {
      error.value = true
      return
    }

    // Use projects store to fetch
    await projectsStore.fetchProject(slug)

    if (projectsStore.currentProject) {
      project.value = projectsStore.currentProject
    } else {
      error.value = true
    }
  } catch (err) {
    console.error('Failed to fetch project:', err)
    error.value = true
  } finally {
    loading.value = false
  }
}

// Fetch on mount
onMounted(() => {
  fetchProject()
})
</script>

<style scoped>
/* Prose styling handled via inline Tailwind classes in template */
</style>
