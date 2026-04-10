<template>
  <section ref="sectionRef" class="pt-4 pb-2 sm:py-10 relative z-10">
    <div class="container-custom">
      <div class="bezel-shell-sm sm:bezel-shell">
        <div class="bezel-core-sm sm:bezel-core p-3 sm:p-6 md:p-8">
          <div class="grid grid-cols-4 gap-2 sm:gap-6 md:gap-8">
            <div
              v-for="(stat, index) in stats"
              :key="stat.label"
              class="text-center"
            >
              <div class="font-display text-lg sm:text-3xl md:text-5xl font-bold text-accent-gold mb-0.5 sm:mb-2 tracking-tight">
                {{ animatedValues[index] }}{{ stat.suffix }}
              </div>
              <div class="mono-label text-[6px] sm:text-[9px] md:text-[10px] leading-tight">
                {{ stat.label }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'

const stats = [
  { value: 17, suffix: '+', label: 'Years Experience' },
  { value: 56, suffix: '+', label: 'Projects Delivered' },
  { value: 1, suffix: '', label: '#1 Champion', prefix: '#' },
  { value: 16, suffix: '', label: 'Countries' }
]

const sectionRef = ref(null)
const animatedValues = reactive(stats.map(() => 0))
let hasAnimated = false
let observer = null

function animateCounters() {
  if (hasAnimated) return
  hasAnimated = true

  stats.forEach((stat, index) => {
    const target = stat.value
    const duration = 1800
    const start = performance.now()

    function step(now) {
      const elapsed = now - start
      const progress = Math.min(elapsed / duration, 1)
      // Spring ease-out
      const eased = 1 - Math.pow(1 - progress, 4)
      animatedValues[index] = stat.prefix
        ? stat.prefix + Math.round(eased * target)
        : Math.round(eased * target)
      if (progress < 1) {
        requestAnimationFrame(step)
      }
    }

    setTimeout(() => requestAnimationFrame(step), index * 120)
  })
}

onMounted(() => {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReduced) {
    stats.forEach((stat, i) => {
      animatedValues[i] = stat.prefix ? stat.prefix + stat.value : stat.value
    })
    return
  }

  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        animateCounters()
        observer.disconnect()
      }
    },
    { threshold: 0.3 }
  )

  if (sectionRef.value) {
    observer.observe(sectionRef.value)
  }
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>
