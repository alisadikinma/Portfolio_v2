<template>
  <section ref="heroRef" class="relative min-h-[100dvh] w-full overflow-hidden flex items-center justify-center">
    <!-- Background Priority: 1) Video  2) Canvas Frames  3) CSS Gradient -->

    <!-- Video Background -->
    <video
      v-if="hasVideo"
      ref="videoRef"
      class="absolute inset-0 w-full h-full object-cover"
      autoplay
      loop
      muted
      playsinline
      preload="auto"
      @loadeddata="onVideoLoaded"
      @error="onVideoError"
    >
      <source :src="videoSrc" type="video/mp4" />
    </video>

    <!-- Canvas Frame Background (fallback if no video) -->
    <canvas
      v-else-if="hasFrames"
      ref="canvasRef"
      class="absolute inset-0 w-full h-full object-cover"
    />

    <!-- Ethereal Mesh Gradient Fallback -->
    <div v-else class="absolute inset-0 hero-gradient" />

    <!-- Deep vignette overlay -->
    <div class="absolute inset-0 bg-bg-deep/60" />
    <div class="absolute inset-0 pointer-events-none" style="box-shadow: inset 0 0 200px 80px #050505;" />

    <!-- Content overlay -->
    <div class="relative z-10 text-center px-4 w-full max-w-6xl mx-auto">

      <!-- Eyebrow Tag -->
      <div
        class="flex justify-center mb-8"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 800ms cubic-bezier(0.32,0.72,0,1),transform 800ms cubic-bezier(0.32,0.72,0,1);' : 'transform:translateY(16px);'"
      >
        <span class="eyebrow-tag text-accent-gold border-accent-gold/20">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          AI Generalist Expert
        </span>
      </div>

      <!-- Name — Massive Display Typography -->
      <h1
        class="font-display font-bold tracking-[-0.04em] leading-none text-gradient mb-6 text-shadow-lg"
        :class="[
          contentVisible ? 'opacity-100' : 'opacity-0',
          'text-6xl sm:text-7xl md:text-8xl lg:text-9xl'
        ]"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 100ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 100ms;' : 'transform:translateY(24px);'"
      >
        ALI SADIKIN
      </h1>

      <!-- Subtitle — Lighter weight, generous letter-spacing -->
      <p
        class="font-sans text-lg sm:text-xl md:text-2xl text-fg-muted font-light tracking-wide mb-16 text-shadow"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 200ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 200ms;' : 'transform:translateY(16px);'"
      >
        Bridging AI &amp; Business Outcomes
      </p>

      <!-- Achievement Badges — Pill capsules -->
      <div
        class="flex flex-wrap justify-center gap-3 md:gap-4"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 350ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 350ms;' : 'transform:translateY(16px);'"
      >
        <span class="eyebrow-tag text-accent-gold border-accent-gold/15 text-shadow">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          #1 Champion
        </span>
        <span class="eyebrow-tag text-accent-cyan border-accent-cyan/15 text-shadow">
          16 Countries
        </span>
        <span class="eyebrow-tag text-accent-indigo border-accent-indigo/15 text-shadow">
          56+ Projects
        </span>
      </div>
    </div>

    <!-- Scroll indicator — floating -->
    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 animate-float-y">
      <span class="mono-label text-fg-dim text-[9px]">Scroll</span>
      <svg class="w-4 h-4 text-fg-dim" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" />
      </svg>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const heroRef = ref(null)
const videoRef = ref(null)
const canvasRef = ref(null)
const contentVisible = ref(false)
const hasVideo = ref(false)
const hasFrames = ref(false)
const videoLoaded = ref(false)

const videoSrc = '/videos/hero-bg.mp4'

async function checkVideo() {
  try {
    const res = await fetch(videoSrc, { method: 'HEAD' })
    if (res.ok) {
      hasVideo.value = true
      return true
    }
  } catch {}
  hasVideo.value = false
  return false
}

function onVideoLoaded() {
  videoLoaded.value = true
}

function onVideoError() {
  hasVideo.value = false
  checkFrames()
}

const frames = []
let totalFrames = 0

async function checkFrames() {
  try {
    const img = new Image()
    img.src = '/frames/hero/frame_0001.jpg'
    await new Promise((resolve, reject) => {
      img.onload = resolve
      img.onerror = reject
    })
    hasFrames.value = true
    totalFrames = 120
    await preloadFrames()
  } catch {
    hasFrames.value = false
  }
}

async function preloadFrames() {
  for (let i = 1; i <= Math.min(30, totalFrames); i++) {
    const img = new Image()
    img.src = `/frames/hero/frame_${String(i).padStart(4, '0')}.jpg`
    frames[i - 1] = img
  }
}

function onScroll() {
  if (!heroRef.value) return

  const rect = heroRef.value.getBoundingClientRect()
  const progress = Math.min(1, Math.max(0, -rect.top / rect.height))

  // Parallax shift on video
  if (videoRef.value) {
    videoRef.value.style.transform = `translateY(${progress * 80}px) scale(1.1)`
  }

  // Draw frame on canvas
  if (hasFrames.value && canvasRef.value) {
    const frameIndex = Math.min(totalFrames - 1, Math.floor(progress * totalFrames))
    const ctx = canvasRef.value.getContext('2d')
    if (frames[frameIndex] && frames[frameIndex].complete) {
      canvasRef.value.width = canvasRef.value.offsetWidth
      canvasRef.value.height = canvasRef.value.offsetHeight
      ctx.drawImage(frames[frameIndex], 0, 0, canvasRef.value.width, canvasRef.value.height)
    }
  }
}

onMounted(async () => {
  const foundVideo = await checkVideo()
  if (!foundVideo) {
    await checkFrames()
  }

  window.addEventListener('scroll', onScroll, { passive: true })

  // Staggered content reveal after mount
  setTimeout(() => { contentVisible.value = true }, 300)
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
})
</script>

<style scoped>
.hero-gradient {
  background:
    radial-gradient(ellipse 600px 400px at 20% 50%, rgba(212, 168, 67, 0.1) 0%, transparent 70%),
    radial-gradient(ellipse 500px 500px at 80% 20%, rgba(6, 182, 212, 0.07) 0%, transparent 60%),
    radial-gradient(ellipse 400px 300px at 50% 80%, rgba(94, 106, 210, 0.05) 0%, transparent 70%),
    #050505;
  animation: heroGradientShift 20s cubic-bezier(0.45, 0, 0.55, 1) infinite alternate;
}

@keyframes heroGradientShift {
  0% { background-position: 0% 0%; }
  100% { background-position: 100% 100%; }
}

@media (prefers-reduced-motion: reduce) {
  .hero-gradient {
    animation: none;
  }
  video {
    display: none;
  }
}
</style>
