<template>
  <section ref="sectionRef" class="w-full py-6 lg:py-8">
    <div class="container-custom">
      <!-- Header -->
      <div class="flex items-end justify-between mb-4 lg:mb-6 reveal" :class="{ 'is-visible': isVisible }">
        <div>
          <span class="eyebrow-tag text-accent-gold mb-2 inline-flex">Portfolio</span>
          <h2 class="section-heading text-3xl md:text-4xl lg:text-5xl text-gradient mt-2">Featured Projects</h2>
        </div>
        <router-link to="/work" class="hidden md:flex items-center gap-2 btn-glass text-sm py-2 px-4">
          View All
          <span class="btn-icon-island w-5 h-5">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
          </span>
        </router-link>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="bento-grid">
        <div v-for="i in 5" :key="i" :class="i === 1 ? 'bento-hero' : ''" class="rounded-xl bg-white/[0.02] border border-white/[0.06] animate-pulse aspect-video"></div>
      </div>

      <!-- Bento Grid -->
      <div v-else class="bento-grid">
        <router-link
          v-for="(project, idx) in displayProjects"
          :key="project.id"
          :to="`/projects/${project.slug}`"
          :class="idx === 0 ? 'bento-hero' : ''"
          class="bento-card group"
        >
          <BaseImage
            v-if="project.image || project.featured_image"
            :src="project.image || project.featured_image"
            :variants="project.image_variants"
            :alt="project.title"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            :eager="idx === 0"
            :fetchpriority="idx === 0 ? 'high' : 'auto'"
            class="bento-card__img group-hover:scale-105 transition-transform duration-700"
          />
          <div v-else class="bento-card__img-placeholder">
            <svg class="w-8 h-8 text-fg-dim/20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25z"/></svg>
          </div>

          <!-- Category badge -->
          <span v-if="project.category" class="bento-card__badge">
            {{ project.category?.name || project.category }}
          </span>

          <!-- Text overlay -->
          <div class="bento-card__overlay">
            <h3 :class="idx === 0 ? 'text-sm lg:text-base' : 'text-xs'" class="font-bold text-white leading-snug line-clamp-2">
              {{ project.title }}
            </h3>
            <p v-if="idx === 0" class="text-[11px] text-white/60 mt-1 line-clamp-1">
              {{ project.excerpt || project.description }}
            </p>
          </div>

          <!-- Hover accent -->
          <div class="bento-card__accent"></div>
        </router-link>
      </div>

      <!-- Mobile CTA -->
      <router-link to="/work" class="md:hidden flex items-center justify-center gap-2 mt-4 btn-glass text-sm py-2.5 px-5">
        View All Work
        <span class="btn-icon-island w-5 h-5">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
        </span>
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import api from '@/services/api'
import BaseImage from '@/components/base/BaseImage.vue'

gsap.registerPlugin(ScrollTrigger)

const projects = ref([])
const isLoading = ref(true)
const isVisible = ref(false)
const sectionRef = ref(null)
const triggers = []

const displayProjects = computed(() => {
  const all = [...projects.value]
  const featuredIdx = all.findIndex(p => p.featured)
  if (featuredIdx > 0) {
    const [feat] = all.splice(featuredIdx, 1)
    all.unshift(feat)
  }
  return all.slice(0, 5)
})

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

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (!prefersReduced && sectionRef.value) {
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        isVisible.value = true
        const cards = sectionRef.value.querySelectorAll('.bento-card')
        gsap.fromTo(cards, { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5, ease: 'power3.out', stagger: 0.08 })
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
/* ── Grid: 3 cols desktop, 2 cols tablet, 1 col mobile ── */
.bento-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1fr;
}

@media (min-width: 640px) {
  .bento-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .bento-hero {
    grid-column: span 2;
  }
}

@media (min-width: 1024px) {
  .bento-grid {
    grid-template-columns: repeat(3, 1fr);
  }
  .bento-hero {
    grid-column: span 1;
    grid-row: span 2;
  }
}

/* ── Card ── */
.bento-card {
  position: relative;
  overflow: hidden;
  border-radius: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.06);
  aspect-ratio: 16 / 9;
  transition: border-color 0.4s, box-shadow 0.4s, transform 0.4s;
}

.bento-hero {
  aspect-ratio: 16 / 9;
}

@media (min-width: 1024px) {
  .bento-hero {
    aspect-ratio: auto;
  }
}

.bento-card:hover {
  border-color: rgba(212, 168, 67, 0.25);
  box-shadow: 0 8px 32px -8px rgba(212, 168, 67, 0.12);
  transform: translateY(-2px);
}

/* ── Image ── */
.bento-card__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.bento-card__img-placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(212,168,67,0.05), rgba(6,182,212,0.05));
}

/* ── Badge ── */
.bento-card__badge {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  padding: 0.125rem 0.5rem;
  font-size: 9px;
  font-weight: 700;
  color: white;
  border-radius: 9999px;
  background: linear-gradient(to right, rgba(212,168,67,0.8), rgba(6,182,212,0.8));
  backdrop-filter: blur(4px);
  z-index: 3;
}

/* ── Text overlay ── */
.bento-card__overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 0.75rem;
  background: linear-gradient(to top, rgba(5,5,5,0.9) 0%, rgba(5,5,5,0.4) 60%, transparent 100%);
  z-index: 2;
}

/* ── Hover accent ── */
.bento-card__accent {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(to right, #D4A843, #06B6D4, #5E6AD2);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.5s;
  z-index: 3;
}
.bento-card:hover .bento-card__accent {
  transform: scaleX(1);
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
