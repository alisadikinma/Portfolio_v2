<template>
  <div ref="containerRef" class="w-full h-[400px] relative">
    <TresCanvas
      v-if="isVisible"
      :clear-color="'#050506'"
      :alpha="true"
      window-size
    >
      <TresPerspectiveCamera :position="[0, 0, 2.8]" />
      <OrbitControls
        v-if="isDesktop"
        :enable-zoom="false"
        :enable-pan="false"
        :auto-rotate="true"
        :auto-rotate-speed="0.5"
      />

      <!-- Ambient light -->
      <TresAmbientLight :intensity="0.3" />
      <TresPointLight :position="[5, 3, 5]" :intensity="1" color="#D4A843" />

      <!-- Globe sphere (wireframe) -->
      <TresMesh :rotation="[0, autoRotation, 0]">
        <TresSphereGeometry :args="[1, 32, 32]" />
        <TresMeshBasicMaterial
          :wireframe="true"
          color="#1a1a2e"
          :opacity="0.3"
          :transparent="true"
        />
      </TresMesh>

      <!-- Country dots -->
      <TresMesh
        v-for="country in countries"
        :key="country.name"
        :position="country.position"
        @pointer-enter="hoveredCountry = country.name"
        @pointer-leave="hoveredCountry = ''"
      >
        <TresSphereGeometry :args="[country.home ? 0.025 : 0.015, 8, 8]" />
        <TresMeshBasicMaterial
          :color="country.home ? '#D4A843' : '#06B6D4'"
        />
      </TresMesh>
    </TresCanvas>

    <!-- Tooltip -->
    <div
      v-if="hoveredCountry"
      class="absolute top-4 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-lg bg-bg-elevated/90 border border-border-hairline text-sm text-fg-primary font-mono pointer-events-none z-10"
    >
      {{ hoveredCountry }}
    </div>

    <!-- Loading state -->
    <div v-if="!isVisible" class="w-full h-full flex items-center justify-center">
      <p class="text-fg-dim mono-label">Loading Globe...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, shallowRef } from 'vue'
import { TresCanvas } from '@tresjs/core'
import { useGlobe } from '@/composables/useGlobe'

// Lazy-load OrbitControls
const OrbitControls = shallowRef(null)

const { countries } = useGlobe()
const containerRef = ref(null)
const isVisible = ref(false)
const isDesktop = ref(false)
const hoveredCountry = ref('')
const autoRotation = ref(0)

let animFrame = null
let observer = null

onMounted(async () => {
  isDesktop.value = window.matchMedia('(pointer: fine)').matches

  // Dynamically import OrbitControls
  try {
    const mod = await import('@tresjs/core')
    // OrbitControls may need separate import
    // For now, auto-rotate is handled via the mesh rotation
  } catch (e) {
    console.warn('OrbitControls not available')
  }

  // Only render when visible
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        isVisible.value = true
        startRotation()
        observer.disconnect()
      }
    },
    { threshold: 0.1 }
  )

  if (containerRef.value) observer.observe(containerRef.value)
})

function startRotation() {
  if (!isDesktop.value) {
    // Auto-rotate for mobile
    function rotate() {
      autoRotation.value += 0.002
      animFrame = requestAnimationFrame(rotate)
    }
    animFrame = requestAnimationFrame(rotate)
  }
}

onUnmounted(() => {
  if (observer) observer.disconnect()
  if (animFrame) cancelAnimationFrame(animFrame)
})
</script>
