<template>
  <section ref="sectionRef" class="py-28">
    <div class="container-custom">
      <!-- Header -->
      <div class="flex items-end justify-between mb-16 reveal" :class="{ 'is-visible': isVisible }">
        <div>
          <span class="eyebrow-tag text-accent-cyan mb-4 inline-flex">Selected Work</span>
          <h2 class="section-heading text-4xl md:text-5xl lg:text-6xl text-gradient mt-4">Featured Work</h2>
          <p class="text-fg-muted text-lg mt-3 font-light">Selected projects from 56+ delivered.</p>
        </div>
        <router-link to="/work" class="hidden md:flex items-center gap-2 btn-glass text-sm py-2.5 px-5">
          View All
          <span class="btn-icon-island w-6 h-6">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
          </span>
        </router-link>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <div class="md:col-span-8 bezel-shell"><div class="bezel-core animate-pulse h-80" /></div>
        <div class="md:col-span-4 bezel-shell"><div class="bezel-core animate-pulse h-80" /></div>
        <div class="md:col-span-4 bezel-shell"><div class="bezel-core animate-pulse h-64" /></div>
        <div class="md:col-span-4 bezel-shell"><div class="bezel-core animate-pulse h-64" /></div>
        <div class="md:col-span-4 bezel-shell"><div class="bezel-core animate-pulse h-64" /></div>
      </div>

      <!-- Asymmetric Bento Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <router-link
          v-for="(project, index) in projects"
          :key="project.id"
          :to="`/projects/${project.slug}`"
          class="group"
          :class="getBentoSpan(index)"
        >
          <!-- Outer bezel shell -->
          <div class="bezel-shell-sm h-full">
            <!-- Inner core -->
            <div class="bezel-core-sm p-5 h-full flex flex-col">
              <!-- Thumbnail -->
              <div v-if="project.thumbnail" class="rounded-xl overflow-hidden mb-4 bg-bg-elevated flex-shrink-0" :class="index === 0 ? 'aspect-[16/9]' : 'aspect-video'">
                <img
                  :src="project.thumbnail"
                  :alt="project.title"
                  class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                  loading="lazy"
                />
              </div>
              <!-- Content -->
              <div class="flex-1 flex flex-col">
                <div v-if="project.category" class="mb-2">
                  <span class="eyebrow-tag text-accent-cyan text-[9px]">{{ project.category?.name || project.category }}</span>
                </div>
                <h3 class="font-display text-lg font-semibold text-fg-primary group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  {{ project.title }}
                </h3>
                <p class="text-sm text-fg-muted mt-2 line-clamp-2 font-light">{{ project.excerpt || project.description }}</p>
                <!-- Arrow indicator -->
                <div class="mt-auto pt-4 flex items-center gap-2 text-fg-dim group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  <span class="text-xs font-medium">View Project</span>
                  <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/>
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </router-link>
      </div>

      <!-- Mobile CTA -->
      <router-link to="/work" class="md:hidden flex items-center justify-center gap-2 mt-10 btn-glass text-sm py-3 px-6">
        View All Work
        <span class="btn-icon-island w-6 h-6">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
        </span>
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'

const projects = ref([])
const isLoading = ref(true)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null

// Asymmetric bento grid spans
function getBentoSpan(index) {
  const spans = [
    'md:col-span-8',   // Large hero card
    'md:col-span-4',   // Side card
    'md:col-span-4',   // Bottom row
    'md:col-span-4',
    'md:col-span-4',
    'md:col-span-6',   // Extra row half
  ]
  return spans[index] || 'md:col-span-4'
}

onMounted(async () => {
  try {
    const res = await api.get('/projects', { params: { per_page: 6, featured: 1 } })
    projects.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch featured projects:', err)
  } finally {
    isLoading.value = false
  }

  // Scroll reveal
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        isVisible.value = true
        observer.disconnect()
      }
    },
    { threshold: 0.1 }
  )
  if (sectionRef.value) observer.observe(sectionRef.value)
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>
