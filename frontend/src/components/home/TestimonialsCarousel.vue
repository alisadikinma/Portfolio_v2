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
        Public recommendations — every quote linked back to its
        author's LinkedIn profile.
      </p>

      <!-- Loading skeleton -->
      <div
        v-if="isLoading"
        class="glass-card mx-auto h-[18rem] w-full max-w-3xl animate-pulse rounded-xl border border-white/5 bg-white/[0.03] lg:h-[20rem]"
        aria-hidden="true"
      ></div>

      <!-- Empty state — render nothing if no testimonials (matches Phase 11 silent-absence pattern) -->
      <div v-else-if="!testimonials.length" class="hidden"></div>

      <!-- Carousel -->
      <div
        v-else
        class="relative mx-auto max-w-3xl"
        @mouseenter="pause"
        @mouseleave="resume"
      >
        <Transition name="quote-fade" mode="out-in">
          <article
            :key="active.id"
            class="rounded-xl border border-white/5 bg-white/[0.04] p-7 backdrop-blur-md md:p-10"
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

            <!-- Footer: client + LinkedIn badge -->
            <div class="flex items-end justify-between gap-4 border-t border-white/5 pt-5">
              <div class="min-w-0">
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

              <a
                v-if="active.source_url"
                :href="active.source_url"
                target="_blank"
                rel="noopener noreferrer"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-[var(--accent-cyan,#06B6D4)]/40 bg-white/5 px-3 py-1.5 text-[0.65rem] font-medium uppercase tracking-[0.18em] text-[var(--fg-primary,#EDEDEF)]/90 transition-colors hover:border-[var(--accent-cyan,#06B6D4)] hover:text-[var(--accent-cyan,#06B6D4)] lg:text-xs"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >
                via LinkedIn ↗
              </a>
            </div>
          </article>
        </Transition>

        <!-- Dots / nav -->
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
  // Reset rotation timer so manual click doesn't immediately get bumped
  startTimer()
}

function handleKey(e) {
  if (e.key === 'ArrowRight') {
    e.preventDefault()
    goTo((activeIdx.value + 1) % testimonials.value.length)
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    goTo(
      (activeIdx.value - 1 + testimonials.value.length) % testimonials.value.length
    )
  }
}

function quoteParagraphs(t) {
  if (!t || !t.testimonial_text) return []
  return String(t.testimonial_text)
    .split(/\n\s*\n/)
    .map((p) => p.trim())
    .filter(Boolean)
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

@media (prefers-reduced-motion: reduce) {
  .quote-fade-enter-active,
  .quote-fade-leave-active { transition: none; }
  .quote-fade-enter-from,
  .quote-fade-leave-to { transform: none; }
  .dot { transition: none; }
}
</style>
