<template>
  <section ref="sectionRef" class="py-28">
    <div class="container-custom">
      <!-- Header -->
      <div class="flex items-end justify-between mb-16 reveal" :class="{ 'is-visible': isVisible }">
        <div>
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Portfolio</span>
          <h2 class="section-heading text-4xl md:text-5xl lg:text-6xl text-gradient mt-4">Featured Projects</h2>
          <p class="text-fg-muted text-lg mt-3 font-light max-w-md">Selected builds from AI automation to full-stack platforms.</p>
        </div>
        <router-link to="/work" class="hidden md:flex items-center gap-2 btn-glass text-sm py-2.5 px-5">
          View All
          <span class="btn-icon-island w-6 h-6">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
          </span>
        </router-link>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <div class="md:col-span-7 bezel-shell-sm"><div class="bezel-core-sm animate-pulse h-72" /></div>
        <div class="md:col-span-5 bezel-shell-sm"><div class="bezel-core-sm animate-pulse h-72" /></div>
        <div class="md:col-span-5 bezel-shell-sm"><div class="bezel-core-sm animate-pulse h-64" /></div>
        <div class="md:col-span-7 bezel-shell-sm"><div class="bezel-core-sm animate-pulse h-64" /></div>
      </div>

      <!-- Asymmetric Bento Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-12 gap-5">
        <router-link
          v-for="(project, index) in projects"
          :key="project.id"
          :to="`/projects/${project.slug}`"
          class="group"
          :class="getGridSpan(index)"
        >
          <div class="bezel-shell-sm h-full">
            <div class="bezel-core-sm p-5 h-full flex flex-col">
              <!-- Thumbnail (compact) -->
              <div class="rounded-lg overflow-hidden mb-3 bg-bg-elevated flex-shrink-0 aspect-[16/5]">
                <img
                  v-if="project.thumbnail"
                  :src="project.thumbnail"
                  :alt="project.title"
                  class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                  loading="lazy"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-accent-gold/5 to-accent-cyan/5">
                  <svg class="w-10 h-10 text-fg-dim/30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25z"/>
                  </svg>
                </div>
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
                <div class="mt-auto pt-4 flex items-center gap-2 text-fg-dim group-hover:text-accent-gold transition-all duration-700 ease-spring">
                  <span class="text-xs font-medium">View Project</span>
                  <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-700 ease-spring" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
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
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import api from '@/services/api'

gsap.registerPlugin(ScrollTrigger)

const projects = ref([])
const isLoading = ref(true)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null
const triggers = []

// Asymmetric 7/5 alternating grid — NOT 3-equal-column
function getGridSpan(index) {
  const spans = [
    'md:col-span-7',  // Row 1: large
    'md:col-span-5',  // Row 1: small
    'md:col-span-5',  // Row 2: small (reversed)
    'md:col-span-7',  // Row 2: large (reversed)
    'md:col-span-6',  // Row 3: equal split
    'md:col-span-6',  // Row 3: equal split
  ]
  return spans[index] || 'md:col-span-6'
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

  // GSAP stagger reveal for project cards
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (!prefersReduced && sectionRef.value) {
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        isVisible.value = true
        const cards = sectionRef.value.querySelectorAll('.group')
        gsap.fromTo(cards, { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'power3.out', stagger: 0.1 })
      }
    })
    triggers.push(st)
  } else {
    isVisible.value = true
  }
})

onUnmounted(() => {
  triggers.forEach(st => st.kill())
})
</script>
