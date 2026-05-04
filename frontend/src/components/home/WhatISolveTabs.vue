<template>
  <section
    class="what-i-solve relative flex min-h-[80vh] w-full flex-col justify-center overflow-hidden bg-[var(--bg-deep,#050506)] px-4 py-20 lg:px-12 lg:py-28"
    aria-label="What I solve — four AI disciplines"
  >
    <!-- Eyebrow -->
    <p
      class="mb-3 text-center text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
      style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
    >
      what i solve
    </p>

    <!-- Section title -->
    <h2
      class="mb-12 text-center text-3xl font-bold text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
      style="font-family: 'Space Grotesk', sans-serif;"
    >
      Four disciplines.
      <span class="text-[var(--accent-gold,#D4A843)]">One operator.</span>
    </h2>

    <!-- Tab strip (mobile = horizontal scroll snap; desktop = centered row) -->
    <div
      role="tablist"
      aria-label="What I solve"
      class="mx-auto mb-10 flex w-full max-w-5xl gap-2 overflow-x-auto md:overflow-visible md:justify-center md:gap-1 [scrollbar-width:none] [&amp;::-webkit-scrollbar]:hidden snap-x snap-mandatory"
      @keydown="handleKeydown"
    >
      <button
        v-for="(tab) in tabs"
        :key="tab.id"
        :id="`tab-${tab.id}`"
        role="tab"
        :aria-selected="activeId === tab.id ? 'true' : 'false'"
        :aria-controls="`panel-${tab.id}`"
        :tabindex="activeId === tab.id ? 0 : -1"
        class="tab-btn group relative flex shrink-0 snap-start items-center gap-2 px-5 py-3 text-sm font-medium uppercase tracking-[0.18em] transition-colors duration-200 md:text-base"
        :class="[
          activeId === tab.id ? accentTextClass(tab.accent) : 'text-[var(--fg-muted,#8A8F98)] hover:text-[var(--fg-primary,#EDEDEF)]',
        ]"
        :data-accent="tab.accent"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
        @click="selectTab(tab.id)"
      >
        <span class="text-lg" aria-hidden="true">{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- Panel (transitions between tabs) -->
    <div class="mx-auto w-full max-w-6xl">
      <Transition name="panel-fade" mode="out-in">
        <div
          :key="active.id"
          :id="`panel-${active.id}`"
          role="tabpanel"
          :aria-labelledby="`tab-${active.id}`"
          tabindex="0"
          class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-12"
        >
          <!-- Left: text column (5/12 on desktop) -->
          <div class="flex flex-col justify-center lg:col-span-5">
            <span
              class="mb-5 text-5xl opacity-90 lg:text-6xl"
              aria-hidden="true"
            >{{ active.icon }}</span>

            <p
              class="mb-3 text-[0.7rem] uppercase tracking-[0.28em]"
              :class="accentTextClass(active.accent)"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ active.subtitle }}
            </p>

            <h3
              class="mb-5 text-2xl leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-3xl lg:text-[2.1rem] lg:leading-[1.18]"
              style="font-family: 'Space Grotesk', sans-serif; font-weight: 600;"
            >
              {{ active.headline }}
            </h3>

            <p
              class="mb-6 text-base leading-relaxed text-[var(--fg-muted,#8A8F98)] lg:text-[1.05rem]"
              style="font-family: 'Inter', sans-serif; font-weight: 300;"
            >
              {{ active.description }}
            </p>

            <!-- Bullets -->
            <ul
              v-if="active.bullets && active.bullets.length"
              class="mb-7 space-y-3"
            >
              <li
                v-for="(bullet, i) in active.bullets"
                :key="i"
                class="flex items-start gap-3 text-sm leading-relaxed text-[var(--fg-primary,#EDEDEF)]/85 lg:text-[0.95rem]"
              >
                <svg
                  class="mt-0.5 h-4 w-4 shrink-0"
                  :class="accentTextClass(active.accent)"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <polyline points="20 6 9 17 4 12" />
                </svg>
                <span>{{ bullet }}</span>
              </li>
            </ul>

            <!-- CTA -->
            <div>
              <RouterLink
                :to="active.cta.to"
                class="inline-flex items-center gap-2 rounded-md border bg-white/5 px-5 py-2.5 text-sm font-medium uppercase tracking-[0.18em] text-[var(--fg-primary,#EDEDEF)] backdrop-blur transition-all duration-200 hover:bg-white/10"
                :class="accentBorderClass(active.accent)"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >
                {{ active.cta.label }} →
              </RouterLink>
            </div>
          </div>

          <!-- Right: video column (7/12 on desktop) -->
          <div class="flex items-center justify-center lg:col-span-7">
            <div
              class="relative aspect-video w-full overflow-hidden rounded-xl border border-white/5 bg-[var(--bg-elevated,#0C0C0F)] shadow-[0_30px_60px_-15px_rgba(0,0,0,0.6)]"
            >
              <!-- Video element — autoplay muted loop, lazy preload until in viewport -->
              <video
                v-if="!videoBroken[active.id]"
                :key="active.videoSrc"
                :src="active.videoSrc"
                class="h-full w-full object-cover"
                autoplay
                loop
                muted
                playsinline
                preload="metadata"
                @error="onVideoError(active.id)"
              />
              <!-- Fallback: gradient placeholder with icon -->
              <div
                v-else
                class="visual-fallback flex h-full w-full items-center justify-center"
                :data-accent="active.accent"
                aria-hidden="true"
              >
                <div class="text-center">
                  <span class="block text-7xl opacity-95 lg:text-8xl">{{ active.icon }}</span>
                  <p
                    class="mt-3 text-xs uppercase tracking-[0.2em] text-[var(--fg-muted,#8A8F98)]"
                    style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                  >
                    Video coming soon
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, reactive } from 'vue'
import { RouterLink } from 'vue-router'
import { tabs } from '@/data/whatISolve'

const activeId = ref(tabs[0].id)
const videoBroken = reactive({})

const active = computed(
  () => tabs.find((t) => t.id === activeId.value) ?? tabs[0]
)

function selectTab(id) {
  if (activeId.value === id) return
  activeId.value = id
}

function focusTabAt(idx) {
  const wrapped = (idx + tabs.length) % tabs.length
  const next = tabs[wrapped]
  activeId.value = next.id
  requestAnimationFrame(() => {
    const el = document.getElementById(`tab-${next.id}`)
    if (el) el.focus()
  })
}

function handleKeydown(e) {
  const currentIdx = tabs.findIndex((t) => t.id === activeId.value)
  switch (e.key) {
    case 'ArrowRight':
      e.preventDefault()
      focusTabAt(currentIdx + 1)
      break
    case 'ArrowLeft':
      e.preventDefault()
      focusTabAt(currentIdx - 1)
      break
    case 'Home':
      e.preventDefault()
      focusTabAt(0)
      break
    case 'End':
      e.preventDefault()
      focusTabAt(tabs.length - 1)
      break
  }
}

function onVideoError(id) {
  videoBroken[id] = true
}

function accentTextClass(accent) {
  switch (accent) {
    case 'cyan': return 'text-[var(--accent-cyan,#06B6D4)]'
    case 'indigo': return 'text-[var(--accent-indigo,#5E6AD2)]'
    case 'gold':
    default: return 'text-[var(--accent-gold,#D4A843)]'
  }
}

function accentBorderClass(accent) {
  switch (accent) {
    case 'cyan': return 'border-[var(--accent-cyan,#06B6D4)]/40 hover:border-[var(--accent-cyan,#06B6D4)]'
    case 'indigo': return 'border-[var(--accent-indigo,#5E6AD2)]/40 hover:border-[var(--accent-indigo,#5E6AD2)]'
    case 'gold':
    default: return 'border-[var(--accent-gold,#D4A843)]/40 hover:border-[var(--accent-gold,#D4A843)]'
  }
}
</script>

<style scoped>
/* Tab underline grow left → right on hover; persists at full width when active */
.tab-btn::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 0;
  height: 2px;
  width: 100%;
  background: var(--accent-gold, #D4A843);
  transform: scaleX(0);
  transform-origin: left center;
  transition: transform 180ms ease-out;
}
.tab-btn[data-accent='cyan']::after { background: var(--accent-cyan, #06B6D4); }
.tab-btn[data-accent='indigo']::after { background: var(--accent-indigo, #5E6AD2); }
.tab-btn:hover::after { transform: scaleX(1); }
.tab-btn[aria-selected='true']::after { transform: scaleX(1); }

/* Panel cross-fade — 120ms out, 200ms in */
.panel-fade-enter-active { transition: opacity 200ms ease-out, transform 200ms ease-out; }
.panel-fade-leave-active { transition: opacity 120ms ease-in, transform 120ms ease-in; }
.panel-fade-enter-from { opacity: 0; transform: translateY(8px); }
.panel-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Visual fallback gradient — accent-aware */
.visual-fallback {
  background:
    radial-gradient(circle at 30% 30%, rgba(212, 168, 67, 0.18), transparent 55%),
    radial-gradient(circle at 75% 70%, rgba(6, 182, 212, 0.12), transparent 55%),
    var(--bg-elevated, #0C0C0F);
}
.visual-fallback[data-accent='cyan'] {
  background:
    radial-gradient(circle at 30% 30%, rgba(6, 182, 212, 0.22), transparent 55%),
    radial-gradient(circle at 75% 70%, rgba(212, 168, 67, 0.10), transparent 55%),
    var(--bg-elevated, #0C0C0F);
}
.visual-fallback[data-accent='indigo'] {
  background:
    radial-gradient(circle at 30% 30%, rgba(94, 106, 210, 0.22), transparent 55%),
    radial-gradient(circle at 75% 70%, rgba(212, 168, 67, 0.10), transparent 55%),
    var(--bg-elevated, #0C0C0F);
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  .tab-btn::after,
  .panel-fade-enter-active,
  .panel-fade-leave-active { transition: none; }
  .panel-fade-enter-from,
  .panel-fade-leave-to { transform: none; }
}

[role='tablist']::-webkit-scrollbar { display: none; }
</style>
