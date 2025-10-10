<template>
  <div class="min-h-screen">
    <!-- Hero Section - Clean & Professional -->
    <section class="relative pt-32 pb-20 md:pt-40 md:pb-32 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
      <!-- Subtle Background Pattern -->
      <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #6366f1 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom relative z-10">
        <div class="max-w-4xl mx-auto text-center">
          <!-- Badge -->
          <div class="inline-flex items-center gap-2 px-4 py-2 glass rounded-full mb-6 animate-fade-in-down">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-500"></span>
            </span>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              Available for Freelance Work
            </span>
          </div>

          <!-- Main Heading -->
          <h1 class="text-5xl md:text-6xl lg:text-7xl font-display font-bold text-gray-900 dark:text-white mb-6 leading-tight animate-fade-in-up">
            Creative Developer & <br />
            <span class="text-gradient">Digital Designer</span>
          </h1>

          <!-- Subtitle -->
          <p class="text-xl md:text-2xl text-gray-600 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed animate-fade-in-up animate-delay-100">
            I craft exceptional digital experiences through modern design
            and innovative solutions that drive real results.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap justify-center gap-4 mb-16 animate-fade-in-up animate-delay-200">
            <button
              @click="$router.push('/projects')"
              class="group px-8 py-4 bg-gradient-to-r from-primary-600 to-secondary-600 text-white font-semibold rounded-xl hover:shadow-xl hover:scale-105 transition-all duration-300"
            >
              <span class="flex items-center gap-2">
                View My Work
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </span>
            </button>
            <button
              @click="$router.push('/contact')"
              class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
            >
              Let's Talk
            </button>
          </div>

          <!-- Tech Stack -->
          <div class="animate-fade-in-up animate-delay-300">
            <p class="text-sm text-gray-500 dark:text-gray-500 mb-4 uppercase tracking-wider">
              Tech Stack
            </p>
            <div class="flex flex-wrap justify-center gap-3">
              <span
                v-for="tech in techStack"
                :key="tech"
                class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
              >
                {{ tech }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section - Clean Cards -->
    <section class="py-20 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div
            v-for="(stat, index) in stats"
            :key="stat.label"
            class="card-elevated p-8 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
            :class="`animate-fade-in-up animate-delay-${index * 100}`"
          >
            <div class="text-4xl md:text-5xl font-bold text-gradient mb-2">
              {{ stat.value }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400 uppercase tracking-wider">
              {{ stat.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured Projects - Modern Grid -->
    <section class="py-20 bg-gray-50 dark:bg-gray-900">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-primary-600 dark:text-primary-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Featured Work
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Selected Projects
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            Showcasing innovative solutions and creative implementations
          </p>
        </div>

        <BaseLoader v-if="projectsLoading" text="Loading projects..." />

        <!-- Projects Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div
            v-for="(project, index) in featuredProjects"
            :key="project.id"
            @click="$router.push(`/projects/${project.slug}`)"
            class="group cursor-pointer card-elevated overflow-hidden hover:shadow-2xl hover:-translate-y-2 transition-all duration-300"
          >
            <!-- Project Image -->
            <div class="relative aspect-video bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/20 dark:to-secondary-900/20 overflow-hidden">
              <img
                v-if="project.featured_image"
                :src="project.featured_image"
                :alt="project.title"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-black/0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

              <!-- View Button Overlay -->
              <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <span class="px-6 py-3 glass text-white font-semibold rounded-xl flex items-center gap-2">
                  View Project
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                  </svg>
                </span>
              </div>
            </div>

            <!-- Content -->
            <div class="p-6">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                {{ project.title }}
              </h3>
              <p class="text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                {{ project.description }}
              </p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="tech in project.technologies?.slice(0, 4)"
                  :key="tech"
                  class="px-3 py-1 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-lg text-xs font-semibold"
                >
                  {{ tech }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-12">
          <button
            @click="$router.push('/projects')"
            class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
          >
            View All Projects
          </button>
        </div>
      </div>
    </section>

    <!-- Latest Blog -->
    <section class="py-20 bg-white dark:bg-gray-950">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="max-w-2xl mb-16">
          <p class="text-secondary-600 dark:text-secondary-400 font-semibold mb-2 uppercase tracking-wider text-sm">
            Insights & Articles
          </p>
          <h2 class="text-4xl md:text-5xl font-display font-bold text-gray-900 dark:text-white mb-4">
            Latest Thoughts
          </h2>
        </div>

        <BaseLoader v-if="postsLoading" text="Loading posts..." />

        <!-- Posts Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="post in latestPosts"
            :key="post.id"
            @click="$router.push(`/blog/${post.slug}`)"
            class="group cursor-pointer card-elevated overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col"
          >
            <!-- Image -->
            <div class="relative aspect-video bg-gradient-to-br from-secondary-100 to-accent-100 dark:from-secondary-900/20 dark:to-accent-900/20">
              <img
                v-if="post.featured_image"
                :src="post.featured_image"
                :alt="post.title"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
              />
            </div>

            <!-- Content -->
            <div class="p-6 flex-1 flex flex-col">
              <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 bg-secondary-50 dark:bg-secondary-900/20 text-secondary-700 dark:text-secondary-300 rounded-lg text-xs font-semibold uppercase">
                  {{ post.category?.name }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-500">
                  {{ formatDate(post.published_at) }}
                </span>
              </div>

              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 group-hover:text-secondary-600 dark:group-hover:text-secondary-400 transition-colors line-clamp-2">
                {{ post.title }}
              </h3>

              <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-3 flex-1 mb-4">
                {{ post.excerpt }}
              </p>

              <div class="flex items-center text-secondary-600 dark:text-secondary-400 font-semibold text-sm">
                <span>Read Article</span>
                <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-12">
          <button
            @click="$router.push('/blog')"
            class="px-8 py-4 glass text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:shadow-lg transition-all duration-300"
          >
            Read All Articles
          </button>
        </div>
      </div>
    </section>

    <!-- CTA Section - Clean & Direct -->
    <section class="relative py-20 bg-gradient-to-br from-primary-600 via-secondary-600 to-accent-600 overflow-hidden">
      <!-- Subtle Pattern -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 30px 30px;"></div>
      </div>

      <div class="container-custom text-center relative z-10">
        <h2 class="text-4xl md:text-5xl font-display font-bold text-white mb-6">
          Let's Build Something Great
        </h2>
        <p class="text-xl text-white/90 mb-10 max-w-2xl mx-auto">
          Ready to start your next project? Let's create something extraordinary together.
        </p>
        <button
          @click="$router.push('/contact')"
          class="px-10 py-5 bg-white text-primary-600 font-bold text-lg rounded-xl hover:shadow-2xl hover:scale-105 transition-all duration-300"
        >
          Start a Project
        </button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useProjects } from '@/composables/useProjects'
import { usePosts } from '@/composables/usePosts'
import { BaseLoader } from '@/components/base'

const { projects: featuredProjects, isLoading: projectsLoading, fetchProjects } = useProjects()
const { posts: latestPosts, isLoading: postsLoading, fetchPosts } = usePosts()

const stats = [
  { value: '50+', label: 'Projects' },
  { value: '100+', label: 'Articles' },
  { value: '200+', label: 'Clients' },
  { value: '5+', label: 'Years' }
]

const techStack = [
  'Vue.js', 'React', 'Laravel', 'Node.js', 'TypeScript', 'TailwindCSS', 'MySQL', 'Docker'
]

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

onMounted(async () => {
  await fetchProjects({ featured: true, limit: 4 })
  await fetchPosts({ limit: 3 })
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
