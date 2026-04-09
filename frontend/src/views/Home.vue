<template>
  <div class="min-h-screen">

    <!-- 1. HERO — VEO Warrior Video -->
    <CinematicHero v-if="isSectionActive('hero')" />

    <!-- 2. SKILLS REEL — Kinetic Marquee -->
    <SkillsReel v-if="isSectionActive('skills-reel')" />

    <!-- 3. VIBE CODING — Split Layout (video left) -->
    <SkillShowcase
      v-if="isSectionActive('skill-vibe-coding')"
      :title="getSectionField('skill-vibe-coding', 'title', 'Vibe Coding')"
      subtitle="AI-Powered Development"
      :description="getSectionField('skill-vibe-coding', 'description', 'Build production apps with AI-powered development workflows using Claude Code, Cursor, and custom plugins.')"
      :video-src="getSectionField('skill-vibe-coding', 'video_url', '/videos/vibe-coding.mp4')"
      accent-color="gold"
      :reversed="false"
      :links="[
        { label: 'gaspol-dev', url: 'https://github.com/alisadikinma/gaspol-dev' },
        { label: 'ai-carousel', url: 'https://github.com/alisadikinma/ai-image-carousel-prompt-gen' },
        { label: 'ai-video', url: 'https://github.com/alisadikinma/ai-video-promo-engine' }
      ]"
    />

    <!-- 4. AI AUTOMATION — Reversed Split (video right) -->
    <SkillShowcase
      v-if="isSectionActive('skill-ai-automation')"
      :title="getSectionField('skill-ai-automation', 'title', 'AI Automation')"
      subtitle="Workflow Intelligence"
      :description="getSectionField('skill-ai-automation', 'description', 'Design and deploy intelligent automation pipelines using n8n, Zapier, and custom API orchestration.')"
      :video-src="getSectionField('skill-ai-automation', 'video_url', '/videos/ai-automation.mp4')"
      accent-color="cyan"
      :reversed="true"
    />

    <!-- 5. AI AGENTS — Split Layout (video left) -->
    <SkillShowcase
      v-if="isSectionActive('skill-ai-agents')"
      :title="getSectionField('skill-ai-agents', 'title', 'AI Agents')"
      subtitle="Autonomous Systems"
      :description="getSectionField('skill-ai-agents', 'description', 'Build autonomous AI agents with OpenClaw, Claude API, and multi-agent architectures for complex task execution.')"
      :video-src="getSectionField('skill-ai-agents', 'video_url', '/videos/ai-agents.mp4')"
      accent-color="indigo"
      :reversed="false"
    />

    <!-- 6. AI VIDEO GENERATION — Reversed Split (video right) -->
    <SkillShowcase
      v-if="isSectionActive('skill-ai-video')"
      :title="getSectionField('skill-ai-video', 'title', 'AI Video Generation')"
      subtitle="Cinematic Production"
      :description="getSectionField('skill-ai-video', 'description', 'Produce cinematic video content using VEO 3.1, Kling AI, and custom prompt engineering pipelines.')"
      :video-src="getSectionField('skill-ai-video', 'video_url', '/videos/ai-video.mp4')"
      accent-color="gold"
      :reversed="true"
    />

    <!-- 7. FEATURED PROJECTS — Asymmetric Bento Grid -->
    <ProjectsBento v-if="isSectionActive('featured-projects')" />

    <!-- 8. LATEST BLOG -->
    <LatestBlog v-if="isSectionActive('latest-blog')" />

    <!-- 9. STATS + CTA -->
    <template v-if="isSectionActive('stats-cta')">
      <StatsBar />
      <CTASection />
    </template>

  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { usePageSections } from '@/composables/usePageSections'

import CinematicHero from '@/components/CinematicHero.vue'
import SkillsReel from '@/components/home/SkillsReel.vue'
import SkillShowcase from '@/components/home/SkillShowcase.vue'
import ProjectsBento from '@/components/home/ProjectsBento.vue'
import LatestBlog from '@/components/home/LatestBlog.vue'
import StatsBar from '@/components/home/StatsBar.vue'
import CTASection from '@/components/home/CTASection.vue'

const { sections, fetchActiveSections } = usePageSections()

function isSectionActive(sectionType) {
  if (!sections.value || sections.value.length === 0) return true
  const section = sections.value.find(s => s.section_type === sectionType)
  return section ? section.is_active : false
}

function getSectionField(sectionType, field, fallback = '') {
  const section = sections.value?.find(s => s.section_type === sectionType)
  return section?.[field] || fallback
}

onMounted(() => {
  fetchActiveSections('homepage')
})
</script>
