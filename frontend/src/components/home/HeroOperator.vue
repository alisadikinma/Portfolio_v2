<template>
  <section
    class="hero-operator relative flex min-h-screen w-full flex-col justify-end overflow-hidden bg-[var(--bg-deep,#050506)] px-6 pb-20 pt-28 lg:px-20 lg:pb-28"
    aria-label="Ali Sadikin Ma — AI Generalist"
  >
    <!-- Background: person-forward montage video (poster fallback for reduced-motion / slow conn) -->
    <video
      v-if="!reducedMotion"
      class="absolute inset-0 h-full w-full object-cover"
      autoplay
      loop
      muted
      playsinline
      preload="metadata"
      :poster="posterSrc || undefined"
      aria-hidden="true"
    >
      <source v-if="webmSrc" :src="webmSrc" type="video/webm" />
      <source v-if="mp4Src" :src="mp4Src" type="video/mp4" />
    </video>
    <img
      v-else-if="posterSrc"
      :src="posterSrc"
      alt=""
      class="absolute inset-0 h-full w-full object-cover"
      aria-hidden="true"
    />

    <!-- Cinematic warm/cyan glow + legibility overlay -->
    <div class="hero-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>
    <div class="hero-overlay pointer-events-none absolute inset-0" aria-hidden="true"></div>

    <!-- Content (lower-left) -->
    <div class="relative z-10 mx-auto w-full max-w-7xl">
      <p
        class="mb-4 text-[0.72rem] uppercase tracking-[0.3em] text-[var(--accent-gold,#D4A843)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        AI Generalist · since 2008
      </p>

      <h1
        class="mb-6 text-5xl font-bold leading-[0.95] tracking-tight text-[var(--fg-primary,#EDEDEF)] sm:text-6xl lg:text-[6.5rem]"
        style="font-family: 'Space Grotesk', sans-serif;"
      >
        ALI SADIKIN MA
      </h1>

      <p
        class="mb-8 max-w-3xl text-lg leading-relaxed text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
        style="font-family: 'Inter', sans-serif; font-weight: 300;"
      >
        I build AI that turns frontier models into
        <span class="text-[var(--accent-gold,#D4A843)]">real business outcomes</span>
        — not slide decks. Seventeen years, from factory floors to
        <span class="text-[var(--accent-gold,#D4A843)]">global stages</span>
        — one operator.
        <span class="text-[var(--accent-gold,#D4A843)]">Now teaching what I build.</span>
      </p>

      <!-- CTAs -->
      <div class="mb-12 flex flex-wrap items-center gap-4">
        <a
          :href="linkedinUrl"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center rounded-[10px] bg-[var(--accent-gold,#D4A843)] px-7 py-4 text-[0.95rem] font-medium text-[#0A0A0C] transition-transform duration-200 hover:-translate-y-0.5"
          style="font-family: 'Inter', sans-serif;"
        >
          Follow the build
        </a>
        <button
          type="button"
          @click="scrollToAnchor('join-the-build')"
          class="inline-flex items-center rounded-[10px] border border-[var(--fg-muted,#8A8F98)]/55 px-7 py-4 text-[0.95rem] font-medium text-[var(--fg-primary,#EDEDEF)] backdrop-blur transition-colors duration-200 hover:border-[var(--accent-gold,#D4A843)] hover:bg-white/5"
          style="font-family: 'Inter', sans-serif;"
        >
          Learn AI with me
        </button>
        <RouterLink
          to="/blog"
          class="inline-flex items-center px-2 py-4 text-[0.95rem] font-medium text-[var(--fg-primary,#EDEDEF)] transition-colors duration-200 hover:text-[var(--accent-gold,#D4A843)]"
          style="font-family: 'Inter', sans-serif;"
        >
          Read the blog →
        </RouterLink>
      </div>

      <!-- Stat triad -->
      <div class="flex flex-wrap gap-x-14 gap-y-6">
        <div v-for="stat in heroStats" :key="stat.label">
          <div
            class="text-4xl font-bold tracking-tight lg:text-[2.4rem]"
            :class="stat.gold ? 'text-[var(--accent-gold,#D4A843)]' : 'text-[var(--fg-primary,#EDEDEF)]'"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            {{ stat.value }}
          </div>
          <div
            class="mt-1.5 text-[0.7rem] uppercase tracking-[0.15em] text-[var(--fg-muted,#8A8F98)]"
            style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
          >
            {{ stat.label }}
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useHomepageFeatured } from '@/composables/useHomepageFeatured'

const linkedinUrl = 'https://www.linkedin.com/in/alisadikinma/'

// Public-folder media. Bound as runtime URLs so Vite does NOT resolve them at
// build time; missing files degrade gracefully (sources are v-if'd, poster
// optional → falls back to the dark section bg + cinematic glow).
//
// Hero media — "Operator at the console" (Concept A). Keyframe generated from the
// face ref (indusia-image-gen), then animated into an 8s seamless loop via VEO
// (indusia-video-gen, image-to-video). webm primary (1.95MB) / mp4 fallback (2.44MB);
// the poster (frame 0) shows immediately + serves the reduced-motion / slow-conn path.
const posterSrc = '/videos/hero-poster.jpg'
const webmSrc = '/videos/hero-loop.webm'
const mp4Src = '/videos/hero-loop.mp4'

const { data } = useHomepageFeatured()

const heroStats = computed(() => {
  const s = data.value?.stats ?? {}
  return [
    { value: String(s.years_experience ?? 17), label: 'Years Building', gold: false },
    { value: `${s.projects_count ?? 56}+`, label: 'Products Shipped', gold: false },
    { value: '#1', label: 'Global AI Demo Day 2026', gold: true },
  ]
})

// Reduced-motion: serve static poster instead of autoplay video
const reducedMotion = ref(false)
let mq
function syncMotion(e) { reducedMotion.value = e.matches }
onMounted(() => {
  mq = window.matchMedia('(prefers-reduced-motion: reduce)')
  reducedMotion.value = mq.matches
  mq.addEventListener?.('change', syncMotion)
})
onUnmounted(() => mq?.removeEventListener?.('change', syncMotion))

// "Learn AI with me" scrolls to the newsletter waitlist (#join-the-build)
// until a dedicated /courses page ships. Reduced-motion → instant jump.
function scrollToAnchor(id) {
  const el = id && document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: reducedMotion.value ? 'auto' : 'smooth', block: 'start' })
}
</script>

<style scoped>
.hero-glow {
  background:
    radial-gradient(60% 50% at 70% 30%, rgba(212, 168, 67, 0.22), transparent 70%),
    radial-gradient(45% 40% at 30% 25%, rgba(6, 182, 212, 0.10), transparent 70%);
}
.hero-overlay {
  background: linear-gradient(
    to top,
    rgba(5, 5, 6, 0.94) 0%,
    rgba(5, 5, 6, 0.55) 45%,
    rgba(5, 5, 6, 0.2) 100%
  );
}
</style>
