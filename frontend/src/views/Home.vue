<template>
  <div class="min-h-screen">

    <!-- 1. HERO — person-forward montage, wordmark, manifesto, stat triad -->
    <div class="snap-section" v-if="isSectionActive('hero')">
      <HeroOperator />
    </div>

    <!-- 2. WHO I AM — answer-shaped about block + portrait (LLM-quotable) -->
    <div class="snap-section" v-if="isSectionActive('who-i-am')">
      <WhoIAm />
    </div>

    <!-- 3. WHAT I BUILD — three-discipline tabbed switcher (incl. MANDOR AI) -->
    <div class="snap-section" v-if="isSectionActive('what-i-solve')">
      <WhatISolveTabs />
    </div>

    <!-- 4. THE RECEIPTS — 6-tile proof bento -->
    <div class="snap-section" v-if="isSectionActive('receipts')">
      <ReceiptsBento />
    </div>

    <!-- 5. INTERNATIONAL STAGES — global reach cards -->
    <div class="snap-section" v-if="isSectionActive('international-stages')">
      <InternationalStages />
    </div>

    <!-- 6. SELECTED WORK — metric-led project cards -->
    <div class="snap-section" v-if="isSectionActive('selected-work')">
      <SelectedWork />
    </div>

    <!-- 7. TESTIMONIALS — LinkedIn-sourced quotes -->
    <div class="snap-section" v-if="isSectionActive('testimonials')">
      <TestimonialsCarousel />
    </div>

    <!-- 8. LATEST WRITING — editorial feed + Content Engine meta-flex -->
    <div class="snap-section" v-if="isSectionActive('latest-writing')">
      <LatestWriting />
    </div>

    <!-- 9. JOIN THE BUILD — follow + newsletter + WhatsApp -->
    <div class="snap-section" v-if="isSectionActive('join-the-build')">
      <JoinTheBuild />
    </div>

  </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted } from 'vue'
import { usePageSections } from '@/composables/usePageSections'
import { useMetaTags } from '@/composables/useMetaTags'
import { useAboutSettings } from '@/composables/useAboutSettings'

import HeroOperator from '@/components/home/HeroOperator.vue'
import WhoIAm from '@/components/home/WhoIAm.vue'
import WhatISolveTabs from '@/components/home/WhatISolveTabs.vue'
import ReceiptsBento from '@/components/home/ReceiptsBento.vue'
import InternationalStages from '@/components/home/InternationalStages.vue'
import SelectedWork from '@/components/home/SelectedWork.vue'
import TestimonialsCarousel from '@/components/home/TestimonialsCarousel.vue'
import LatestWriting from '@/components/home/LatestWriting.vue'
import JoinTheBuild from '@/components/home/JoinTheBuild.vue'

const { sections, fetchActiveSections } = usePageSections()
const { injectPersonSchema, injectFaqSchema } = useMetaTags()
const { aboutSettings } = useAboutSettings()

function isSectionActive(sectionType) {
  // Before sections load, show everything (re-evaluates once data arrives).
  if (!sections.value || sections.value.length === 0) return true
  const section = sections.value.find(s => s.section_type === sectionType)
  // Row exists → respect its is_active toggle. Row absent → default-on: the
  // Operator spine ships before PageSectionSeeder runs (Phase E), so unknown
  // (not-yet-seeded) types must still render rather than vanish.
  return section ? !!section.is_active : true
}

onMounted(async () => {
  fetchActiveSections('homepage')
  document.documentElement.classList.add('snap-page')

  // GEO: Person + FAQ JSON-LD (G2/G3). Inject curated schema immediately so
  // crawlers always see it; enrich the portrait + sameAs from live CMS settings
  // when they resolve (overrides are optional — curated defaults stand alone).
  injectPersonSchema()
  injectFaqSchema()

  await nextTick()
  const about = aboutSettings.value || {}
  const sameAs = Array.isArray(about.social_links)
    ? about.social_links.map((l) => l?.url).filter(Boolean)
    : []
  if (about.profile_photo || sameAs.length) {
    injectPersonSchema({ image: about.profile_photo || undefined, sameAs })
  }
})

onUnmounted(() => {
  document.documentElement.classList.remove('snap-page')
})
</script>
