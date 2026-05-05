<template>
  <section
    class="testimonials relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-4 py-20 lg:px-12 lg:py-28"
    aria-label="Testimonials"
  >
    <div class="mx-auto max-w-7xl">
      <!-- Eyebrow -->
      <p
        class="mb-3 text-center text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        people who worked with me
      </p>

      <!-- Title -->
      <h2
        class="mb-3 text-center text-3xl font-bold text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
        style="font-family: 'Space Grotesk', sans-serif;"
      >
        What teammates say
        <span class="text-[var(--accent-gold,#D4A843)]">on LinkedIn.</span>
      </h2>

      <p
        class="mx-auto mb-12 max-w-2xl text-center text-sm text-[var(--fg-muted,#8A8F98)] lg:text-base"
        style="font-family: 'Inter', sans-serif; font-weight: 300;"
      >
        Public recommendations from people who've worked alongside me.
      </p>

      <!-- Loading skeleton — 3 placeholder cards -->
      <div
        v-if="isLoading"
        class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
        aria-hidden="true"
      >
        <div
          v-for="i in 3"
          :key="i"
          class="h-[22rem] animate-pulse rounded-xl border border-white/5 bg-white/[0.03]"
        />
      </div>

      <!-- Empty -->
      <div v-else-if="!testimonials.length" class="hidden"></div>

      <!-- Carousel -->
      <div
        v-else
        class="relative"
        @mouseenter="pause"
        @mouseleave="resume"
        @keydown="handleKey"
      >
        <!-- Slide viewport: 3 cards visible (lg), 2 (md), 1 (mobile) -->
        <div class="overflow-hidden px-1">
          <div
            class="flex gap-5 transition-transform duration-500 ease-out"
            :style="{ transform: `translateX(-${activeIdx * slidePctPerStep}%)` }"
          >
            <article
              v-for="(t, i) in testimonials"
              :key="t.id ?? i"
              class="card-slide flex flex-col rounded-xl border border-white/5 bg-white/[0.04] p-6 backdrop-blur-md md:p-7"
              role="group"
              :aria-roledescription="`testimonial ${i + 1} of ${testimonials.length}`"
            >
              <!-- Open quote glyph -->
              <span
                class="block text-4xl leading-none text-[var(--accent-gold,#D4A843)]/60 lg:text-5xl"
                aria-hidden="true"
                style="font-family: 'Playfair Display', serif;"
              >“</span>

              <!-- Quote text — clamped to fit card -->
              <div
                class="quote-clamp relative mt-3 mb-5 flex-1 text-sm leading-relaxed text-[var(--fg-primary,#EDEDEF)]/95 lg:text-[0.95rem]"
                style="font-family: 'Inter', sans-serif; font-weight: 300;"
              >
                <p class="quote-clamp-text">{{ flattenQuote(t) }}</p>
                <span class="quote-fade" aria-hidden="true"></span>
              </div>

              <!-- Footer: avatar + name + role -->
              <div class="flex items-center gap-3 border-t border-white/5 pt-4">
                <!-- Avatar -->
                <div class="shrink-0">
                  <img
                    v-if="t.client_photo && !brokenPhotos.has(t.id)"
                    :src="resolvePhoto(t.client_photo)"
                    :alt="t.client_name"
                    class="h-12 w-12 rounded-full border border-white/10 object-cover"
                    loading="lazy"
                    @error="brokenPhotos.add(t.id)"
                  />
                  <div
                    v-else
                    class="flex h-12 w-12 items-center justify-center rounded-full border border-white/10 text-sm font-semibold uppercase tracking-wider text-[var(--bg-deep,#050506)]"
                    :style="initialsAvatarStyle(t)"
                    style="font-family: 'Space Grotesk', sans-serif;"
                    aria-hidden="true"
                  >
                    {{ initials(t.client_name) }}
                  </div>
                </div>

                <!-- Name + role -->
                <div class="min-w-0 flex-1">
                  <p
                    class="truncate text-xs font-semibold uppercase tracking-[0.14em] text-[var(--fg-primary,#EDEDEF)] lg:text-[0.78rem]"
                    style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                  >
                    {{ t.client_name }}
                  </p>
                  <p
                    v-if="t.job_title"
                    class="mt-0.5 truncate text-[0.7rem] text-[var(--fg-muted,#8A8F98)] lg:text-xs"
                    style="font-family: 'Inter', sans-serif; font-weight: 300;"
                  >
                    {{ t.job_title }}
                  </p>
                  <p
                    v-if="t.company_name"
                    class="mt-0.5 truncate text-[0.6rem] uppercase tracking-[0.16em] text-[var(--accent-cyan,#06B6D4)]"
                    style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                  >
                    {{ t.company_name }}
                  </p>
                </div>
              </div>
            </article>
          </div>
        </div>

        <!-- Prev/Next arrows -->
        <button
          v-if="canSlide"
          type="button"
          class="nav-arrow nav-arrow-prev"
          :disabled="activeIdx === 0"
          aria-label="Previous testimonials"
          @click="prev"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button
          v-if="canSlide"
          type="button"
          class="nav-arrow nav-arrow-next"
          :disabled="activeIdx >= maxIdx"
          aria-label="Next testimonials"
          @click="next"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Dots — one per slide step -->
        <div
          v-if="canSlide"
          class="mt-8 flex items-center justify-center gap-3"
          role="tablist"
          aria-label="Testimonial slide nav"
        >
          <button
            v-for="i in maxIdx + 1"
            :key="i"
            role="tab"
            :aria-selected="activeIdx === i - 1 ? 'true' : 'false'"
            :aria-label="`Go to testimonial slide ${i}`"
            class="dot h-2 rounded-full transition-all duration-300"
            :class="activeIdx === i - 1
              ? 'w-8 bg-[var(--accent-gold,#D4A843)]'
              : 'w-2 bg-white/20 hover:bg-white/40'"
            @click="goTo(i - 1)"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted, watch, reactive } from 'vue'
import { useHomepageFeatured } from '@/composables/useHomepageFeatured'

const ROTATION_MS = 8000

const { data, isLoading } = useHomepageFeatured()

const testimonials = computed(() => {
  const arr = data.value?.featured_testimonials
  if (!Array.isArray(arr)) return []
  return arr
})

// Visible cards per breakpoint — synced via matchMedia
const visibleCards = ref(1)

function syncVisibleCards() {
  if (typeof window === 'undefined' || !window.matchMedia) {
    visibleCards.value = 3
    return
  }
  if (window.matchMedia('(min-width: 1024px)').matches) {
    visibleCards.value = 3
  } else if (window.matchMedia('(min-width: 768px)').matches) {
    visibleCards.value = 2
  } else {
    visibleCards.value = 1
  }
}

const maxIdx = computed(() =>
  Math.max(0, testimonials.value.length - visibleCards.value)
)
const canSlide = computed(() => maxIdx.value > 0)
const slidePctPerStep = computed(() => 100 / visibleCards.value)

const activeIdx = ref(0)

watch(
  [maxIdx],
  () => {
    if (activeIdx.value > maxIdx.value) activeIdx.value = maxIdx.value
  }
)

let timer = null
let isPaused = false
let mqlLg = null
let mqlMd = null

function startTimer() {
  if (timer) clearInterval(timer)
  if (!canSlide.value) return
  timer = setInterval(() => {
    if (isPaused) return
    activeIdx.value = activeIdx.value >= maxIdx.value ? 0 : activeIdx.value + 1
  }, ROTATION_MS)
}

function pause() { isPaused = true }
function resume() { isPaused = false }

function goTo(idx) {
  activeIdx.value = Math.max(0, Math.min(idx, maxIdx.value))
  startTimer()
}

function next() {
  if (activeIdx.value < maxIdx.value) goTo(activeIdx.value + 1)
}

function prev() {
  if (activeIdx.value > 0) goTo(activeIdx.value - 1)
}

function handleKey(e) {
  if (e.key === 'ArrowRight') {
    e.preventDefault()
    next()
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    prev()
  }
}

function flattenQuote(t) {
  if (!t || !t.testimonial_text) return ''
  return String(t.testimonial_text).replace(/\s+/g, ' ').trim()
}

function initials(name) {
  if (!name) return '?'
  const parts = String(name).trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const second = parts[parts.length - 1]?.[0] ?? ''
  return (first + (parts.length > 1 ? second : '')).toUpperCase()
}

const PALETTES = [
  ['#D4A843', '#06B6D4'],
  ['#06B6D4', '#5E6AD2'],
  ['#5E6AD2', '#D4A843'],
  ['#D4A843', '#5E6AD2'],
]
function paletteFor(name) {
  const str = String(name ?? '')
  let hash = 0
  for (let i = 0; i < str.length; i++) hash = (hash * 31 + str.charCodeAt(i)) >>> 0
  return PALETTES[hash % PALETTES.length]
}
function initialsAvatarStyle(t) {
  const [a, b] = paletteFor(t?.client_name)
  return { background: `linear-gradient(135deg, ${a} 0%, ${b} 100%)` }
}

// Resolve relative photo path against API origin so /uploads/... loads from backend
function resolvePhoto(p) {
  if (!p) return ''
  if (/^https?:\/\//i.test(p)) return p
  // Path is relative — browser will resolve against current origin.
  // Both prod and dev serve uploaded images from same origin as the SPA.
  return p
}

const brokenPhotos = reactive(new Set())

onMounted(() => {
  syncVisibleCards()
  if (typeof window !== 'undefined' && window.matchMedia) {
    mqlLg = window.matchMedia('(min-width: 1024px)')
    mqlMd = window.matchMedia('(min-width: 768px)')
    mqlLg.addEventListener?.('change', syncVisibleCards)
    mqlMd.addEventListener?.('change', syncVisibleCards)
  }
})

watch(
  () => testimonials.value.length,
  (len) => {
    if (len > 0) startTimer()
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  mqlLg?.removeEventListener?.('change', syncVisibleCards)
  mqlMd?.removeEventListener?.('change', syncVisibleCards)
})
</script>

<style scoped>
/* Card sizing — flex-basis matches visible card count.
   Use fixed widths via flex-basis percentages, accounting for gap. */
.card-slide {
  flex: 0 0 calc(100% - 0px);
  min-height: 22rem;
}
@media (min-width: 768px) {
  .card-slide { flex-basis: calc((100% - 1.25rem) / 2); }
}
@media (min-width: 1024px) {
  .card-slide { flex-basis: calc((100% - 2.5rem) / 3); }
}

/* Clamp long quote text with fade-out gradient at bottom */
.quote-clamp {
  max-height: 14rem;
  overflow: hidden;
}
.quote-clamp-text {
  display: -webkit-box;
  -webkit-line-clamp: 9;
  line-clamp: 9;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.quote-fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 2.5rem;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(5, 5, 6, 0.85) 100%
  );
}

.nav-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 2.75rem;
  height: 2.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(255, 255, 255, 0.04);
  color: var(--fg-primary, #EDEDEF);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  cursor: pointer;
  transition: background 200ms ease, border-color 200ms ease, transform 200ms ease, color 200ms ease, opacity 200ms ease;
  z-index: 5;
}
.nav-arrow svg { width: 1.25rem; height: 1.25rem; }
.nav-arrow:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.12);
  border-color: rgba(212, 168, 67, 0.5);
  color: var(--accent-gold, #D4A843);
}
.nav-arrow:active:not(:disabled) {
  transform: translateY(-50%) scale(0.96);
}
.nav-arrow:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}
.nav-arrow-prev { left: -1rem; }
.nav-arrow-next { right: -1rem; }
@media (min-width: 1024px) {
  .nav-arrow-prev { left: -3rem; }
  .nav-arrow-next { right: -3rem; }
}

@media (prefers-reduced-motion: reduce) {
  .dot,
  .nav-arrow { transition: none; }
  .card-slide,
  .quote-clamp { transition: none; }
}
</style>
