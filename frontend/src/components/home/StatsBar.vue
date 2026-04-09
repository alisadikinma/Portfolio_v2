<template>
  <section ref="sectionRef" class="py-16 relative z-10">
    <div class="container-custom">
      <!-- Outer Bezel Shell -->
      <div class="bezel-shell">
        <!-- Inner Core — the glass plate -->
        <div class="bezel-core p-6 md:p-8">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
            <div
              v-for="(stat, index) in stats"
              :key="stat.label"
              class="text-center"
            >
              <div class="font-display text-4xl md:text-5xl font-bold text-accent-gold mb-2 tracking-tight">
                {{ animatedValues[index] }}{{ stat.suffix }}
              </div>
              <div class="mono-label text-[10px]">
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
