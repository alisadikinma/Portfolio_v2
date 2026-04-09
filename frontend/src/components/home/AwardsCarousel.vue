<template>
  <section ref="sectionRef" class="py-24 md:py-32 overflow-hidden">
    <div class="container-custom">
      <!-- Header -->
      <div ref="headerRef" class="text-center mb-14 opacity-0">
        <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Recognition</span>
        <h2 class="section-heading text-4xl md:text-5xl lg:text-6xl text-fg-primary mt-4">Awards & Honors</h2>
        <p class="text-fg-muted text-lg mt-3 font-light">Milestones and industry recognition.</p>
      </div>

      <!-- Carousel Container -->
      <div ref="carouselRef" class="relative opacity-0">
        <!-- Cards Track -->
        <div
          ref="trackRef"
          class="flex gap-5 transition-transform duration-700 ease-spring cursor-grab active:cursor-grabbing"
          :style="{ transform: `translateX(${trackOffset}px)` }"
          @mousedown="startDrag"
          @touchstart.passive="startDrag"
        >
          <div
            v-for="(award, index) in awards"
            :key="award.id"
            class="w-[320px] md:w-[380px] flex-shrink-0"
          >
            <div class="bezel-shell h-full">
              <div class="bezel-core overflow-hidden h-full flex flex-col">
                <!-- Award Image -->
                <div class="aspect-[4/3] relative bg-bg-elevated overflow-hidden">
                  <img
                    v-if="award.image"
                    :src="award.image"
                    :alt="award.title"
                    class="w-full h-full object-cover"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-accent-gold/8 to-accent-indigo/8">
                    <svg class="w-16 h-16 text-accent-gold/20" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.996.436-1.75 1.408-1.75 2.514 0 1.5 1.25 2.75 2.75 2.75S9 8.25 9 6.75c0-1.106-.754-2.078-1.75-2.514M5.25 4.236V2.75m0 1.486l1.448-.804M5.25 4.236l-1.448-.804M18.75 4.236c.996.436 1.75 1.408 1.75 2.514 0 1.5-1.25 2.75-2.75 2.75S15 8.25 15 6.75c0-1.106.754-2.078 1.75-2.514M18.75 4.236V2.75m0 1.486l-1.448-.804M18.75 4.236l1.448-.804"/>
                    </svg>
                  </div>
                  <!-- Year Badge -->
                  <span v-if="award.year" class="absolute bottom-3 right-3 eyebrow-tag text-accent-gold border-accent-gold/20 bg-bg-deep/80 backdrop-blur-sm text-[10px]">
                    {{ award.year }}
                  </span>
                  <!-- Gallery Count -->
                  <span v-if="award.galleries?.length" class="absolute bottom-3 left-3 eyebrow-tag text-fg-muted bg-bg-deep/80 backdrop-blur-sm text-[10px]">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v13.5a2.25 2.25 0 002.25 2.25z"/></svg>
                    {{ award.galleries.reduce((sum, g) => sum + (g.items_count || 0), 0) }} items
                  </span>
                </div>

                <!-- Content -->
                <div class="p-5 flex-1 flex flex-col">
                  <h3 class="font-display text-base font-semibold text-fg-primary mb-1.5 line-clamp-2">
                    {{ award.title }}
                  </h3>
                  <p class="text-xs text-fg-muted font-light line-clamp-2 mb-4">{{ award.description }}</p>

                  <!-- Organization Badge -->
                  <div v-if="award.organization" class="mt-auto flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-accent-cyan flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21"/>
                    </svg>
                    <span class="mono-label text-[9px] text-accent-cyan">{{ award.organization }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Arrows -->
        <button
          v-if="canScrollLeft"
          @click="scroll('left')"
          class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-3 w-10 h-10 rounded-full bg-bg-elevated/90 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-fg-primary hover:border-border-hover transition-all duration-700 ease-spring z-10"
          aria-label="Previous"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        </button>
        <button
          v-if="canScrollRight"
          @click="scroll('right')"
          class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-3 w-10 h-10 rounded-full bg-bg-elevated/90 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-fg-primary hover:border-border-hover transition-all duration-700 ease-spring z-10"
          aria-label="Next"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
        </button>
      </div>

      <!-- Dots Indicator -->
      <div v-if="totalDots > 1" class="flex justify-center gap-2 mt-8">
        <button
          v-for="dot in totalDots"
          :key="dot"
          @click="goToDot(dot - 1)"
          class="h-1.5 rounded-full transition-all duration-700 ease-spring"
          :class="dot - 1 === activeDot ? 'bg-accent-gold w-8' : 'bg-fg-dim/20 w-1.5 hover:bg-fg-muted/40'"
          :aria-label="`Go to slide group ${dot}`"
        />
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import api from '@/services/api'

gsap.registerPlugin(ScrollTrigger)

const awards = ref([])
const isLoading = ref(true)
const sectionRef = ref(null)
const headerRef = ref(null)
const carouselRef = ref(null)
const trackRef = ref(null)
const triggers = []

// Carousel state
const currentIndex = ref(0)
const cardWidth = 400 // 380 + 20 gap
let isDragging = false
let dragStartX = 0
let dragOffset = 0

const trackOffset = computed(() => {
  return -(currentIndex.value * cardWidth) + dragOffset
})

const maxIndex = computed(() => Math.max(0, awards.value.length - 3))

const canScrollLeft = computed(() => currentIndex.value > 0)
const canScrollRight = computed(() => currentIndex.value < maxIndex.value)

const totalDots = computed(() => Math.min(awards.value.length, maxIndex.value + 1))
const activeDot = computed(() => Math.min(currentIndex.value, totalDots.value - 1))

function scroll(direction) {
  if (direction === 'left' && currentIndex.value > 0) {
    currentIndex.value--
  } else if (direction === 'right' && currentIndex.value < maxIndex.value) {
    currentIndex.value++
  }
}

function goToDot(index) {
  currentIndex.value = Math.min(index, maxIndex.value)
}

// Drag handling
function startDrag(e) {
  isDragging = true
  dragStartX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX
  dragOffset = 0
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', endDrag)
  window.addEventListener('touchmove', onDrag, { passive: true })
  window.addEventListener('touchend', endDrag)
}

function onDrag(e) {
  if (!isDragging) return
  const x = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX
  dragOffset = x - dragStartX
}

function endDrag() {
  isDragging = false
  if (Math.abs(dragOffset) > 80) {
    if (dragOffset < 0) scroll('right')
    else scroll('left')
  }
  dragOffset = 0
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', endDrag)
  window.removeEventListener('touchmove', onDrag)
  window.removeEventListener('touchend', endDrag)
}

onMounted(async () => {
  try {
    const res = await api.get('/awards')
    awards.value = res.data.data || []
  } catch (err) {
    console.error('Failed to fetch awards:', err)
  } finally {
    isLoading.value = false
  }

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReduced) {
    if (headerRef.value) headerRef.value.style.opacity = '1'
    if (carouselRef.value) carouselRef.value.style.opacity = '1'
    return
  }

  // GSAP header reveal
  if (headerRef.value) {
    gsap.set(headerRef.value, { y: 30, opacity: 0 })
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        gsap.to(headerRef.value, { y: 0, opacity: 1, duration: 0.7, ease: 'power3.out' })
      }
    })
    triggers.push(st)
  }

  // GSAP carousel reveal
  if (carouselRef.value) {
    gsap.set(carouselRef.value, { y: 40, opacity: 0 })
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 70%',
      once: true,
      onEnter: () => {
        gsap.to(carouselRef.value, { y: 0, opacity: 1, duration: 0.8, ease: 'power3.out', delay: 0.2 })
      }
    })
    triggers.push(st)
  }
})

onUnmounted(() => {
  triggers.forEach(st => st.kill())
})
</script>
