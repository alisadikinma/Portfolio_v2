<template>
  <section
    class="testimonials relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-4 py-20 lg:px-12 lg:py-28"
    aria-label="Testimonials"
  >
    <div class="mx-auto max-w-5xl">
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
        class="glass-card mx-auto h-[20rem] w-full max-w-3xl animate-pulse rounded-xl border border-white/5 bg-white/[0.03] lg:h-[22rem]"
        aria-hidden="true"
      ></div>

      <!-- Empty state -->
      <div v-else-if="!testimonials.length" class="hidden"></div>

      <!-- Carousel -->
      <div
        v-else
        class="relative mx-auto max-w-3xl"
        @mouseenter="pause"
        @mouseleave="resume"
      >
        <!-- Slide -->
        <Transition name="quote-fade" mode="out-in">
          <article
            :key="active.id"
            class="relative rounded-xl border border-white/5 bg-white/[0.04] p-7 backdrop-blur-md md:p-10"
            role="group"
            :aria-roledescription="`testimonial ${activeIdx + 1} of ${testimonials.length}`"
          >
            <!-- Open quote glyph -->
            <span
              class="block text-5xl leading-none text-[var(--accent-gold,#D4A843)]/60 lg:text-6xl"
              aria-hidden="true"
              style="font-family: 'Playfair Display', serif;"
            >“</span>

            <!-- Quote text — preserve double-line breaks as paragraph spacing -->
            <div
              class="mt-4 mb-6 space-y-4 text-base leading-relaxed text-[var(--fg-primary,#EDEDEF)]/95 lg:text-[1.05rem]"
              style="font-family: 'Inter', sans-serif; font-weight: 300;"
            >
              <p
                v-for="(para, i) in quoteParagraphs(active)"
                :key="i"
              >{{ para }}</p>
            </div>

            <!-- Footer: avatar + client name + role -->
            <div class="flex items-center gap-4 border-t border-white/5 pt-5">
              <!-- Avatar (photo or initials fallback) -->
              <div class="shrink-0">
                <img
                  v-if="active.client_photo"
                  :src="active.client_photo"
                  :alt="active.client_name"
                  class="h-14 w-14 rounded-full border border-white/10 object-cover lg:h-16 lg:w-16"
                  loading="lazy"
                  @error="onPhotoError($event)"
                />
                <div
                  v-else
                  class="flex h-14 w-14 items-center justify-center rounded-full border border-white/10 text-base font-semibold uppercase tracking-wider text-[var(--bg-deep,#050506)] lg:h-16 lg:w-16 lg:text-lg"
                  :style="initialsAvatarStyle(active)"
                  style="font-family: 'Space Grotesk', sans-serif;"
                  aria-hidden="true"
                >
                  {{ initials(active.client_name) }}
                </div>
              </div>

              <!-- Name + role -->
              <div class="min-w-0 flex-1">
                <p
                  class="truncate text-sm font-semibold uppercase tracking-[0.16em] text-[var(--fg-primary,#EDEDEF)] lg:text-[0.95rem]"
                  style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                >
                  {{ active.client_name }}
                </p>
                <p
                  v-if="active.job_title"
                  class="mt-1 truncate text-xs text-[var(--fg-muted,#8A8F98)] lg:text-sm"
                  style="font-family: 'Inter', sans-serif; font-weight: 300;"
                >
                  {{ active.job_title }}
                </p>
                <p
                  v-if="active.company_name"
                  class="mt-0.5 truncate text-[0.65rem] uppercase tracking-[0.18em] text-[var(--accent-cyan,#06B6D4)] lg:text-xs"
                  style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                >
                  {{ active.company_name }}
                </p>
              </div>
            </div>
          </article>
        </Transition>

        <!-- Prev/Next arrow buttons -->
        <button
          v-if="testimonials.length > 1"
          type="button"
          class="nav-arrow nav-arrow-prev"
          aria-label="Previous testimonial"
          @click="prev"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button
          v-if="testimonials.length > 1"
          type="button"
          class="nav-arrow nav-arrow-next"
          aria-label="Next testimonial"
          @click="next"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Dots nav -->
        <div
          v-if="testimonials.length > 1"
          class="mt-8 flex items-center justify-center gap-3"
          role="tablist"
          aria-label="Testimonial slide nav"
          @keydown="handleKey"
        >
          <button
            v-for="(t, i) in testimonials"
            :key="t.id ?? i"
            role="tab"
            :aria-selected="activeIdx === i ? 'true' : 'false'"
            :aria-label="`Go to testimonial ${i + 1}: ${t.client_name}`"
            class="dot h-2 rounded-full transition-all duration-300"
            :class="activeIdx === i
              ? 'w-8 bg-[var(--accent-gold,#D4A843)]'
              : 'w-2 bg-white/20 hover:bg-white/40'"
            @click="goTo(i)"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, onBeforeUnmount, watch } from 'vue'
import { useHomepageFeatured } from '@/composables/useHomepageFeatured'

const ROTATION_MS = 8000

const { data, isLoading } = useHomepageFeatured()

const testimonials = computed(() => {
  const arr = data.value?.featured_testimonials
  if (!Array.isArray(arr)) return []
  return arr
})

const activeIdx = ref(0)
const active = computed(
  () => testimonials.value[activeIdx.value] ?? null
)

let timer = null
let isPaused = false

function startTimer() {
  if (timer) clearInterval(timer)
  if (testimonials.value.length <= 1) return
  timer = setInterval(() => {
    if (isPaused) return
    activeIdx.value = (activeIdx.value + 1) % testimonials.value.length
  }, ROTATION_MS)
}

function pause() { isPaused = true }
function resume() { isPaused = false }

function goTo(idx) {
  activeIdx.value = idx
  startTimer()
}

function next() {
  goTo((activeIdx.value + 1) % testimonials.value.length)
}

function prev() {
  goTo((activeIdx.value - 1 + testimonials.value.length) % testimonials.value.length)
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

// Stable per-name gradient using simple hash → palette index
const PALETTES = [
  ['#D4A843', '#06B6D4'], // gold → cyan
  ['#06B6D4', '#5E6AD2'], // cyan → indigo
  ['#5E6AD2', '#D4A843'], // indigo → gold
  ['#D4A843', '#5E6AD2'], // gold → indigo
]

function paletteFor(name) {
  const str = String(name ?? '')
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    hash = (hash * 31 + str.charCodeAt(i)) >>> 0
  }
  return PALETTES[hash % PALETTES.length]
}

function initialsAvatarStyle(t) {
  const [a, b] = paletteFor(t?.client_name)
  return {
    background: `linear-gradient(135deg, ${a} 0%, ${b} 100%)`,
  }
}

function onPhotoError(e) {
  // Photo URL broke — hide <img> so initials fallback rerenders next tick.
  // Mutate the testimonial object so v-if/v-else flips.
  if (active.value) active.value.client_photo = null
  if (e?.target) e.target.style.display = 'none'
}

watch(
  () => testimonials.value.length,
  (len) => {
    if (len > 0) startTimer()
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.quote-fade-enter-active { transition: opacity 350ms ease-out, transform 350ms ease-out; }
.quote-fade-leave-active { transition: opacity 200ms ease-in, transform 200ms ease-in; }
.quote-fade-enter-from { opacity: 0; transform: translateY(10px); }
.quote-fade-leave-to { opacity: 0; transform: translateY(-6px); }

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
  transition: background 200ms ease, border-color 200ms ease, transform 200ms ease, color 200ms ease;
  z-index: 5;
}
.nav-arrow svg {
  width: 1.25rem;
  height: 1.25rem;
}
.nav-arrow:hover {
  background: rgba(212, 168, 67, 0.12);
  border-color: rgba(212, 168, 67, 0.5);
  color: var(--accent-gold, #D4A843);
}
.nav-arrow:active {
  transform: translateY(-50%) scale(0.96);
}
.nav-arrow-prev { left: -1rem; }
.nav-arrow-next { right: -1rem; }
@media (min-width: 1024px) {
  .nav-arrow-prev { left: -3.5rem; }
  .nav-arrow-next { right: -3.5rem; }
}

@media (prefers-reduced-motion: reduce) {
  .quote-fade-enter-active,
  .quote-fade-leave-active { transition: none; }
  .quote-fade-enter-from,
  .quote-fade-leave-to { transform: none; }
  .dot,
  .nav-arrow { transition: none; }
}
</style>
