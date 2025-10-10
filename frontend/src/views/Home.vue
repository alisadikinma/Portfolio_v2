<template>
  <div class="overflow-hidden">
    <!-- Hero Section - Bold & Eye-Catching -->
    <section class="relative min-h-screen flex items-center bg-black text-white overflow-hidden">
      <!-- Animated Grid Background -->
      <div class="absolute inset-0 opacity-20">
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(99, 102, 241, 0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, 0.3) 1px, transparent 1px); background-size: 50px 50px;"></div>
      </div>

      <!-- Glowing Orbs -->
      <div class="absolute top-20 left-20 w-96 h-96 bg-indigo-600 rounded-full blur-[120px] opacity-40 animate-pulse"></div>
      <div class="absolute bottom-20 right-20 w-96 h-96 bg-purple-600 rounded-full blur-[120px] opacity-40 animate-pulse" style="animation-delay: 1s;"></div>
      <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-pink-600 rounded-full blur-[150px] opacity-20 animate-pulse" style="animation-delay: 2s;"></div>

      <div class="container-custom relative z-10 py-20">
        <div class="max-w-6xl mx-auto">
          <!-- Badge -->
          <div class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-full mb-8 animate-fade-in">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span class="text-sm font-semibold tracking-wide">AVAILABLE FOR FREELANCE WORK</span>
          </div>

          <!-- Main Heading -->
          <h1 class="text-7xl md:text-8xl lg:text-9xl font-black mb-8 leading-none animate-fade-in-up">
            <span class="block">CREATIVE</span>
            <span class="block text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">
              DEVELOPER
            </span>
          </h1>

          <!-- Subtitle -->
          <p class="text-xl md:text-2xl text-gray-300 mb-12 max-w-3xl leading-relaxed animate-fade-in-up" style="animation-delay: 0.2s;">
            I craft exceptional digital experiences through 
            <span class="text-indigo-400 font-bold">full-stack development</span>, 
            <span class="text-purple-400 font-bold">modern design</span>, and 
            <span class="text-pink-400 font-bold">creative solutions</span> that drive results.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap gap-4 mb-16 animate-fade-in-up" style="animation-delay: 0.4s;">
            <button @click="$router.push('/projects')" class="group px-8 py-4 bg-white text-black font-bold rounded-full hover:bg-indigo-500 hover:text-white transition-all duration-300 transform hover:scale-105 shadow-2xl">
              <span class="flex items-center gap-2">
                VIEW MY WORK
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
              </span>
            </button>
            <button @click="$router.push('/contact')" class="px-8 py-4 bg-white/10 backdrop-blur-md border-2 border-white/30 text-white font-bold rounded-full hover:bg-white hover:text-black transition-all duration-300">
              LET'S TALK
            </button>
          </div>

          <!-- Tech Stack Marquee -->
          <div class="animate-fade-in-up" style="animation-delay: 0.6s;">
            <p class="text-sm text-gray-500 mb-4 tracking-widest">TECH STACK</p>
            <div class="flex flex-wrap gap-4">
              <span v-for="tech in techStack" :key="tech" class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-sm font-semibold hover:bg-white/10 transition-all">
                {{ tech }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Scroll Indicator -->
      <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2">
        <div class="flex flex-col items-center gap-2 animate-bounce">
          <span class="text-xs text-gray-400 tracking-widest">SCROLL</span>
          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
          </svg>
        </div>
      </div>
    </section>

    <!-- Stats Section - Bold Cards -->
    <section class="py-32 bg-gradient-to-b from-black to-gray-900">
      <div class="container-custom">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div v-for="(stat, index) in stats" :key="stat.label" 
               class="relative group cursor-pointer"
               :style="`animation-delay: ${index * 0.1}s`">
            <!-- Card -->
            <div class="relative p-8 bg-gradient-to-br from-gray-900 to-black border-2 border-gray-800 rounded-3xl overflow-hidden transition-all duration-500 hover:border-indigo-500 hover:shadow-2xl hover:shadow-indigo-500/20 hover:-translate-y-2">
              <!-- Glow Effect -->
              <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/0 to-purple-600/0 group-hover:from-indigo-600/10 group-hover:to-purple-600/10 transition-all duration-500"></div>
              
              <div class="relative">
                <div class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400 mb-2">
                  {{ stat.value }}
                </div>
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest">
                  {{ stat.label }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured Projects - Grid Style -->
    <section class="py-32 bg-gray-900">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="mb-20">
          <p class="text-indigo-400 font-bold text-sm tracking-widest mb-4">FEATURED WORK</p>
          <h2 class="text-6xl md:text-7xl font-black text-white mb-6">
            SELECTED
            <span class="block text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">
              PROJECTS
            </span>
          </h2>
          <p class="text-xl text-gray-400 max-w-2xl">
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
            class="group cursor-pointer"
            :style="`animation-delay: ${index * 0.15}s`"
          >
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-gray-800 to-black border-2 border-gray-800 hover:border-indigo-500 transition-all duration-500 hover:shadow-2xl hover:shadow-indigo-500/20">
              <!-- Image Area -->
              <div class="relative aspect-video bg-gradient-to-br from-indigo-900/20 to-purple-900/20 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 to-purple-600/30 group-hover:scale-110 transition-transform duration-700"></div>
                
                <!-- Hover Overlay -->
                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-center justify-center">
                  <span class="text-white font-bold text-xl flex items-center gap-2">
                    VIEW PROJECT
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                  </span>
                </div>
              </div>
              
              <!-- Content -->
              <div class="p-8">
                <h3 class="text-3xl font-black text-white mb-3 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-indigo-400 group-hover:to-purple-400 transition-all">
                  {{ project.title }}
                </h3>
                <p class="text-gray-400 mb-6">
                  {{ project.description }}
                </p>
                <div class="flex flex-wrap gap-2">
                  <span v-for="tech in project.technologies?.slice(0, 4)" :key="tech" 
                        class="px-3 py-1 bg-white/5 border border-white/10 text-indigo-400 rounded-full text-xs font-bold">
                    {{ tech }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-16">
          <button @click="$router.push('/projects')" class="group px-8 py-4 bg-white/10 backdrop-blur-md border-2 border-white/20 text-white font-bold rounded-full hover:bg-white hover:text-black transition-all duration-300">
            <span class="flex items-center gap-2">
              VIEW ALL PROJECTS
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
              </svg>
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- Latest Blog - Minimal Cards -->
    <section class="py-32 bg-black">
      <div class="container-custom">
        <!-- Section Header -->
        <div class="mb-20">
          <p class="text-purple-400 font-bold text-sm tracking-widest mb-4">INSIGHTS & ARTICLES</p>
          <h2 class="text-6xl md:text-7xl font-black text-white mb-6">
            LATEST
            <span class="block text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-400">
              THOUGHTS
            </span>
          </h2>
        </div>

        <BaseLoader v-if="postsLoading" text="Loading posts..." />

        <!-- Posts Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="(post, index) in latestPosts"
            :key="post.id"
            @click="$router.push(`/blog/${post.slug}`)"
            class="group cursor-pointer"
            :style="`animation-delay: ${index * 0.1}s`"
          >
            <div class="relative overflow-hidden rounded-3xl bg-gray-900 border-2 border-gray-800 hover:border-purple-500 transition-all duration-500 hover:shadow-2xl hover:shadow-purple-500/20 h-full flex flex-col">
              <!-- Image -->
              <div class="relative aspect-video bg-gradient-to-br from-purple-900/20 to-pink-900/20 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-600/30 to-pink-600/30 group-hover:scale-110 transition-transform duration-700"></div>
              </div>
              
              <!-- Content -->
              <div class="p-6 flex-1 flex flex-col">
                <div class="flex items-center gap-3 mb-4">
                  <span class="px-3 py-1 bg-purple-500/20 border border-purple-500/30 text-purple-400 rounded-full text-xs font-bold uppercase tracking-wider">
                    {{ post.category?.name }}
                  </span>
                  <span class="text-xs text-gray-500">
                    {{ formatDate(post.published_at) }}
                  </span>
                </div>
                
                <h3 class="text-xl font-bold text-white mb-3 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-purple-400 group-hover:to-pink-400 transition-all line-clamp-2">
                  {{ post.title }}
                </h3>
                
                <p class="text-gray-400 text-sm line-clamp-3 flex-1 mb-4">
                  {{ post.excerpt }}
                </p>
                
                <div class="flex items-center text-purple-400 font-bold text-sm group-hover:gap-2 transition-all">
                  <span>READ ARTICLE</span>
                  <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- View All Button -->
        <div class="text-center mt-16">
          <button @click="$router.push('/blog')" class="group px-8 py-4 bg-white/10 backdrop-blur-md border-2 border-white/20 text-white font-bold rounded-full hover:bg-white hover:text-black transition-all duration-300">
            <span class="flex items-center gap-2">
              READ ALL ARTICLES
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
              </svg>
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- CTA Section - Bold & Direct -->
    <section class="relative py-40 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 overflow-hidden">
      <!-- Animated Background -->
      <div class="absolute inset-0">
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 1px, transparent 1px); background-size: 50px 50px;"></div>
      </div>
      
      <!-- Glowing Orbs -->
      <div class="absolute top-0 left-0 w-96 h-96 bg-white/20 rounded-full blur-3xl animate-pulse"></div>
      <div class="absolute bottom-0 right-0 w-96 h-96 bg-white/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>

      <div class="container-custom text-center relative z-10">
        <h2 class="text-6xl md:text-7xl font-black text-white mb-8 leading-tight">
          LET'S BUILD<br>
          SOMETHING GREAT
        </h2>
        <p class="text-2xl text-white/90 mb-12 max-w-2xl mx-auto font-medium">
          Ready to start your next project? Let's create something extraordinary together.
        </p>
        <button @click="$router.push('/contact')" class="px-12 py-5 bg-black text-white font-black text-lg rounded-full hover:bg-white hover:text-black transition-all duration-300 transform hover:scale-105 shadow-2xl">
          START A PROJECT
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
  { value: '50+', label: 'PROJECTS' },
  { value: '100+', label: 'ARTICLES' },
  { value: '200+', label: 'CLIENTS' },
  { value: '5+', label: 'YEARS' }
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
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes fade-in-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fade-in 0.8s ease-out;
}

.animate-fade-in-up {
  animation: fade-in-up 1s ease-out;
}

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
