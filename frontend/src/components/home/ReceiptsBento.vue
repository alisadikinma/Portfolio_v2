<template>
  <section
    class="receipts relative w-full overflow-hidden bg-[var(--bg-deep,#050506)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="The receipts — proof of work"
  >
    <div class="relative z-10 mx-auto w-full max-w-7xl">
      <!-- Header -->
      <p
        class="mb-3 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-gold,#D4A843)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        the receipts
      </p>
      <h2
        class="mb-12 max-w-3xl text-3xl font-bold leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
        style="font-family: 'Space Grotesk', sans-serif;"
      >
        Not claims —
        <span class="text-[var(--accent-gold,#D4A843)]">proof.</span>
      </h2>

      <!-- Bento grid: lead tile spans 2×2, five supporting tiles around it -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:auto-rows-[1fr]">
        <article
          v-for="tile in tiles"
          :key="tile.id"
          class="receipt-tile group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-6 lg:p-7"
          :class="tile.lead
            ? 'is-lead border-[var(--accent-gold,#D4A843)]/45 sm:col-span-2 lg:col-span-2 lg:row-span-2'
            : 'border-white/10 bg-[var(--bg-elevated,#0C0C0F)]'"
        >
          <!-- Cinematic background (generated) + dark overlay for stat legibility -->
          <img
            :src="tile.image"
            alt=""
            aria-hidden="true"
            class="tile-bg pointer-events-none absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
            loading="lazy"
            decoding="async"
          />
          <div
            class="pointer-events-none absolute inset-0"
            :class="tile.lead ? 'tile-overlay-lead' : 'tile-overlay'"
            aria-hidden="true"
          ></div>

          <div class="relative z-10 flex items-start justify-between gap-3">
            <span class="text-2xl lg:text-3xl" aria-hidden="true">{{ tile.icon }}</span>
            <span
              v-if="tile.lead"
              class="rounded-full bg-[var(--accent-gold,#D4A843)] px-3 py-1 text-[0.58rem] font-semibold uppercase tracking-[0.16em] text-[#0A0A0C]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >Champion</span>
          </div>

          <div class="relative z-10" :class="tile.lead ? 'mt-8' : 'mt-6'">
            <div
              class="font-bold tracking-tight"
              :class="tile.lead
                ? 'text-5xl text-[var(--accent-gold,#D4A843)] lg:text-7xl'
                : 'text-4xl text-[var(--fg-primary,#EDEDEF)] lg:text-[2.6rem]'"
              style="font-family: 'Space Grotesk', sans-serif;"
            >
              {{ tile.value }}
            </div>
            <div
              v-if="tile.title"
              class="mt-2 text-lg font-semibold text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
              style="font-family: 'Space Grotesk', sans-serif;"
            >
              {{ tile.title }}
            </div>
            <div
              class="mt-1.5 text-[0.82rem] leading-snug text-[var(--fg-muted,#8A8F98)]"
              style="font-family: 'Inter', sans-serif;"
            >
              {{ tile.sub }}
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useHomepageFeatured } from '@/composables/useHomepageFeatured'

const { data } = useHomepageFeatured()

const stats = computed(() => data.value?.stats ?? {})
const productsCount = computed(() => stats.value.projects_count ?? 56)
const yearsCount = computed(() => stats.value.years_experience ?? 17)

// Six proof tiles. Live: products + years (from /api/homepage/featured stats).
// Static-locked: Demo Day #1, $318K+ impact, 16 countries, ≥95% accuracy.
// Cache-bust tag: bump `v` whenever a proof image is regenerated. The server
// serves /images/* as `immutable, max-age=1y`, so the filename alone never
// refetches at the edge or in the browser — the query param forces a new URL.
const v = '20260608b'
const tiles = computed(() => [
  {
    id: 'tile-demoday',
    image: `/images/proof/demoday.jpg?v=${v}`,
    lead: true,
    icon: '🥇',
    value: '#1',
    title: 'Global AI Demo Day 2026',
    sub: 'Beat 26 startups across 16 countries — ranked #1 AI Generalist.',
  },
  {
    id: 'tile-impact',
    image: `/images/proof/impact.jpg?v=${v}`,
    icon: '💰',
    value: '$318K+',
    sub: 'Documented business impact delivered.',
  },
  {
    id: 'tile-products',
    image: `/images/proof/products.jpg?v=${v}`,
    icon: '📦',
    value: `${productsCount.value}+`,
    sub: 'Enterprise products shipped.',
  },
  {
    id: 'tile-years',
    image: `/images/proof/years.jpg?v=${v}`,
    icon: '⏳',
    value: String(yearsCount.value),
    sub: 'Years building, hands-on.',
  },
  {
    id: 'tile-countries',
    image: `/images/proof/countries.jpg?v=${v}`,
    icon: '🌏',
    value: '16',
    sub: 'Countries — work shipped & presented.',
  },
  {
    id: 'tile-accuracy',
    image: `/images/proof/accuracy.jpg?v=${v}`,
    icon: '🎯',
    value: '≥95%',
    sub: 'Edge-AI visual inspection — better & cheaper than Keyence-class AOI.',
  },
])
</script>

<style scoped>
.receipt-tile {
  transition: transform 200ms ease-out, border-color 200ms ease-out;
}
.receipt-tile:hover {
  transform: translateY(-2px);
}
.receipt-tile.is-lead {
  background:
    radial-gradient(120% 100% at 0% 0%, rgba(212, 168, 67, 0.16), transparent 60%),
    var(--bg-elevated, #0C0C0F);
}
.tile-overlay {
  background: linear-gradient(
    to top,
    rgba(5, 5, 6, 0.93) 0%,
    rgba(5, 5, 6, 0.58) 45%,
    rgba(5, 5, 6, 0.22) 100%
  );
}
.tile-overlay-lead {
  background:
    radial-gradient(120% 100% at 0% 100%, rgba(212, 168, 67, 0.32), transparent 55%),
    linear-gradient(to top, rgba(5, 5, 6, 0.92) 0%, rgba(5, 5, 6, 0.5) 45%, rgba(5, 5, 6, 0.18) 100%);
}
@media (prefers-reduced-motion: reduce) {
  .receipt-tile .tile-bg { transition: none; }
}
@media (prefers-reduced-motion: reduce) {
  .receipt-tile { transition: none; }
  .receipt-tile:hover { transform: none; }
}
</style>
