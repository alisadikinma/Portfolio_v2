<template>
  <section ref="sectionRef" class="py-28">
    <div class="container-custom">
      <div
        class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-12 items-center reveal"
        :class="{ 'is-visible': isVisible }"
      >
        <!-- Video Side (7 cols) -->
        <div
          class="md:col-span-7"
          :class="reversed ? 'md:order-2' : 'md:order-1'"
        >
          <div class="bezel-shell">
            <div class="bezel-core overflow-hidden">
              <div class="aspect-video relative bg-bg-elevated">
                <video
                  v-if="videoSrc"
                  ref="videoRef"
                  class="w-full h-full object-cover"
                  loop
                  muted
                  playsinline
                  preload="metadata"
                  @error="videoError = true"
                >
                  <source :src="videoSrc" type="video/mp4" />
                </video>
                <!-- Fallback when no video or video error -->
                <div
                  v-if="!videoSrc || videoError"
                  class="absolute inset-0 flex items-center justify-center"
                  :class="fallbackGradient"
                >
                  <div class="text-center">
                    <svg class="w-12 h-12 text-fg-dim mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/>
                    </svg>
                    <p class="text-fg-dim text-sm font-light">Video coming soon</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Text Side (5 cols) -->
        <div
          class="md:col-span-5"
          :class="reversed ? 'md:order-1' : 'md:order-2'"
        >
          <!-- Eyebrow -->
          <span
            class="eyebrow-tag mb-4 inline-flex"
            :class="accentClasses.eyebrow"
          >
            {{ subtitle }}
          </span>

          <!-- Heading -->
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-4">
            {{ title }}
          </h2>

          <!-- Description -->
          <p class="text-fg-muted text-base leading-relaxed font-light mb-8 max-w-lg">
            {{ description }}
          </p>

          <!-- Links -->
          <div v-if="links && links.length" class="flex flex-wrap gap-3">
            <a
              v-for="link in links"
              :key="link.url"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-glass text-sm py-2.5 px-5 group"
            >
              <span v-if="link.icon" class="w-4 h-4" v-html="link.icon"></span>
              {{ link.label }}
              <span class="btn-icon-island w-5 h-5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
              </span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useVideoReveal } from '@/composables/useVideoReveal'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: 'Skill' },
  description: { type: String, default: '' },
  videoSrc: { type: String, default: '' },
  links: { type: Array, default: () => [] },
  reversed: { type: Boolean, default: false },
  accentColor: { type: String, default: 'gold' } // gold | cyan | indigo
})

const videoRef = ref(null)
const videoError = ref(false)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null

const { setupVideoReveal } = useVideoReveal()

const accentClasses = computed(() => {
  const map = {
    gold: { eyebrow: 'text-accent-gold border-accent-gold/20' },
    cyan: { eyebrow: 'text-accent-cyan border-accent-cyan/20' },
    indigo: { eyebrow: 'text-accent-indigo border-accent-indigo/20' }
  }
  return map[props.accentColor] || map.gold
})

const fallbackGradient = computed(() => {
  const map = {
    gold: 'bg-gradient-to-br from-accent-gold/5 to-transparent',
    cyan: 'bg-gradient-to-br from-accent-cyan/5 to-transparent',
    indigo: 'bg-gradient-to-br from-accent-indigo/5 to-transparent'
  }
  return map[props.accentColor] || map.gold
})

onMounted(() => {
  // Video auto-play on scroll
  if (videoRef.value) {
    setupVideoReveal(videoRef.value)
  }

  // Section scroll reveal
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
