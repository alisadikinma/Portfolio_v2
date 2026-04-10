<template>
  <section ref="sectionRef" class="py-12 md:py-16 overflow-hidden w-full">
    <div class="container-custom">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-14 items-center">

        <!-- Video Side (7 cols) -->
        <div
          ref="videoSide"
          class="md:col-span-7 opacity-0"
          :class="reversed ? 'md:order-2' : 'md:order-1'"
        >
          <div class="bezel-shell">
            <div class="bezel-core overflow-hidden">
              <div class="aspect-video relative bg-bg-elevated">
                <!-- Poster image: shows immediately, hidden once video is ready -->
                <img
                  v-if="videoSrc && !videoReady"
                  :src="posterSrc"
                  alt=""
                  class="absolute inset-0 w-full h-full object-cover"
                />
                <!-- Video: only loads when section is in viewport -->
                <video
                  v-if="videoSrc && shouldLoadVideo"
                  ref="videoRef"
                  class="w-full h-full object-cover transition-opacity duration-500"
                  :class="videoReady ? 'opacity-100' : 'opacity-0'"
                  loop
                  muted
                  playsinline
                  preload="none"
                  @canplay="videoReady = true"
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
        </div>

        <!-- Text Side (5 cols) -->
        <div
          ref="textSide"
          class="md:col-span-5 opacity-0"
          :class="reversed ? 'md:order-1' : 'md:order-2'"
        >
          <!-- Eyebrow -->
          <span class="eyebrow-tag mb-4 inline-flex" :class="accentClasses.eyebrow">
            {{ subtitle }}
          </span>

          <!-- Heading -->
          <h2 class="section-heading text-3xl md:text-4xl text-fg-primary mt-4 mb-4">
            {{ title }}
          </h2>

          <!-- Short Description (max 3 lines) -->
          <p class="text-fg-muted text-sm leading-relaxed font-light mb-5 line-clamp-3">
            {{ description }}
          </p>

          <!-- Bullet Points (compact) -->
          <ul v-if="bullets && bullets.length" class="space-y-2 mb-5">
            <li
              v-for="(bullet, i) in bullets.slice(0, 4)"
              :key="i"
              class="flex items-start gap-2.5"
            >
              <span class="w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" :class="accentClasses.bulletBg">
                <svg class="w-2.5 h-2.5" :class="accentClasses.bulletIcon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              </span>
              <span class="text-xs text-fg-muted font-light leading-relaxed">{{ bullet }}</span>
            </li>
          </ul>

          <!-- Links (compact pills) -->
          <div v-if="links && links.length" class="flex flex-wrap gap-2 mb-4">
            <a
              v-for="link in links"
              :key="link.url"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 text-[11px] py-1.5 px-3 rounded-full bg-white/4 border border-border-hairline text-fg-muted hover:text-accent-gold hover:border-accent-gold/20 transition-all duration-700 ease-spring"
            >
              {{ link.label }}
              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
            </a>
          </div>

          <!-- Social Links -->
          <div v-if="socialLinks && socialLinks.length" class="flex gap-2">
            <a
              v-for="social in socialLinks"
              :key="social.url"
              :href="social.url"
              target="_blank"
              rel="noopener noreferrer"
              class="w-8 h-8 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-accent-gold hover:border-accent-gold/30 transition-all duration-700 ease-spring"
              :aria-label="social.label"
            >
              <span class="w-3.5 h-3.5" v-html="social.icon"></span>
            </a>
          </div>
        </div>
      </div>

      <!-- YouTube Row — BELOW the main grid, full width -->
      <div
        v-if="youtubeVideos && youtubeVideos.length"
        ref="youtubeRow"
        class="mt-10 opacity-0"
      >
        <p class="eyebrow-tag text-fg-dim mb-4 inline-flex">Published Work</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3">
          <a
            v-for="yt in youtubeVideos"
            :key="yt.url"
            :href="yt.url"
            target="_blank"
            rel="noopener noreferrer"
            class="group"
          >
            <div class="bezel-shell-sm">
              <div class="bezel-core-sm overflow-hidden flex flex-col">
                <!-- Thumbnail -->
                <div class="aspect-video relative bg-bg-elevated">
                  <img
                    :src="getYouTubeThumbnail(yt.url)"
                    :alt="yt.title"
                    class="w-full h-full object-cover transition-transform duration-700 ease-spring group-hover:scale-105"
                    loading="lazy"
                  />
                  <div class="absolute inset-0 flex items-center justify-center">
                    <div class="w-9 h-9 rounded-full bg-white/15 border border-white/20 flex items-center justify-center group-hover:scale-110 group-hover:bg-white/25 transition-all duration-700 ease-spring">
                      <svg class="w-3.5 h-3.5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                  </div>
                </div>
                <!-- Info -->
                <div class="p-3 flex flex-col justify-center">
                  <p class="text-xs text-fg-primary font-medium line-clamp-2 group-hover:text-accent-gold transition-colors duration-700 ease-spring">{{ yt.title }}</p>
                  <p class="text-[10px] text-fg-dim mt-1.5 flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    YouTube
                  </p>
                </div>
              </div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { useVideoReveal } from '@/composables/useVideoReveal'

gsap.registerPlugin(ScrollTrigger)

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

const sectionRef = ref(null)
const videoSide = ref(null)
const textSide = ref(null)
const youtubeRow = ref(null)
const videoRef = ref(null)
const videoError = ref(false)
const videoReady = ref(false)
const shouldLoadVideo = ref(false)

const { setupVideoReveal } = useVideoReveal()
const triggers = []

// Poster: use first frame screenshot stored as .webp alongside the .mp4
// e.g. /videos/ai-video.mp4 → /videos/ai-video-poster.webp
// Falls back to a tiny gradient placeholder if poster doesn't exist
const posterSrc = computed(() => {
  if (!props.videoSrc) return ''
  return props.videoSrc.replace(/\.mp4$/, '-poster.webp')
})


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

// Lazy load video only when section enters viewport
let videoObserver = null

function initVideoLazyLoad() {
  if (!props.videoSrc || !sectionRef.value) return

  videoObserver = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        shouldLoadVideo.value = true
        videoObserver?.disconnect()
        // Once video element exists, set up auto-play/pause
        nextTick(() => {
          if (videoRef.value) {
            setupVideoReveal(videoRef.value)
          }
        })
      }
    },
    { rootMargin: '200px' } // Start loading 200px before visible
  )
  videoObserver.observe(sectionRef.value)
}

onMounted(() => {
  initVideoLazyLoad()

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReduced) {
    if (videoSide.value) videoSide.value.style.opacity = '1'
    if (textSide.value) textSide.value.style.opacity = '1'
    if (youtubeRow.value) youtubeRow.value.style.opacity = '1'
    return
  }

  // Video side slides in from left (or right if reversed)
  if (videoSide.value) {
    const fromX = props.reversed ? 60 : -60
    gsap.set(videoSide.value, { x: fromX, opacity: 0 })
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        gsap.to(videoSide.value, { x: 0, opacity: 1, duration: 0.9, ease: 'power3.out' })
      }
    })
    triggers.push(st)
  }

  // Text side slides in from opposite direction, slightly delayed
  if (textSide.value) {
    const fromX = props.reversed ? -40 : 40
    gsap.set(textSide.value, { x: fromX, opacity: 0 })
    const st = ScrollTrigger.create({
      trigger: sectionRef.value,
      start: 'top 80%',
      once: true,
      onEnter: () => {
        gsap.to(textSide.value, { x: 0, opacity: 1, duration: 0.9, ease: 'power3.out', delay: 0.15 })
      }
    })
    triggers.push(st)
  }

  // YouTube row fades up
  if (youtubeRow.value) {
    gsap.set(youtubeRow.value, { y: 30, opacity: 0 })
    const st = ScrollTrigger.create({
      trigger: youtubeRow.value,
      start: 'top 90%',
      once: true,
      onEnter: () => {
        gsap.to(youtubeRow.value, { y: 0, opacity: 1, duration: 0.7, ease: 'power3.out' })
      }
    })
    triggers.push(st)
  }
})

onUnmounted(() => {
  triggers.forEach(st => st.kill())
  videoObserver?.disconnect()
})
</script>
