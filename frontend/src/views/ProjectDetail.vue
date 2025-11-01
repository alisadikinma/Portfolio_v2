<template>
  <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-4">Project Not Found</h1>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">{{ error }}</p>
        <router-link
          to="/projects"
          class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-medium transition-colors"
        >
          ← Back to Projects
        </router-link>
      </div>
    </div>

    <!-- Project Content -->
    <template v-else>
      <!-- Breadcrumb Navigation - ALWAYS VISIBLE -->
      <div class="bg-white dark:bg-neutral-800 border-b border-neutral-200 dark:border-neutral-700 py-4 mt-20">
        <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
          <nav class="flex items-center space-x-2 text-sm font-medium">
            <router-link 
              to="/" 
              class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              🏠 Home
            </router-link>
            <span class="text-neutral-400 dark:text-neutral-600">/</span>
            <router-link 
              to="/projects" 
              class="text-neutral-600 dark:text-neutral-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              💼 Projects
            </router-link>
            <template v-if="project">
              <span class="text-neutral-400 dark:text-neutral-600">/</span>
              <span class="text-neutral-900 dark:text-neutral-100 font-semibold truncate max-w-md">
                {{ project.title }}
              </span>
            </template>
          </nav>
        </div>
      </div>

      <!-- Main Content -->
      <div v-if="project" class="py-12">
        <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
          <div class="bg-white dark:bg-neutral-800 rounded-2xl shadow-lg p-8 md:p-12 space-y-12">
            <!-- Full Content (HTML from database) -->
            <div v-if="project.content" class="max-w-full mx-auto">
              <div 
                v-html="project.content"
                class="
                  [&_h1]:text-4xl [&_h1]:md:text-5xl [&_h1]:font-bold [&_h1]:text-neutral-900 [&_h1]:dark:text-neutral-100 [&_h1]:mt-0 [&_h1]:mb-6 [&_h1]:text-center
                  [&_h2]:text-2xl [&_h2]:md:text-3xl [&_h2]:font-bold [&_h2]:text-neutral-900 [&_h2]:dark:text-neutral-100 [&_h2]:mt-8 [&_h2]:mb-4 [&_h2]:text-center
                  [&_h3]:text-xl [&_h3]:md:text-2xl [&_h3]:font-bold [&_h3]:text-neutral-900 [&_h3]:dark:text-neutral-100 [&_h3]:mt-6 [&_h3]:mb-3
                  [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-neutral-700 [&_p]:dark:text-neutral-300 [&_p]:text-justify
                  [&_a]:text-primary-600 [&_a]:dark:text-primary-400 [&_a]:hover:text-primary-700 [&_a]:dark:hover:text-primary-300 [&_a]:font-medium [&_a]:underline
                  [&_img]:rounded-lg [&_img]:my-8 [&_img]:mx-auto [&_img]:max-w-full [&_img]:h-auto [&_img]:shadow-lg [&_img]:w-auto
                  [&_code]:px-2 [&_code]:py-1 [&_code]:bg-neutral-100 [&_code]:dark:bg-neutral-800 [&_code]:rounded [&_code]:font-mono [&_code]:text-sm
                  [&_pre]:p-4 [&_pre]:bg-neutral-900 [&_pre]:dark:bg-neutral-950 [&_pre]:text-neutral-100 [&_pre]:rounded-lg [&_pre]:overflow-x-auto [&_pre]:my-6
                  [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-6
                  [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-6
                  [&_li]:mb-2 [&_li]:text-neutral-700 [&_li]:dark:text-neutral-300
                  [&_blockquote]:border-l-4 [&_blockquote]:border-primary-600 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:my-6
                  [&_hr]:my-8 [&_hr]:border-neutral-200 [&_hr]:dark:border-neutral-800
                  [&_table]:w-full [&_table]:my-6
                  [&_th]:bg-neutral-100 [&_th]:dark:bg-neutral-800 [&_th]:px-4 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold
                  [&_td]:px-4 [&_td]:py-2 [&_td]:border-t [&_td]:border-neutral-200 [&_td]:dark:border-neutral-700
                "
              ></div>
            </div>

            <!-- Technologies -->
            <div v-if="project.technologies && project.technologies.length > 0" class="pt-8 border-t border-neutral-200 dark:border-neutral-700">
              <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-4">🛠️ Technologies Used</h2>
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
          </div>
        </div>

        <!-- Related Projects Section -->
        <div 
          v-if="project.related_projects && project.related_projects.length > 0" 
          class="mt-16"
        >
          <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-neutral-900 dark:text-neutral-100 mb-8 text-center">
              🔗 Related Projects
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="relatedProject in project.related_projects"
                :key="relatedProject.id"
                class="group cursor-pointer bg-white dark:bg-neutral-800 rounded-xl overflow-hidden shadow-md hover:shadow-2xl transition-all duration-300 border border-neutral-200 dark:border-neutral-700"
                @click="navigateToProject(relatedProject.slug)"
              >
                <!-- Image -->
                <div class="relative h-48 bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                  <img
                    v-if="relatedProject.featured_image"
                    :src="relatedProject.featured_image"
                    :alt="relatedProject.title"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                  />
                  <div 
                    v-else 
                    class="w-full h-full flex items-center justify-center text-neutral-400 text-4xl"
                  >
                    📷
                  </div>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-2">
                  <h3 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors line-clamp-2">
                    {{ relatedProject.title }}
                  </h3>
                  <p class="text-sm text-neutral-600 dark:text-neutral-400 line-clamp-2">
                    {{ relatedProject.description }}
                  </p>
                  <div class="pt-2">
                    <span class="text-sm font-medium text-primary-600 dark:text-primary-400 group-hover:underline">
                      View Project →
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CTA Section -->
        <div v-if="hasCta" class="mt-16">
          <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
            <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-2xl p-8 md:p-12 text-white shadow-2xl">
              <h2 class="text-3xl md:text-4xl font-bold mb-4">
                📢 {{ project.cta?.title || 'Need a Similar Solution?' }}
              </h2>
              <p class="text-lg md:text-xl opacity-90 max-w-3xl mb-8">
                {{ project.cta?.description || 'Get in touch to discuss how we can help with your project requirements.' }}
              </p>

              <div class="flex flex-col sm:flex-row gap-4">
                <!-- Email CTA -->
                <a
                  v-if="project.cta?.phone_number && project.cta.phone_number.includes('@')"
                  :href="`mailto:${project.cta.phone_number}`"
                  class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-primary-600 hover:bg-neutral-100 rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-xl"
                >
                  ✉️ {{ project.cta?.button_text || 'Email Us' }}
                </a>

                <!-- Phone CTA -->
                <a
                  v-else-if="project.cta?.phone_number"
                  :href="`tel:${project.cta.phone_number.replace(/\s/g, '')}`"
                  class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-primary-600 hover:bg-neutral-100 rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-xl"
                >
                  📞 {{ project.cta?.button_text || 'Call Now' }}
                </a>

                <!-- Contact Page CTA -->
                <router-link
                  to="/contact"
                  class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white text-primary-600 hover:bg-neutral-100 rounded-xl font-bold transition-all duration-300 shadow-lg hover:shadow-xl"
                >
                  💬 Contact Us
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

// State
const project = ref(null)
const loading = ref(true)
const error = ref(null)

// Computed: Always show CTA for projects
const hasCta = computed(() => {
  return !!project.value
})

// Fetch project from API
const fetchProject = async (slug) => {
  if (!slug) {
    error.value = 'No project slug provided'
    loading.value = false
    return
  }

  try {
    loading.value = true
    error.value = null
    
    const response = await api.get(`/projects/${slug}`)
    
    if (response.data && response.data.data) {
      project.value = response.data.data
    } else if (response.data && !response.data.data) {
      project.value = response.data
    } else {
      error.value = 'Project not found'
    }
  } catch (err) {
    console.error('[ProjectDetail] Error:', err)
    error.value = err.response?.data?.message || 'Failed to load project'
  } finally {
    loading.value = false
  }
}

// Navigate to related project
const navigateToProject = (slug) => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
  router.push(`/projects/${slug}`)
}

// Watch route changes
watch(
  () => route.params.slug,
  (newSlug) => {
    if (newSlug) {
      fetchProject(newSlug)
    }
  },
  { immediate: true }
)
</script>
