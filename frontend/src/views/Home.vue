<template>
  <div class="min-h-screen">

    <!-- 1. HERO — VEO Warrior Video -->
    <div class="snap-section" v-if="isSectionActive('hero')">
      <CinematicHero />
    </div>

    <!-- 2. SKILLS REEL — Kinetic Marquee (no snap, flows naturally) -->
    <SkillsReel v-if="isSectionActive('skills-reel')" />

    <!-- 2.5 WHAT I SOLVE — Three-discipline tabbed switcher (Phase 3) -->
    <div class="snap-section" v-if="isSectionActive('what-i-solve')">
      <WhatISolveTabs />
    </div>

    <!-- 3-6. (RETIRED) The 4 standalone SkillShowcase sections (vibe-coding,
         ai-automation, ai-agents, ai-video) have been merged into the
         WhatISolveTabs component above. Their video + description + bullet
         content lives in frontend/src/data/whatISolve.js. -->

    <!-- 7. FEATURED PROJECTS -->
    <div class="snap-section" v-if="isSectionActive('featured-projects')">
      <ProjectsBento />
    </div>

    <!-- 8. LATEST BLOG -->
    <div class="snap-section" v-if="isSectionActive('latest-blog')">
      <LatestBlog />
    </div>

    <!-- 8.5 TESTIMONIALS — LinkedIn-sourced quotes (Phase 6 partial, May 5) -->
    <div class="snap-section" v-if="isSectionActive('testimonials')">
      <TestimonialsCarousel />
    </div>

    <!-- 9. STATS + CTA -->
    <div class="snap-section" v-if="isSectionActive('stats-cta')">
      <StatsBar />
      <CTASection />
    </div>

  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { usePageSections } from '@/composables/usePageSections'

import CinematicHero from '@/components/CinematicHero.vue'
import SkillsReel from '@/components/home/SkillsReel.vue'
import WhatISolveTabs from '@/components/home/WhatISolveTabs.vue'
import ProjectsBento from '@/components/home/ProjectsBento.vue'
import LatestBlog from '@/components/home/LatestBlog.vue'
import TestimonialsCarousel from '@/components/home/TestimonialsCarousel.vue'
import StatsBar from '@/components/home/StatsBar.vue'
import CTASection from '@/components/home/CTASection.vue'

const { sections, fetchActiveSections } = usePageSections()

function isSectionActive(sectionType) {
  // If sections not loaded yet, show all (will re-evaluate once data arrives)
  if (!sections.value || sections.value.length === 0) return true
  const section = sections.value.find(s => s.section_type === sectionType)
  // If section exists in DB, respect is_active. If not found in DB, hide it.
  return section ? !!section.is_active : false
}

onMounted(() => {
  fetchActiveSections('homepage')
  document.documentElement.classList.add('snap-page')
})

onUnmounted(() => {
  document.documentElement.classList.remove('snap-page')
})
</script>
