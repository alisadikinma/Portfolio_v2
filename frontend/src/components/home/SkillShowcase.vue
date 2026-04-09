<template>
  <section ref="sectionRef" class="py-28">
    <div class="container-custom">
      <div
        class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-12 items-start reveal"
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

          <!-- YouTube Thumbnails (below main video) -->
          <div v-if="youtubeVideos && youtubeVideos.length" class="grid grid-cols-2 gap-3 mt-3">
            <a
              v-for="yt in youtubeVideos"
              :key="yt.url"
              :href="yt.url"
              target="_blank"
              rel="noopener noreferrer"
              class="group"
            >
              <div class="bezel-shell-sm">
                <div class="bezel-core-sm overflow-hidden">
                  <div class="aspect-video relative bg-bg-elevated">
                    <img
                      :src="getYouTubeThumbnail(yt.url)"
                      :alt="yt.title"
                      class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                      loading="lazy"
                    />
                    <!-- Play icon overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-bg-deep/30 group-hover:bg-bg-deep/10 transition-all duration-700 ease-spring">
                      <div class="w-10 h-10 rounded-full bg-white/15 border border-white/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-700 ease-spring">
                        <svg class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      </div>
                    </div>
                  </div>
                  <div class="p-3">
                    <p class="text-xs text-fg-muted font-light line-clamp-2 group-hover:text-fg-primary transition-colors duration-700 ease-spring">{{ yt.title }}</p>
                  </div>
                </div>
              </div>
            </a>
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
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-5">
            {{ title }}
          </h2>

          <!-- Description -->
          <p class="text-fg-muted text-base leading-relaxed font-light mb-6 max-w-lg">
            {{ description }}
          </p>

          <!-- Bullet Points (proof points) -->
          <ul v-if="bullets && bullets.length" class="space-y-3 mb-8">
            <li
              v-for="(bullet, i) in bullets"
              :key="i"
              class="flex items-start gap-3"
            >
              <span class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" :class="accentClasses.bulletBg">
                <svg class="w-3 h-3" :class="accentClasses.bulletIcon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              </span>
              <span class="text-sm text-fg-muted font-light leading-relaxed">{{ bullet }}</span>
            </li>
          </ul>

          <!-- Links -->
          <div v-if="links && links.length" class="flex flex-wrap gap-2.5 mb-6">
            <a
              v-for="link in links"
              :key="link.url"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-glass text-xs py-2 px-4 group"
            >
              <span v-if="link.icon" class="w-3.5 h-3.5" v-html="link.icon"></span>
              {{ link.label }}
              <span class="btn-icon-island w-5 h-5">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
              </span>
            </a>
          </div>

          <!-- Social Links -->
          <div v-if="socialLinks && socialLinks.length" class="flex gap-2.5">
            <a
              v-for="social in socialLinks"
              :key="social.url"
              :href="social.url"
              target="_blank"
              rel="noopener noreferrer"
              class="w-9 h-9 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-accent-gold hover:border-accent-gold/30 transition-all duration-700 ease-spring"
              :aria-label="social.label"
            >
              <span class="w-4 h-4" v-html="social.icon"></span>
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
  bullets: { type: Array, default: () => [] },
  videoSrc: { type: String, default: '' },
  links: { type: Array, default: () => [] },
  youtubeVideos: { type: Array, default: () => [] },
  socialLinks: { type: Array, default: () => [] },
  reversed: { type: Boolean, default: false },
  accentColor: { type: String, default: 'gold' }
})

const videoRef = ref(null)
const videoError = ref(false)
const isVisible = ref(false)
const sectionRef = ref(null)
let observer = null

const { setupVideoReveal } = useVideoReveal()

const accentClasses = computed(() => {
  const map = {
    gold: {
      eyebrow: 'text-accent-gold border-accent-gold/20',
      bulletBg: 'bg-accent-gold/10',
      bulletIcon: 'text-accent-gold'
    },
    cyan: {
      eyebrow: 'text-accent-cyan border-accent-cyan/20',
      bulletBg: 'bg-accent-cyan/10',
      bulletIcon: 'text-accent-cyan'
    },
    indigo: {
      eyebrow: 'text-accent-indigo border-accent-indigo/20',
      bulletBg: 'bg-accent-indigo/10',
      bulletIcon: 'text-accent-indigo'
    }
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

function getYouTubeThumbnail(url) {
  const match = url.match(/(?:youtu\.be\/|youtube\.com\/watch\?v=)([^&]+)/)
  return match ? `https://img.youtube.com/vi/${match[1]}/mqdefault.jpg` : ''
}

onMounted(() => {
  if (videoRef.value) {
    setupVideoReveal(videoRef.value)
  }
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
