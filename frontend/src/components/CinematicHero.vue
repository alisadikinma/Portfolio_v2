<template>
  <section ref="heroRef" class="relative min-h-[100dvh] w-full overflow-hidden flex items-center justify-center">

    <!-- Background: dark gradient (warrior videos are the main visual now) -->
    <div class="absolute inset-0 hero-gradient" />

    <!-- Deep vignette overlay -->
    <div class="absolute inset-0 bg-bg-deep/40" />
    <div class="absolute inset-0 pointer-events-none" style="box-shadow: inset 0 0 200px 80px #050505;" />

    <!-- ═══ Warrior Videos ═══ -->
    <div class="warrior-container">
      <video
        class="warrior-video"
        :class="{ 'is-active': activeWarrior === 'left' }"
        autoplay loop muted playsinline preload="auto"
        src="/videos/warrior-left.mp4"
      />
      <video
        class="warrior-video"
        :class="{ 'is-active': activeWarrior === 'front' }"
        autoplay loop muted playsinline preload="auto"
        src="/videos/warrior-front.mp4"
      />
      <video
        class="warrior-video"
        :class="{ 'is-active': activeWarrior === 'right' }"
        autoplay loop muted playsinline preload="auto"
        src="/videos/warrior-right.mp4"
      />
    </div>

    <!-- Dark overlay behind text area -->
    <div class="absolute inset-0 z-[6] hero-text-backdrop" />

    <!-- Content overlay — z-10, above warrior -->
    <div class="relative z-10 text-center px-4 w-full max-w-6xl mx-auto">

      <!-- Name + Logo -->
      <h1
        class="font-display font-bold tracking-[-0.04em] leading-none text-white mb-4 hero-text-glow flex items-center justify-center gap-3 sm:gap-4 md:gap-5"
        :class="[
          contentVisible ? 'opacity-100' : 'opacity-0',
          'text-4xl sm:text-6xl md:text-7xl lg:text-8xl'
        ]"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 100ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 100ms;' : 'transform:translateY(24px);'"
      >
        <img src="/alisadikin-logo.png" alt="Ali Sadikin Ma logo" class="h-[1em] w-auto drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]" />
        ALI SADIKIN MA
      </h1>

      <!-- Eyebrow Tag -->
      <div
        class="flex justify-center mb-6"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 200ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 200ms;' : 'transform:translateY(16px);'"
      >
        <span class="eyebrow-tag text-accent-gold border-accent-gold/30 bg-black/40 backdrop-blur-sm hero-text-glow">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          AI Generalist Expert
        </span>
      </div>

      <!-- Subtitle -->
      <p
        class="font-sans text-lg sm:text-xl md:text-2xl text-white font-light tracking-wide mb-4 hero-text-glow"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 300ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 300ms;' : 'transform:translateY(16px);'"
      >
        Bridging AI &amp; Business Outcomes
      </p>

      <!-- URL Branding -->
      <p
        class="font-mono text-sm sm:text-base text-accent-gold tracking-[0.2em] mb-12 hero-text-glow"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 320ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 320ms;' : 'transform:translateY(16px);'"
      >
        alisadikinma.com
      </p>

      <!-- Achievement Badges -->
      <div
        class="flex flex-wrap justify-center gap-3 md:gap-4"
        :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        :style="contentVisible ? 'transform:translateY(0);transition:opacity 900ms cubic-bezier(0.32,0.72,0,1) 350ms,transform 900ms cubic-bezier(0.32,0.72,0,1) 350ms;' : 'transform:translateY(16px);'"
      >
        <span class="eyebrow-tag text-accent-gold border-accent-gold/30 bg-black/40 backdrop-blur-sm hero-text-glow">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          #1 Champion
        </span>
        <span class="eyebrow-tag text-accent-cyan border-accent-cyan/30 bg-black/40 backdrop-blur-sm hero-text-glow">
          16 Countries
        </span>
        <span class="eyebrow-tag text-accent-indigo border-accent-indigo/30 bg-black/40 backdrop-blur-sm hero-text-glow">
          56+ Projects
        </span>
      </div>
    </div>

    <!-- Spark particles container -->
    <div ref="sparksRef" class="absolute inset-0 z-[8] pointer-events-none overflow-hidden" />

    <!-- Scroll indicator -->
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
import { useCursorSparks } from '@/composables/useCursorSparks'

const heroRef = ref(null)
const sparksRef = ref(null)
const contentVisible = ref(false)

// ── Mouse Tracking (Warrior Video Swap) ──
const activeWarrior = ref('front')

function onMouseMove(e) {
  const x = e.clientX / window.innerWidth
  if (x < 0.35) {
    activeWarrior.value = 'left'
  } else if (x > 0.65) {
    activeWarrior.value = 'right'
  } else {
    activeWarrior.value = 'front'
  }
}

// ── Cursor Sparks ──
const { init: initSparks, destroy: destroySparks } = useCursorSparks()

// ── Lifecycle ──
onMounted(() => {
  window.addEventListener('mousemove', onMouseMove, { passive: true })

  if (sparksRef.value) {
    initSparks(sparksRef.value)
  }

  setTimeout(() => { contentVisible.value = true }, 300)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  destroySparks()
})
</script>

<style scoped>
.hero-gradient {
  background:
    radial-gradient(ellipse 800px 500px at 30% 70%, rgba(212, 168, 67, 0.08) 0%, transparent 70%),
    radial-gradient(ellipse 600px 600px at 70% 30%, rgba(6, 182, 212, 0.06) 0%, transparent 60%),
    radial-gradient(ellipse 500px 400px at 50% 90%, rgba(94, 106, 210, 0.04) 0%, transparent 70%),
    #050505;
}

/* ── Warrior Video Layer ── */
.warrior-container {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  overflow: hidden;
}

.warrior-video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 20%;
  opacity: 0;
  transition: opacity 0.5s ease;
  will-change: opacity;
}

.warrior-video.is-active {
  opacity: 1;
}

/* ── Dark backdrop behind text area ── */
.hero-text-backdrop {
  background:
    radial-gradient(ellipse 80% 50% at 50% 40%, rgba(5, 5, 5, 0.75) 0%, transparent 70%),
    linear-gradient(to bottom, rgba(5, 5, 5, 0.6) 0%, transparent 45%, transparent 80%, rgba(5, 5, 5, 0.5) 100%);
}

/* ── Strong text readability over video ── */
.hero-text-glow {
  text-shadow:
    0 2px 8px rgba(5, 5, 5, 0.9),
    0 4px 24px rgba(5, 5, 5, 0.7),
    0 8px 48px rgba(5, 5, 5, 0.5);
}

@media (prefers-reduced-motion: reduce) {
  video {
    display: none;
  }
}
</style>
