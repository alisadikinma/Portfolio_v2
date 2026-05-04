<template>
  <section
    class="what-i-solve relative flex min-h-[80vh] w-full flex-col justify-center overflow-hidden bg-[var(--bg-deep,#050506)] px-4 py-20 lg:px-12 lg:py-28"
    aria-label="What I solve — three AI disciplines"
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
      Three disciplines.
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
        v-for="(tab, idx) in tabs"
        :key="tab.id"
        :id="`tab-${tab.id}`"
        ref="tabRefs"
        :data-idx="idx"
        role="tab"
        :aria-selected="activeId === tab.id ? 'true' : 'false'"
        :aria-controls="`panel-${tab.id}`"
        :tabindex="activeId === tab.id ? 0 : -1"
        class="tab-btn group relative flex shrink-0 snap-start items-center gap-2 px-5 py-3 text-sm font-medium uppercase tracking-[0.18em] transition-colors duration-200 md:text-base"
        :class="activeId === tab.id ? 'text-[var(--accent-gold,#D4A843)]' : 'text-[var(--fg-muted,#8A8F98)] hover:text-[var(--fg-primary,#EDEDEF)]'"
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
          class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16"
        >
          <!-- Left: icon + headline + metrics + CTA -->
          <div class="flex flex-col justify-center">
            <span
              class="mb-6 text-6xl opacity-90 lg:text-7xl"
              aria-hidden="true"
            >{{ active.icon }}</span>

            <h3
              class="mb-8 text-2xl leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-3xl lg:text-[2.25rem] lg:leading-[1.15]"
              style="font-family: 'Space Grotesk', sans-serif; font-weight: 600;"
            >
              {{ active.headline }}
            </h3>

            <!-- Metrics row -->
            <div class="mb-8 grid grid-cols-3 gap-3 lg:gap-6">
              <div
                v-for="(metric, i) in active.metrics"
                :key="i"
                class="rounded-md border border-white/5 bg-[var(--bg-elevated,#0C0C0F)]/60 px-3 py-4 backdrop-blur-sm lg:px-5 lg:py-5"
              >
                <div
                  class="text-xl text-[var(--accent-gold,#D4A843)] md:text-2xl lg:text-[1.85rem]"
                  style="font-family: 'Space Grotesk', sans-serif; font-weight: 700; letter-spacing: -0.02em;"
                >
                  {{ metric.value }}
                </div>
                <div
                  class="mt-1.5 text-[0.625rem] uppercase tracking-[0.16em] text-[var(--fg-muted,#8A8F98)] lg:text-[0.7rem]"
                  style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
                >
                  {{ metric.label }}
                </div>
              </div>
            </div>

            <!-- CTA — inline glass-style RouterLink (matches BaseButton variant=glass tokens) -->
            <div>
              <RouterLink
                :to="active.cta.to"
                class="inline-flex items-center gap-2 rounded-md border border-[var(--accent-gold,#D4A843)]/40 bg-white/5 px-5 py-2.5 text-sm font-medium uppercase tracking-[0.18em] text-[var(--fg-primary,#EDEDEF)] backdrop-blur transition-all duration-200 hover:border-[var(--accent-gold,#D4A843)] hover:bg-[var(--accent-gold,#D4A843)]/10"
                style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
              >
                {{ active.cta.label }} →
              </RouterLink>
            </div>
          </div>

          <!-- Right: visual (with graceful fallback) -->
          <div class="flex items-center justify-center">
            <div
              class="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-white/5 bg-[var(--bg-elevated,#0C0C0F)] shadow-[0_30px_60px_-15px_rgba(0,0,0,0.6)]"
            >
              <img
                v-if="!visualBroken[active.id]"
                :src="active.visual"
                :alt="`${active.label} showcase`"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover"
                @error="onVisualError(active.id)"
              />
              <!-- Placeholder when visual 404s -->
              <div
                v-else
                class="visual-fallback flex h-full w-full items-center justify-center"
                :data-tab="active.id"
                aria-hidden="true"
              >
                <span class="fallback-icon text-7xl opacity-95 lg:text-8xl">
                  {{ active.icon }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { tabs } from '@/data/whatISolve'

const activeId = ref(tabs[0].id)
const tabRefs = ref([])
const visualBroken = reactive({})

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
  // Wait a tick so the new tab's tabindex=0 lands before focusing
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

function onVisualError(id) {
  visualBroken[id] = true
}

onMounted(() => {
  // Pre-flight: probe each visual once so the first tab swap doesn't flicker
  // through a broken-image state. Skipped silently when network blocked.
  tabs.forEach((t) => {
    if (typeof Image === 'undefined') return
    const probe = new Image()
    probe.onerror = () => {
      visualBroken[t.id] = true
    }
    probe.src = t.visual
  })
})
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
.tab-btn:hover::after {
  transform: scaleX(1);
}
.tab-btn[aria-selected='true']::after {
  transform: scaleX(1);
}

/* Panel cross-fade — 120ms out, 200ms in */
.panel-fade-enter-active {
  transition: opacity 200ms ease-out, transform 200ms ease-out;
}
.panel-fade-leave-active {
  transition: opacity 120ms ease-in, transform 120ms ease-in;
}
.panel-fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.panel-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Visual fallback: soft gold/cyan radial gradient (no purple/magenta) */
.visual-fallback {
  background:
    radial-gradient(circle at 30% 30%, rgba(212, 168, 67, 0.18), transparent 55%),
    radial-gradient(circle at 75% 70%, rgba(6, 182, 212, 0.12), transparent 55%),
    var(--bg-elevated, #0C0C0F);
}

/* Reduced motion: zero out transitions */
@media (prefers-reduced-motion: reduce) {
  .tab-btn::after,
  .panel-fade-enter-active,
  .panel-fade-leave-active {
    transition: none;
  }
  .panel-fade-enter-from,
  .panel-fade-leave-to {
    transform: none;
  }
}

/* Hide horizontal scrollbar on mobile tab strip */
[role='tablist']::-webkit-scrollbar {
  display: none;
}
</style>
