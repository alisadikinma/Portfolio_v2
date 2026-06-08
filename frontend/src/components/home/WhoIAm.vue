<template>
  <section
    class="who-i-am relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="Who is Ali Sadikin Ma"
  >
    <div class="hero-glow pointer-events-none absolute inset-0" aria-hidden="true"></div>

    <div class="relative z-10 mx-auto grid w-full max-w-7xl grid-cols-1 items-center gap-14 lg:grid-cols-12 lg:gap-16">
      <!-- Portrait (real photo from settings.about.profile_photo) -->
      <div class="order-1 lg:order-1 lg:col-span-5">
        <div class="portrait-frame relative mx-auto aspect-[4/5] w-full max-w-md overflow-hidden rounded-2xl border border-white/10 bg-[var(--bg-elevated,#0C0C0F)]">
          <img
            v-if="portrait"
            :src="portrait"
            :alt="`Portrait of ${name}`"
            class="h-full w-full object-cover"
            loading="lazy"
            decoding="async"
          />
          <!-- Graceful fallback: monogram on glass when no photo resolves -->
          <div
            v-else
            class="flex h-full w-full items-center justify-center"
            aria-hidden="true"
          >
            <span
              class="text-7xl font-bold text-[var(--accent-gold,#D4A843)] opacity-90"
              style="font-family: 'Space Grotesk', sans-serif;"
            >AS</span>
          </div>
          <div class="portrait-vignette pointer-events-none absolute inset-0" aria-hidden="true"></div>
        </div>
      </div>

      <!-- Answer-shaped about block (LLM-quotable — GEO lever G3) -->
      <div class="order-2 lg:order-2 lg:col-span-7">
        <p
          class="mb-5 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
          style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
        >
          who i am
        </p>

        <h2
          class="mb-7 text-3xl font-bold leading-[1.15] tracking-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-[2.9rem]"
          style="font-family: 'Space Grotesk', sans-serif;"
        >
          Ali Sadikin Ma is an AI Generalist who turns frontier AI models into
          <span class="text-[var(--accent-gold,#D4A843)]">shipped products</span> — not slide decks.
        </h2>

        <p
          class="mb-8 max-w-2xl text-base leading-relaxed text-[var(--fg-muted,#8A8F98)] lg:text-[1.08rem]"
          style="font-family: 'Inter', sans-serif; font-weight: 300;"
        >
          Based in <span class="text-[var(--fg-primary,#EDEDEF)]">Batam, Indonesia</span>, I ship
          production software with AI as my co-builder — from factory-floor inspection systems to
          startup stages around the world, building in the open the whole way.
        </p>

        <!-- Identity chips -->
        <ul class="flex flex-wrap gap-3" aria-label="Identity facts">
          <li
            v-for="chip in chips"
            :key="chip"
            class="rounded-full border border-white/10 bg-white/[0.03] px-4 py-2 text-[0.72rem] uppercase tracking-[0.12em] text-[var(--fg-primary,#EDEDEF)]/85"
            style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
          >
            {{ chip }}
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useAboutSettings } from '@/composables/useAboutSettings'

const { heroName, heroAvatar } = useAboutSettings()

const name = computed(() => heroName.value || 'Ali Sadikin Ma')

// Resolve portrait URL: absolute http(s) → as-is; /storage or /uploads → prepend
// API origin; bare → leave for the browser to resolve relative to origin.
const portrait = computed(() => {
  const raw = heroAvatar.value
  if (!raw) return null
  if (/^https?:\/\//i.test(raw)) return raw
  const base = (import.meta.env.VITE_API_URL || '').replace(/\/api\/?$/, '')
  if (raw.startsWith('/')) return `${base}${raw}`
  return raw
})

const chips = [
  'AI Generalist',
  '17 years building',
  'Batam → 16 countries',
  '20+ open-source repos',
]
</script>

<style scoped>
.hero-glow {
  background:
    radial-gradient(50% 60% at 18% 35%, rgba(212, 168, 67, 0.10), transparent 70%),
    radial-gradient(40% 50% at 85% 70%, rgba(6, 182, 212, 0.07), transparent 70%);
}
.portrait-frame {
  box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.7);
}
.portrait-vignette {
  background: linear-gradient(to top, rgba(5, 5, 6, 0.45) 0%, transparent 45%);
}
</style>
