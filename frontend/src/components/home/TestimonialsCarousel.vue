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

      <!-- Loading skeleton -->
      <div
        v-if="isLoading"
        class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
        aria-hidden="true"
      >
        <div
          v-for="i in 3"
          :key="i"
          class="h-[24rem] animate-pulse rounded-xl border border-white/5 bg-white/[0.03]"
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
        <div class="testi-rail overflow-hidden px-1 max-md:snap-x max-md:snap-mandatory max-md:overflow-x-auto">
          <div
            class="flex gap-5 md:transition-transform md:duration-500 md:ease-out"
            :style="visibleCards === 1 ? {} : { transform: `translateX(calc(${activeIdx} * (100% + 1.25rem) / ${visibleCards} * -1))` }"
          >
            <component
              :is="t.linkedin_url ? 'a' : 'article'"
              v-for="(t, i) in testimonials"
              :key="t.id ?? i"
              :href="t.linkedin_url || null"
              :target="t.linkedin_url ? '_blank' : null"
              :rel="t.linkedin_url ? 'noopener noreferrer' : null"
              class="card-slide group flex snap-start flex-col rounded-xl border border-white/5 bg-white/[0.04] p-6 backdrop-blur-md transition-all duration-300 md:p-7"
              :class="t.linkedin_url
                ? 'cursor-pointer hover:-translate-y-1 hover:border-[var(--accent-gold,#D4A843)]/40 hover:bg-white/[0.06]'
                : ''"
              role="group"
              :aria-roledescription="`testimonial ${i + 1} of ${testimonials.length}`"
              :aria-label="t.linkedin_url ? `View ${t.client_name} on LinkedIn` : null"
            >
              <!-- Open quote glyph + LinkedIn icon (top-right when linked) -->
              <div class="flex items-start justify-between">
                <span
                  class="block text-4xl leading-none text-[var(--accent-gold,#D4A843)]/60 lg:text-5xl"
                  aria-hidden="true"
                  style="font-family: 'Playfair Display', serif;"
                >“</span>
                <span
                  v-if="t.linkedin_url"
                  class="opacity-40 transition-opacity duration-300 group-hover:opacity-90"
                  aria-hidden="true"
                >
                  <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-[var(--accent-cyan,#06B6D4)]">
                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                  </svg>
                </span>
              </div>

              <!-- Quote text — clamped -->
              <div
                class="quote-clamp relative mt-3 mb-4 text-sm leading-relaxed text-[var(--fg-primary,#EDEDEF)]/95 lg:text-[0.95rem]"
                style="font-family: 'Inter', sans-serif; font-weight: 300;"
              >
                <p class="quote-clamp-text">{{ flattenQuote(t) }}</p>
                <span class="quote-fade" aria-hidden="true"></span>
              </div>

              <!-- Read more — opens modal, NEVER follows the card link -->
              <button
                type="button"
                class="mb-5 inline-flex w-fit items-center gap-1.5 self-start rounded-md border border-white/10 bg-white/[0.03] px-3 py-1.5 text-[0.7rem] font-medium uppercase tracking-[0.16em] text-[var(--fg-muted,#8A8F98)] transition-colors hover:border-[var(--accent-gold,#D4A843)]/40 hover:bg-white/[0.06] hover:text-[var(--accent-gold,#D4A843)]"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                :aria-label="`Read full quote from ${t.client_name}`"
                @click.stop.prevent="openModal(t)"
              >
                Read full quote
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Footer: avatar + name + role -->
              <div class="mt-auto flex items-center gap-3 border-t border-white/5 pt-4">
                <div class="shrink-0">
                  <img
                    v-if="t.client_photo && !brokenPhotos.has(t.id)"
                    :src="t.client_photo"
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

                <div class="min-w-0 flex-1">
                  <p
                    class="truncate text-xs font-semibold uppercase tracking-[0.14em] text-[var(--fg-primary,#EDEDEF)] transition-colors lg:text-[0.78rem]"
                    :class="t.linkedin_url ? 'group-hover:text-[var(--accent-gold,#D4A843)]' : ''"
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
            </component>
          </div>
        </div>

        <!-- Prev/Next arrows -->
        <button
          v-if="canSlide"
          type="button"
          class="nav-arrow nav-arrow-prev max-md:hidden"
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
          class="nav-arrow nav-arrow-next max-md:hidden"
          :disabled="activeIdx >= maxIdx"
          aria-label="Next testimonials"
          @click="next"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Dots -->
        <div
          v-if="canSlide"
          class="mt-8 flex items-center justify-center gap-3 max-md:hidden"
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

    <!-- Full quote modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="modalTestimonial"
          class="modal-backdrop"
          role="dialog"
          aria-modal="true"
          :aria-label="`Full quote from ${modalTestimonial.client_name}`"
          @click.self="closeModal"
        >
          <div class="modal-card">
            <!-- Close -->
            <button
              type="button"
              class="modal-close"
              aria-label="Close"
              @click="closeModal"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>

            <!-- Open quote -->
            <span
              class="block text-5xl leading-none text-[var(--accent-gold,#D4A843)]/60 lg:text-6xl"
              aria-hidden="true"
              style="font-family: 'Playfair Display', serif;"
            >“</span>

            <!-- Full quote text -->
            <div
              class="mt-4 mb-6 space-y-4 text-base leading-relaxed text-[var(--fg-primary,#EDEDEF)]/95 lg:text-[1.05rem]"
              style="font-family: 'Inter', sans-serif; font-weight: 300;"
            >
              <p
                v-for="(para, i) in quoteParagraphs(modalTestimonial)"
                :key="i"
              >{{ para }}</p>
            </div>

            <!-- Footer -->
            <div class="flex items-center gap-4 border-t border-white/5 pt-5">
              <div class="shrink-0">
                <img
                  v-if="modalTestimonial.client_photo && !brokenPhotos.has(modalTestimonial.id)"
                  :src="modalTestimonial.client_photo"
                  :alt="modalTestimonial.client_name"
                  class="h-14 w-14 rounded-full border border-white/10 object-cover lg:h-16 lg:w-16"
                  loading="lazy"
                  @error="brokenPhotos.add(modalTestimonial.id)"
                />
                <div
                  v-else
                  class="flex h-14 w-14 items-center justify-center rounded-full border border-white/10 text-base font-semibold uppercase tracking-wider text-[var(--bg-deep,#050506)] lg:h-16 lg:w-16 lg:text-lg"
                  :style="initialsAvatarStyle(modalTestimonial)"
                  style="font-family: 'Space Grotesk', sans-serif;"
                  aria-hidden="true"
                >
                  {{ initials(modalTestimonial.client_name) }}
                </div>
              </div>

              <div class="min-w-0 flex-1">
                <p
                  class="truncate text-sm font-semibold uppercase tracking-[0.16em] text-[var(--fg-primary,#EDEDEF)]"
                  style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                >
                  {{ modalTestimonial.client_name }}
                </p>
                <p
                  v-if="modalTestimonial.job_title"
                  class="mt-1 truncate text-xs text-[var(--fg-muted,#8A8F98)] lg:text-sm"
                  style="font-family: 'Inter', sans-serif; font-weight: 300;"
                >
                  {{ modalTestimonial.job_title }}
                </p>
                <p
                  v-if="modalTestimonial.company_name"
                  class="mt-0.5 truncate text-[0.65rem] uppercase tracking-[0.18em] text-[var(--accent-cyan,#06B6D4)]"
                  style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                >
                  {{ modalTestimonial.company_name }}
                </p>
              </div>

              <a
                v-if="modalTestimonial.linkedin_url"
                :href="modalTestimonial.linkedin_url"
                target="_blank"
                rel="noopener noreferrer"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-[var(--accent-cyan,#06B6D4)]/40 bg-white/5 px-3 py-1.5 text-[0.65rem] font-medium uppercase tracking-[0.18em] text-[var(--fg-primary,#EDEDEF)]/90 transition-colors hover:border-[var(--accent-cyan,#06B6D4)] hover:text-[var(--accent-cyan,#06B6D4)] lg:text-xs"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >
                LinkedIn ↗
              </a>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
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
// Per-step distance is gap-aware: one card + one gap = (100% + gap) / visibleCards.
// (gap-5 = 1.25rem; kept in sync with the track's `gap-5` class in the template.)

const activeIdx = ref(0)

watch([maxIdx], () => {
  if (activeIdx.value > maxIdx.value) activeIdx.value = maxIdx.value
})

// Crossing the mobile↔desktop breakpoint flips between native scroll-snap
// (no timer) and the JS carousel (timer) — re-evaluate on every change.
watch(visibleCards, () => {
  if (visibleCards.value === 1) activeIdx.value = 0
  startTimer()
})

let timer = null
let isPaused = false
let mqlLg = null
let mqlMd = null

function startTimer() {
  if (timer) clearInterval(timer)
  // Mobile uses native CSS scroll-snap (1 card per view) — no JS auto-rotate.
  if (visibleCards.value === 1) return
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
function next() { if (activeIdx.value < maxIdx.value) goTo(activeIdx.value + 1) }
function prev() { if (activeIdx.value > 0) goTo(activeIdx.value - 1) }

function handleKey(e) {
  if (modalTestimonial.value && e.key === 'Escape') {
    e.preventDefault()
    closeModal()
    return
  }
  if (e.key === 'ArrowRight') { e.preventDefault(); next() }
  else if (e.key === 'ArrowLeft') { e.preventDefault(); prev() }
}

function flattenQuote(t) {
  if (!t || !t.testimonial_text) return ''
  return String(t.testimonial_text).replace(/\s+/g, ' ').trim()
}

function quoteParagraphs(t) {
  if (!t || !t.testimonial_text) return []
  return String(t.testimonial_text)
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter(Boolean)
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

const brokenPhotos = reactive(new Set())

// Modal state
const modalTestimonial = ref(null)
function openModal(t) {
  modalTestimonial.value = t
  isPaused = true
  if (typeof document !== 'undefined') {
    document.body.style.overflow = 'hidden'
  }
}
function closeModal() {
  modalTestimonial.value = null
  isPaused = false
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
}

function onGlobalKey(e) {
  if (e.key === 'Escape' && modalTestimonial.value) closeModal()
}

onMounted(() => {
  syncVisibleCards()
  if (typeof window !== 'undefined' && window.matchMedia) {
    mqlLg = window.matchMedia('(min-width: 1024px)')
    mqlMd = window.matchMedia('(min-width: 768px)')
    mqlLg.addEventListener?.('change', syncVisibleCards)
    mqlMd.addEventListener?.('change', syncVisibleCards)
  }
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onGlobalKey)
  }
})

watch(
  () => testimonials.value.length,
  (len) => { if (len > 0) startTimer() },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  mqlLg?.removeEventListener?.('change', syncVisibleCards)
  mqlMd?.removeEventListener?.('change', syncVisibleCards)
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onGlobalKey)
  }
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<style scoped>
/* Mobile: native scroll-snap rail — show ~85% of one card so the next peeks. */
.card-slide {
  flex: 0 0 85%;
  scroll-snap-align: start;
  min-height: 24rem;
  text-decoration: none;
  color: inherit;
}
/* Hide the mobile rail scrollbar (snap-scroll affords the gesture). */
.testi-rail::-webkit-scrollbar { display: none; }
.testi-rail { -ms-overflow-style: none; scrollbar-width: none; }
@media (min-width: 768px) {
  .card-slide { flex-basis: calc((100% - 1.25rem) / 2); }
}
@media (min-width: 1024px) {
  .card-slide { flex-basis: calc((100% - 2.5rem) / 3); }
}

.quote-clamp {
  max-height: 12rem;
  overflow: hidden;
  flex: 1 1 auto;
}
.quote-clamp-text {
  display: -webkit-box;
  -webkit-line-clamp: 8;
  line-clamp: 8;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin: 0;
}
.quote-fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 2.5rem;
  pointer-events: none;
  background: linear-gradient(to bottom, transparent 0%, rgba(5,5,6,0.85) 100%);
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
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.04);
  color: var(--fg-primary, #EDEDEF);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  cursor: pointer;
  transition: background 200ms ease, border-color 200ms ease, transform 200ms ease, color 200ms ease, opacity 200ms ease;
  z-index: 5;
}
.nav-arrow svg { width: 1.25rem; height: 1.25rem; }
.nav-arrow:hover:not(:disabled) {
  background: rgba(212,168,67,0.12);
  border-color: rgba(212,168,67,0.5);
  color: var(--accent-gold, #D4A843);
}
.nav-arrow:active:not(:disabled) { transform: translateY(-50%) scale(0.96); }
.nav-arrow:disabled { opacity: 0.3; cursor: not-allowed; }
.nav-arrow-prev { left: -1rem; }
.nav-arrow-next { right: -1rem; }
@media (min-width: 1024px) {
  .nav-arrow-prev { left: -3rem; }
  .nav-arrow-next { right: -3rem; }
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(5,5,6,0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.modal-card {
  position: relative;
  width: 100%;
  max-width: 42rem;
  max-height: calc(100vh - 4rem);
  overflow-y: auto;
  padding: 2rem;
  border-radius: 1rem;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(12,12,15,0.92);
}
@media (min-width: 1024px) {
  .modal-card { padding: 2.5rem; }
}
.modal-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 2.25rem;
  height: 2.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.04);
  color: var(--fg-muted, #8A8F98);
  cursor: pointer;
  transition: background 180ms ease, border-color 180ms ease, color 180ms ease;
  z-index: 1;
}
.modal-close svg { width: 1.1rem; height: 1.1rem; }
.modal-close:hover {
  border-color: rgba(212,168,67,0.5);
  background: rgba(212,168,67,0.12);
  color: var(--accent-gold, #D4A843);
}

.modal-enter-active, .modal-leave-active {
  transition: opacity 200ms ease;
}
.modal-enter-active .modal-card, .modal-leave-active .modal-card {
  transition: transform 220ms ease, opacity 200ms ease;
}
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-card, .modal-leave-to .modal-card {
  transform: translateY(12px) scale(0.98);
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .dot, .nav-arrow, .card-slide, .modal-enter-active, .modal-leave-active,
  .modal-enter-active .modal-card, .modal-leave-active .modal-card {
    transition: none;
  }
}
</style>
