<template>
  <section ref="sectionRef" class="py-14">
    <div class="container-custom">
      <!-- Header -->
      <div class="flex items-end justify-between mb-8 reveal" :class="{ 'is-visible': isVisible }">
        <div>
          <span class="eyebrow-tag text-accent-gold mb-3 inline-flex">Portfolio</span>
          <h2 class="section-heading text-4xl md:text-5xl lg:text-6xl text-gradient mt-3">Featured Projects</h2>
          <p class="text-fg-muted text-lg mt-2 font-light max-w-md">Selected builds from AI automation to full-stack platforms.</p>
        </div>
        <router-link to="/work" class="hidden md:flex items-center gap-2 btn-glass text-sm py-2.5 px-5">
          View All
          <span class="btn-icon-island w-6 h-6">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
          </span>
        </router-link>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="flex gap-4 overflow-hidden">
        <div v-for="i in 4" :key="i" class="flex-shrink-0 w-[280px] md:w-[320px] bezel-shell-sm">
          <div class="bezel-core-sm animate-pulse h-56"></div>
        </div>
      </div>

      <!-- Horizontal Slider -->
      <div v-else class="slider-wrap">
        <!-- Left fade -->
        <div v-if="canScrollLeft" class="slider-fade slider-fade--left"></div>
        <!-- Right fade -->
        <div v-if="canScrollRight" class="slider-fade slider-fade--right"></div>

        <!-- Left Arrow -->
        <button v-if="canScrollLeft" @click="scrollSlider(-1)" class="slider-arrow slider-arrow--left" aria-label="Scroll left">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <!-- Right Arrow -->
        <button v-if="canScrollRight" @click="scrollSlider(1)" class="slider-arrow slider-arrow--right" aria-label="Scroll right">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>

        <!-- Track -->
        <div ref="trackRef" class="slider-track" @scroll="onTrackScroll">
          <router-link
            v-for="project in projects"
            :key="project.id"
            :to="`/projects/${project.slug}`"
            class="slider-card group"
          >
            <!-- Image -->
            <div class="slider-card__img">
              <img
                v-if="project.image || project.featured_image"
                :src="project.image || project.featured_image"
                :alt="project.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                loading="lazy"
              />
              <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-accent-gold/5 to-accent-cyan/5">
                <svg class="w-8 h-8 text-fg-dim/15" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25z"/></svg>
              </div>
              <!-- Category badge -->
              <span v-if="project.category" class="absolute top-2 right-2 px-2 py-0.5 text-[9px] font-bold text-white rounded-full shadow bg-gradient-to-r from-accent-gold/80 to-accent-cyan/80 backdrop-blur-sm">
                {{ project.category?.name || project.category }}
              </span>
              <!-- Hover overlay -->
              <div class="absolute inset-0 bg-gradient-to-t from-bg-deep/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            </div>

            <!-- Body -->
            <div class="slider-card__body">
              <div v-if="project.client" class="flex items-center gap-1 mb-1">
                <svg class="w-3 h-3 text-accent-gold flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span class="text-[10px] font-semibold text-accent-gold truncate">{{ project.client }}</span>
              </div>
              <h3 class="text-xs font-bold text-fg-primary group-hover:text-accent-gold transition-colors duration-300 leading-snug line-clamp-2">
                {{ project.title }}
              </h3>
              <p class="text-[10px] text-fg-muted mt-0.5 line-clamp-1 font-light">{{ project.excerpt || project.description }}</p>
            </div>

            <!-- Bottom accent -->
            <div class="h-px bg-gradient-to-r from-accent-gold via-accent-cyan to-accent-indigo transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
          </router-link>
        </div>
      </div>

      <!-- Mobile CTA -->
      <router-link to="/work" class="md:hidden flex items-center justify-center gap-2 mt-8 btn-glass text-sm py-3 px-6">
        View All Work
        <span class="btn-icon-island w-6 h-6">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
        </span>
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import api from '@/services/api'

gsap.registerPlugin(ScrollTrigger)

const projects = ref([])
const isLoading = ref(true)
const isVisible = ref(false)
const sectionRef = ref(null)
const trackRef = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(true)
const triggers = []

function onTrackScroll() {
  if (!trackRef.value) return
  const el = trackRef.value
  canScrollLeft.value = el.scrollLeft > 8
  canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 8
}

function scrollSlider(dir) {
  if (!trackRef.value) return
  trackRef.value.scrollBy({ left: dir * 320, behavior: 'smooth' })
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

  await nextTick()
  onTrackScroll()

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (!prefersReduced && sectionRef.value) {
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        isVisible.value = true
        const cards = sectionRef.value.querySelectorAll('.slider-card')
        gsap.fromTo(cards, { x: 60, opacity: 0 }, { x: 0, opacity: 1, duration: 0.5, ease: 'power3.out', stagger: 0.08 })
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

<style scoped>
.slider-wrap {
  position: relative;
  /* Break out of container to go full-width */
  width: 100vw;
  margin-left: calc(-50vw + 50%);
  padding: 0 1rem;
}
@media (min-width: 1024px) {
  .slider-wrap { padding: 0 2rem; }
}

.slider-track {
  display: flex;
  gap: 1rem;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 0.25rem;
}
.slider-track::-webkit-scrollbar { display: none; }

.slider-card {
  flex-shrink: 0;
  /* Responsive: fill width showing 1.3 cards on mobile, 2.3 on sm, 3 on md, 4 on lg */
  width: calc(75vw - 2rem);
  border-radius: 0.75rem;
  overflow: hidden;
  background: var(--bg-elevated, #0C0C0F);
  border: 1px solid rgba(255,255,255,0.06);
  scroll-snap-align: start;
  transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
}
.slider-card:hover {
  border-color: rgba(212,168,67,0.2);
  box-shadow: 0 8px 24px -8px rgba(212,168,67,0.08);
  transform: translateY(-2px);
}
@media (min-width: 640px) {
  .slider-card { width: calc(42vw - 1.5rem); }
}
@media (min-width: 768px) {
  .slider-card { width: calc(33.333vw - 1.5rem); }
}
@media (min-width: 1024px) {
  .slider-card { width: calc(25vw - 1.5rem); }
}
@media (min-width: 1280px) {
  .slider-card { width: calc(25% - 0.75rem); }
}

.slider-card__img {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: rgba(255,255,255,0.03);
}
.slider-card__img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.slider-card__body {
  padding: 0.6rem 0.75rem 0.7rem;
}

/* Fade edges */
.slider-fade {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2.5rem;
  z-index: 5;
  pointer-events: none;
}
.slider-fade--left {
  left: 0;
  background: linear-gradient(to right, var(--bg-deep, #050506), transparent);
}
.slider-fade--right {
  right: 0;
  background: linear-gradient(to left, var(--bg-deep, #050506), transparent);
}

/* Arrows */
.slider-arrow {
  position: absolute;
  top: 40%;
  transform: translateY(-50%);
  z-index: 10;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  backdrop-filter: blur(8px);
  color: rgba(255,255,255,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.slider-arrow:hover {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.3);
  color: #fff;
}
.slider-arrow:active { transform: translateY(-50%) scale(0.92); }
.slider-arrow--left { left: 0.25rem; }
.slider-arrow--right { right: 0.25rem; }
@media (min-width: 1024px) {
  .slider-arrow--left { left: 0.75rem; }
  .slider-arrow--right { right: 0.75rem; }
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
