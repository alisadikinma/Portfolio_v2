<template>
  <section
    class="intl-stages relative w-full overflow-hidden bg-[var(--bg-elevated,#0C0C0F)] px-6 py-24 lg:px-20 lg:py-32"
    aria-label="Track record — career and international stages"
  >
    <div class="relative z-10 mx-auto w-full max-w-7xl">
      <!-- Header -->
      <p
        class="mb-3 text-[0.7rem] uppercase tracking-[0.32em] text-[var(--accent-cyan,#06B6D4)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        track record
      </p>
      <h2
        class="mb-3 max-w-3xl text-3xl font-bold leading-tight text-[var(--fg-primary,#EDEDEF)] md:text-4xl lg:text-5xl"
        style="font-family: 'Space Grotesk', sans-serif;"
      >
        16 countries. 17 years.
        <span class="text-[var(--accent-gold,#D4A843)]">One operator.</span>
      </h2>
      <p
        class="mb-12 max-w-2xl text-base leading-relaxed text-[var(--fg-muted,#8A8F98)]"
        style="font-family: 'Inter', sans-serif; font-weight: 300;"
      >
        From the factory floor to the world's stages — one continuous arc.
      </p>

      <!-- Career band -->
      <p
        class="mb-5 text-[0.7rem] uppercase tracking-[0.28em] text-[var(--accent-gold,#D4A843)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        the 17-year arc
      </p>
      <div class="mb-14 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="chapter in careerChapters"
          :key="chapter.id"
          class="career-card group relative flex flex-col overflow-hidden rounded-2xl border border-[var(--accent-gold,#D4A843)]/20 bg-[var(--bg-deep,#050506)] p-6 lg:p-7"
          :class="chapterCover(chapter) ? 'cursor-pointer' : ''"
          :role="chapterCover(chapter) ? 'button' : undefined"
          :tabindex="chapterCover(chapter) ? 0 : undefined"
          :aria-label="chapterCover(chapter) ? `View ${chapter.org} photos` : undefined"
          @click="openChapter(chapter)"
          @keydown.enter.prevent="openChapter(chapter)"
          @keydown.space.prevent="openChapter(chapter)"
        >
          <!-- Real workplace photo (from the linked experience gallery). Missing → text-only. -->
          <div
            v-if="chapterCover(chapter)"
            class="stage-cover relative -mx-6 -mt-6 mb-5 overflow-hidden lg:-mx-7 lg:-mt-7"
          >
            <img
              :src="chapterCover(chapter)"
              :alt="`${chapter.org} — ${chapter.location}`"
              class="aspect-video w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
              loading="lazy"
              decoding="async"
            />
            <div class="stage-cover-grad pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <span
              class="absolute bottom-2 right-2 rounded-full bg-black/55 px-2 py-0.5 text-[0.6rem] text-white/85 backdrop-blur-sm"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ chapterItems(chapter).length }} photos
            </span>
          </div>

          <!-- Location + years -->
          <div class="mb-4 flex items-center gap-2">
            <span class="text-base" aria-hidden="true">{{ chapter.flag }}</span>
            <span
              class="text-[0.7rem] uppercase tracking-[0.18em] text-[var(--accent-gold,#D4A843)]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ chapter.location }}
            </span>
            <span
              class="ml-auto text-[0.7rem] tracking-[0.1em] text-[var(--fg-muted,#8A8F98)]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ chapter.years }}
            </span>
          </div>

          <!-- Role -->
          <h3
            class="mb-1 text-lg font-semibold leading-snug text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            {{ chapter.role }}
          </h3>

          <!-- Org -->
          <p
            class="mb-3 text-sm font-medium text-[var(--accent-gold,#D4A843)]"
            style="font-family: 'Inter', sans-serif;"
          >
            {{ chapter.org }}
          </p>

          <!-- Proof line -->
          <p
            class="mt-auto text-[0.84rem] leading-relaxed text-[var(--fg-muted,#8A8F98)]"
            style="font-family: 'Inter', sans-serif; font-weight: 300;"
          >
            {{ chapter.note }}
          </p>
        </article>
      </div>

      <!-- Stages band -->
      <p
        class="mb-5 text-[0.7rem] uppercase tracking-[0.28em] text-[var(--accent-cyan,#06B6D4)]"
        style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
      >
        global stages
      </p>
      <!-- Stage cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="stage in stages"
          :key="stage.id"
          class="stage-card group relative flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-[var(--bg-deep,#050506)] p-6 lg:p-7"
          :class="[
            { 'ring-1 ring-[var(--accent-gold,#D4A843)]/40': stage.highlight },
            coverFor(stage) ? 'cursor-pointer' : '',
          ]"
          :role="coverFor(stage) ? 'button' : undefined"
          :tabindex="coverFor(stage) ? 0 : undefined"
          :aria-label="coverFor(stage) ? `View ${stage.event} photos` : undefined"
          @click="openStage(stage)"
          @keydown.enter.prevent="openStage(stage)"
          @keydown.space.prevent="openStage(stage)"
        >
          <!-- Real event photo (from the matching award gallery). Missing → text-only. -->
          <div
            v-if="coverFor(stage)"
            class="stage-cover relative -mx-6 -mt-6 mb-5 overflow-hidden lg:-mx-7 lg:-mt-7"
          >
            <img
              :src="coverFor(stage)"
              :alt="`${stage.event} — ${stage.location}`"
              class="aspect-video w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
              loading="lazy"
              decoding="async"
            />
            <div class="stage-cover-grad pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <span
              class="absolute bottom-2 right-2 rounded-full bg-black/55 px-2 py-0.5 text-[0.6rem] text-white/85 backdrop-blur-sm"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ itemsFor(stage).length }} photos
            </span>
          </div>

          <!-- Location -->
          <div class="mb-4 flex items-center gap-2">
            <span class="text-base" aria-hidden="true">{{ stage.flag }}</span>
            <span
              class="text-[0.7rem] uppercase tracking-[0.18em] text-[var(--accent-cyan,#06B6D4)]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ stage.location }}
            </span>
            <span
              class="ml-auto text-[0.7rem] tracking-[0.1em] text-[var(--fg-muted,#8A8F98)]"
              style="font-family: 'JetBrains Mono', ui-monospace, monospace;"
            >
              {{ stage.year }}
            </span>
          </div>

          <!-- Event -->
          <h3
            class="mb-1.5 text-lg font-semibold leading-snug text-[var(--fg-primary,#EDEDEF)] lg:text-xl"
            style="font-family: 'Space Grotesk', sans-serif;"
          >
            {{ stage.event }}
          </h3>

          <!-- Result chip -->
          <p
            class="mb-3 text-sm font-medium"
            :class="stage.highlight ? 'text-[var(--accent-gold,#D4A843)]' : 'text-[var(--fg-primary,#EDEDEF)]/80'"
            style="font-family: 'Inter', sans-serif;"
          >
            {{ stage.result }}
          </p>

          <!-- Framing line -->
          <p
            class="mt-auto text-[0.84rem] leading-relaxed text-[var(--fg-muted,#8A8F98)]"
            style="font-family: 'Inter', sans-serif; font-weight: 300;"
          >
            {{ stage.note }}
          </p>
        </article>
      </div>
    </div>

    <!-- Full event gallery (reuses the shared modal — same pattern as About.vue) -->
    <BaseGalleryModal
      :show="galleryOpen"
      :title="activeTitle"
      :description="activeDescription"
      :items="activeItems"
      empty-message="No photos in this gallery yet"
      @close="galleryOpen = false"
    />
  </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useStageGalleries } from '@/composables/useStageGalleries'
import { useExperienceGalleries } from '@/composables/useExperienceGalleries'
import BaseGalleryModal from '@/components/base/BaseGalleryModal.vue'

// Hand-curated international stages — narrative facts sourced from the WHY-vault
// (10-Identity/awards.md): the curated framing (UNCTAD 1-of-48, NextDev→SV link)
// has no column in the generic /api/awards CRUD table. But the REAL event photos
// DO live there — each stage maps to its award via `awardId` and pulls the
// gallery (cover + full set) from /api/awards/{id}/galleries for trust + proof.
const stages = [
  {
    id: 'stage-bengaluru',
    awardId: 13,
    flag: '🇮🇳',
    location: 'Bengaluru, India',
    year: '2026',
    event: 'Global AI Demo Day',
    result: '🥇 #1 Champion — beat 26 startups',
    note: 'Outskill AI Generalist Fellowship — ranked the #1 AI Generalist globally.',
    highlight: true,
  },
  {
    id: 'stage-hangzhou',
    awardId: 10,
    flag: '🇨🇳',
    location: 'Hangzhou, China',
    year: '2019',
    event: 'Alibaba eFounders Fellowship',
    result: 'UN-UNCTAD × Alibaba — 1 of 48 in Asia',
    note: 'Selected from thousands across 16 countries for the UN-UNCTAD digital-economy fellowship.',
  },
  {
    id: 'stage-sv',
    awardId: 11,
    flag: '🇺🇸',
    location: 'Silicon Valley, USA',
    year: '2018',
    event: 'Google Startup Grind Global',
    result: 'Global Conference delegate',
    note: 'San Francisco — hosted by Startup Grind with Google for Entrepreneurs. Trip funded by the Telkomsel NextDev win.',
  },
  {
    id: 'stage-nextdev',
    awardId: 7,
    flag: '🇮🇩',
    location: 'Jakarta, Indonesia',
    year: '2018',
    event: 'Telkomsel NextDev',
    result: '🥇 1st Place — National Champion',
    note: "Indonesia's flagship startup competition — the prize funded the Google Startup Grind Silicon Valley trip.",
  },
  {
    id: 'stage-fenox',
    awardId: 8,
    flag: '🇺🇸',
    location: 'Silicon Valley, USA',
    year: '2017',
    event: 'Fenox Startup World Cup',
    result: '🥇 Wild Card Winner',
    note: 'BEKRAF × Fenox VC — global-stage Startup World Cup, grand finale in Silicon Valley.',
  },
]

// 17-year career arc — three chapters, text-only (no gallery). Facts verbatim from the
// WHY-vault (10-Identity/experience.md): Singapore MNC IT → Marlin Booking → Sat Nusapersada.
// Curated identity narrative; no /api column exists for it (mirrors the `stages` pattern).
const careerChapters = [
  {
    id: 'career-singapore',
    flag: '🇸🇬',
    location: 'Singapore — Multinational IT',
    years: '2008–2015',
    role: 'Software Consultant → Solution Support',
    org: 'exSYS · DHL Supply Chain · Thales/Gemalto · MPA Singapore',
    note: 'Eight years of Java/J2EE/Oracle enterprise delivery for Asia-Pacific MNC clients.',
    // "Singapore Career Journey" gallery (linked to all 4 SG roles via experience.gallery_ids)
    galleryIds: [14],
  },
  {
    id: 'career-marlin',
    flag: '🇮🇩',
    location: 'Indonesia — Startup',
    years: '2016–2019',
    role: 'Co-Founder & CEO',
    org: 'Marlin Booking',
    note: 'Digitized Indonesian ports for the Ministry of Transportation — $5M valuation, leading to UN-UNCTAD × Alibaba eFounders (1 of 48 in Asia).',
    galleryIds: [9, 11, 13, 10, 12],
  },
  {
    id: 'career-satnusa',
    flag: '🇮🇩',
    location: 'Indonesia — Manufacturing',
    years: '2023–2025',
    role: 'Head of Digital Transformation 4.0',
    org: 'PT Sat Nusapersada (Satnusa), Batam',
    note: 'Led a team of 31 shipping 56+ enterprise AI/IoT products and the MySatnusa Super App — $318K+ documented impact, which seeded INDUSIA.ai.',
    galleryIds: [], // no gallery linked yet → text-only fallback
  },
]

// Resolve real event photos per stage from the matching award gallery.
const { galleries } = useStageGalleries(stages.map((s) => s.awardId))

function coverFor(stage) {
  return galleries.value[stage.awardId]?.cover || null
}
function itemsFor(stage) {
  return galleries.value[stage.awardId]?.items || []
}

// Resolve real workplace photos per career chapter from its linked experience
// galleries (gallery_id based — Singapore → 14, Marlin → 9-13, Satnusa → none).
const careerGalleryIds = careerChapters.flatMap((c) => c.galleryIds || [])
const { coverFor: careerCoverFor, itemsFor: careerItemsFor } =
  useExperienceGalleries(careerGalleryIds)

function chapterCover(chapter) {
  return careerCoverFor(chapter.galleryIds)
}
function chapterItems(chapter) {
  return careerItemsFor(chapter.galleryIds)
}

// Shared lightbox — opened by stage cards OR career cards (only when photos exist).
const galleryOpen = ref(false)
const activeTitle = ref('')
const activeDescription = ref('')
const activeItems = ref([])

function openGallery(title, description, items) {
  if (!items || !items.length) return
  activeTitle.value = title
  activeDescription.value = description
  activeItems.value = items
  galleryOpen.value = true
}

function openStage(stage) {
  openGallery(stage.event, `${stage.location} · ${stage.year}`, itemsFor(stage))
}
function openChapter(chapter) {
  openGallery(chapter.org, `${chapter.location} · ${chapter.years}`, chapterItems(chapter))
}
</script>

<style scoped>
.stage-card,
.career-card {
  transition: transform 200ms ease-out, border-color 200ms ease-out;
}
.career-card:hover {
  transform: translateY(-2px);
  border-color: rgba(212, 168, 67, 0.4);
}
@media (prefers-reduced-motion: reduce) {
  .career-card { transition: none; }
  .career-card:hover { transform: none; }
}
.stage-cover-grad {
  background: linear-gradient(to top, rgba(5, 5, 6, 0.55) 0%, transparent 55%);
}
@media (prefers-reduced-motion: reduce) {
  .stage-card .stage-cover img { transition: none; }
}
.stage-card:hover {
  transform: translateY(-2px);
  border-color: rgba(6, 182, 212, 0.35);
}
@media (prefers-reduced-motion: reduce) {
  .stage-card { transition: none; }
  .stage-card:hover { transform: none; }
}
</style>
