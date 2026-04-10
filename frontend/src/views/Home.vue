<template>
  <div class="min-h-screen">

    <!-- 1. HERO — VEO Warrior Video -->
    <div class="snap-section">
      <CinematicHero v-if="isSectionActive('hero')" />
    </div>

    <!-- 2. SKILLS REEL — Kinetic Marquee (no snap, flows naturally) -->
    <SkillsReel v-if="isSectionActive('skills-reel')" />

    <!-- 3. VIBE CODING -->
    <div class="snap-section">
      <SkillShowcase
        v-if="isSectionActive('skill-vibe-coding')"
        :title="getSectionField('skill-vibe-coding', 'title', 'Vibe Coding')"
        subtitle="Ship Products 10x Faster"
        :description="getSectionField('skill-vibe-coding', 'description', vibeCodingDesc)"
        :bullets="vibeCodingBullets"
        :video-src="getSectionField('skill-vibe-coding', 'video_url', '/videos/vibe-coding.mp4')"
        accent-color="gold"
        :reversed="false"
        :links="vibeCodingLinks"
      />
    </div>

    <!-- 4. AI AUTOMATION -->
    <div class="snap-section">
      <SkillShowcase
        v-if="isSectionActive('skill-ai-automation')"
        :title="getSectionField('skill-ai-automation', 'title', 'AI Automation')"
        subtitle="Zero Manual Work"
        :description="getSectionField('skill-ai-automation', 'description', aiAutomationDesc)"
        :bullets="aiAutomationBullets"
        :video-src="getSectionField('skill-ai-automation', 'video_url', '/videos/ai-automation.mp4')"
        accent-color="cyan"
        :reversed="true"
      />
    </div>

    <!-- 5. AI AGENTS -->
    <div class="snap-section">
      <SkillShowcase
        v-if="isSectionActive('skill-ai-agents')"
        :title="getSectionField('skill-ai-agents', 'title', 'AI Agents')"
        subtitle="Autonomous Task Execution"
        :description="getSectionField('skill-ai-agents', 'description', aiAgentsDesc)"
        :bullets="aiAgentsBullets"
        :video-src="getSectionField('skill-ai-agents', 'video_url', '/videos/ai-agents.mp4')"
        accent-color="indigo"
        :reversed="false"
      />
    </div>

    <!-- 6. AI VIDEO GENERATION -->
    <div class="snap-section">
      <SkillShowcase
        v-if="isSectionActive('skill-ai-video')"
        :title="getSectionField('skill-ai-video', 'title', 'AI Video Generation')"
        subtitle="From Prompt to Film"
        :description="getSectionField('skill-ai-video', 'description', aiVideoDesc)"
        :bullets="aiVideoBullets"
        :video-src="getSectionField('skill-ai-video', 'video_url', '/videos/ai-video.mp4')"
        :youtube-videos="aiVideoYouTube"
        :social-links="aiVideoSocials"
        accent-color="gold"
        :reversed="true"
      />
    </div>

    <!-- 7. FEATURED PROJECTS -->
    <div class="snap-section">
      <ProjectsBento v-if="isSectionActive('featured-projects')" />
    </div>

    <!-- 8. STATS + CTA -->
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
import SkillShowcase from '@/components/home/SkillShowcase.vue'
import ProjectsBento from '@/components/home/ProjectsBento.vue'
import StatsBar from '@/components/home/StatsBar.vue'
import CTASection from '@/components/home/CTASection.vue'

const { sections, fetchActiveSections } = usePageSections()

// ── SVG Icons ─────────────────────────────────────────────────────
const youtubeIcon = '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'
const instagramIcon = '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'
const tiktokIcon = '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>'

// ── Skill Descriptions (extended, proof-driven) ───────────────────

const vibeCodingDesc = 'I turn prompts into deployed production apps. Claude Code is my IDE — I describe what I want, the AI writes the code, I review and ship. Not prototypes. Real products, live in production.'

const vibeCodingBullets = [
  'Shipped 3 production apps via vibe coding: Sparkfluence Studio, this portfolio, Indusia AI HMI',
  'Author of 3 open-source Claude Code plugins with active installs',
  'Full-stack: Vue 3, Laravel 12, Tailwind CSS 4, MySQL, deployed on production VPS',
  'Average build time: 3-7 days from first prompt to live deployment'
]

const aiAutomationDesc = 'I wire APIs, databases, and AI models into pipelines that run 24/7. Using n8n and custom integrations, I replace entire manual workflows with self-executing automation.'

const aiAutomationBullets = [
  'Built 56+ automation workflows across e-commerce, manufacturing, and SaaS',
  'Integrated n8n with OpenAI, Claude, WhatsApp Business API, Google Sheets, MySQL',
  'Reduced manual data entry by 92% for a logistics company (DHL supply chain project)',
  'API orchestration: webhook triggers, conditional routing, error retry, dead-letter queues'
]

const aiAgentsDesc = 'I architect multi-agent systems where specialized AI workers coordinate autonomously. Researcher, Coder, Reviewer — each purpose-built, then wired into an orchestration layer.'

const aiAgentsBullets = [
  'Built multi-agent systems with 3-5 specialized agents coordinating per task',
  'gaspol-dev plugin uses orchestrator + specialist agents for parallel code execution',
  'Agent architectures: sequential pipelines, parallel fan-out, hierarchical delegation',
  'Tools integration: web search, file system, database queries, API calls per agent'
]

const aiVideoDesc = 'I produce broadcast-ready video without a camera or crew. Script to keyframes to cinematic render — all AI-generated with 3-layer audio and frame-accurate lip sync.'

const aiVideoBullets = [
  'End-to-end AI video pipeline: script, storyboard, keyframes, video, audio — all AI-generated',
  'Published promotional videos on YouTube with real client projects',
  'Built ai-video-promo-engine: 6-phase production system as Claude Code plugin',
  'Multi-platform: VEO 3.1 (Google), Kling AI (Kuaishou), Seedance 2.0 (ByteDance)'
]

// ── Vibe Coding Links ─────────────────────────────────────────────
const vibeCodingLinks = [
  { label: 'Sparkfluence', url: 'https://sparkfluence.studio/' },
  { label: 'Indusia HMI', url: 'https://github.com/alisadikinma/indusia-ai-hmi' },
  { label: 'gaspol-dev', url: 'https://github.com/alisadikinma/gaspol-dev' },
  { label: 'ai-carousel', url: 'https://github.com/alisadikinma/ai-image-carousel-prompt-gen' },
  { label: 'ai-video', url: 'https://github.com/alisadikinma/ai-video-promo-engine' }
]

// ── AI Video YouTube + Socials ────────────────────────────────────
const aiVideoYouTube = [
  { title: 'Smart Port System Ubah Pelabuhan Dumai Jadi Hub Biomass Asia Tenggara', url: 'https://youtu.be/ZCxOIMDkbvc' },
  { title: 'Sistem Tracking Karyawan Real-Time: Disiplin Naik, Produksi Naik | INDUSIA Pulse', url: 'https://youtu.be/g_UErugvoOQ' },
  { title: 'Aplikasi Keamanan Komplek Perumahan Digital — SentraJaga Batam | Siskamling Digital 2026', url: 'https://youtu.be/LqdQMZvQ7fg' }
]

const aiVideoSocials = [
  { label: 'YouTube', url: 'https://youtube.com/@alisadikinma', icon: youtubeIcon },
  { label: 'Instagram', url: 'https://www.instagram.com/alisadikinma/', icon: instagramIcon },
  { label: 'TikTok', url: 'https://www.tiktok.com/@alisadikinma', icon: tiktokIcon }
]

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
  document.documentElement.classList.add('snap-page')
})

onUnmounted(() => {
  document.documentElement.classList.remove('snap-page')
})
</script>
