<template>
  <div>
    <BaseLoader v-if="isLoading" text="Loading project..." class="min-h-screen" />

    <div v-else-if="error" class="min-h-screen flex items-center justify-center">
      <BaseCard class="max-w-md text-center">
        <h2 class="text-2xl font-bold mb-4">Project Not Found</h2>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          The project you're looking for doesn't exist or has been removed.
        </p>
        <BaseButton variant="primary" @click="$router.push('/projects')">
          Back to Projects
        </BaseButton>
      </BaseCard>
    </div>

    <div v-else-if="project">
      <!-- Hero Section -->
      <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
        <div class="container-custom relative py-20">
          <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-2 mb-4">
              <BaseButton variant="outline" size="sm" @click="$router.push('/projects')">
                ← Back
              </BaseButton>
              <BaseBadge variant="success" size="sm">{{ project.status }}</BaseBadge>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6">
              {{ project.title }}
            </h1>
            <p class="text-xl text-neutral-600 dark:text-neutral-300 mb-6">
              {{ project.description }}
            </p>
            <div class="flex flex-wrap gap-2">
              <BaseBadge v-for="tech in project.technologies" :key="tech" variant="primary">
                {{ tech }}
              </BaseBadge>
            </div>
          </div>
        </div>
      </section>

      <!-- Project Image -->
      <section class="section bg-neutral-50 dark:bg-neutral-900">
        <div class="container-custom">
          <div class="max-w-5xl mx-auto">
            <div class="aspect-video bg-neutral-200 dark:bg-neutral-700 rounded-2xl"></div>
          </div>
        </div>
      </section>

      <!-- Project Details -->
      <section class="section bg-white dark:bg-neutral-800">
        <div class="container-custom">
          <div class="max-w-4xl mx-auto grid md:grid-cols-3 gap-12">
            <!-- Main Content -->
            <div class="md:col-span-2">
              <h2 class="text-3xl font-display font-bold mb-6">About the Project</h2>
              <div class="prose dark:prose-invert max-w-none">
                <p class="text-neutral-600 dark:text-neutral-400 mb-4">
                  {{ project.content || 'This project showcases innovative solutions and modern development practices. The implementation focuses on user experience, performance optimization, and scalable architecture.' }}
                </p>
                <h3 class="text-2xl font-semibold mb-4 mt-8">Key Features</h3>
                <ul class="space-y-2 text-neutral-600 dark:text-neutral-400">
                  <li>Responsive design for all devices</li>
                  <li>Modern UI/UX implementation</li>
                  <li>Performance optimized</li>
                  <li>SEO friendly architecture</li>
                  <li>Secure and scalable backend</li>
                </ul>
                <h3 class="text-2xl font-semibold mb-4 mt-8">Challenges & Solutions</h3>
                <p class="text-neutral-600 dark:text-neutral-400">
                  Every project comes with its unique challenges. This project involved solving complex technical problems
                  while maintaining code quality and user experience standards.
                </p>
              </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
              <BaseCard>
                <h3 class="text-lg font-semibold mb-4">Project Info</h3>
                <div class="space-y-3 text-sm">
                  <div>
                    <span class="text-neutral-500 dark:text-neutral-400">Status</span>
                    <p class="font-medium">{{ project.status }}</p>
                  </div>
                  <div>
                    <span class="text-neutral-500 dark:text-neutral-400">Date</span>
                    <p class="font-medium">{{ formatDate(project.created_at) }}</p>
                  </div>
                  <div v-if="project.client">
                    <span class="text-neutral-500 dark:text-neutral-400">Client</span>
                    <p class="font-medium">{{ project.client }}</p>
                  </div>
                  <div v-if="project.url">
                    <span class="text-neutral-500 dark:text-neutral-400">Website</span>
                    <a :href="project.url" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:text-primary-700">
                      Visit Site →
                    </a>
                  </div>
                  <div v-if="project.github_url">
                    <span class="text-neutral-500 dark:text-neutral-400">Source Code</span>
                    <a :href="project.github_url" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:text-primary-700">
                      View on GitHub →
                    </a>
                  </div>
                </div>
              </BaseCard>

              <BaseCard>
                <h3 class="text-lg font-semibold mb-4">Technologies</h3>
                <div class="flex flex-wrap gap-2">
                  <BaseBadge v-for="tech in project.technologies" :key="tech" variant="outline" size="sm">
                    {{ tech }}
                  </BaseBadge>
                </div>
              </BaseCard>
            </div>
          </div>
        </div>
      </section>

      <!-- Project CTA Section -->
      <ProjectCTA :project="project" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjects } from '@/composables/useProjects'
import { BaseButton, BaseCard, BaseBadge, BaseLoader } from '@/components/base'
import ProjectCTA from '@/components/projects/ProjectCTA.vue'

const route = useRoute()
const router = useRouter()
const { project, isLoading, error, fetchProject } = useProjects()

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

onMounted(async () => {
  const slug = route.params.slug
  await fetchProject(slug)

  if (error.value) {
    console.error('Failed to load project:', error.value)
  }
})
</script>
