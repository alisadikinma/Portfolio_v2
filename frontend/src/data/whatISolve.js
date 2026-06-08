// Static content for the "What I Build" tabbed switcher (The Operator redesign,
// 2026-06-08). Three disciplines that double as Ali's course topics:
// Vibe Coding · AI Agent OS (MANDOR AI) · Generative Video.
// (AI Automation dropped from the homepage switcher — folded into Vibe
// Coding / AI Agent OS narratives; still part of the broader skill set.)
//
// Default selection = vibe-coding (first in array).
// Accent palette: gold | cyan | indigo.
// `product` + `badge` are optional; when present the tab renders as a
// featured/"Introducing" discipline (see WhatISolveTabs.vue).
export const tabs = [
  {
    id: 'vibe-coding',
    label: 'Vibe Coding',
    icon: '⚡',
    accent: 'gold',
    subtitle: 'Ship Products at AI Speed',
    headline:
      'I ship production code at AI speed — without sacrificing architecture quality.',
    description:
      'I turn prompts into deployed production apps. Claude Code is my IDE — I describe what I want, the AI writes the code, I review and ship. Not prototypes. Real products, live in production.',
    bullets: [
      'Shipped production apps via vibe coding: Sparkfluence Studio, this portfolio, Indusia AI HMI',
      'Author of open-source Claude Code plugins with active installs',
      'Full-stack: Vue 3, Laravel 12, Tailwind CSS 4, MySQL, deployed on production VPS',
      'Average build time: 3-7 days from first prompt to live deployment',
    ],
    videoSrc: '/videos/vibe-coding.mp4',
    cta: { label: 'Learn it →', anchor: 'join-the-build' },
  },
  {
    id: 'ai-agent-os',
    label: 'AI Agent OS',
    product: 'MANDOR AI',
    badge: 'Introducing',
    icon: '🤖',
    accent: 'cyan',
    subtitle: 'The OS for your AI workforce',
    headline: 'The operating system for your AI workforce.',
    description:
      'MANDOR AI turns coding agents into real teammates. Assign tasks like you would to a colleague — they pick up the work, write code, report blockers, and compound skills over time. Squads, Autopilots, and reusable Skills. Your next 10 hires won’t be human.',
    bullets: [
      'Open-source managed-agents platform — assign issues to agents on a shared board',
      'Works with 11+ agent CLIs (Claude Code, Codex, Copilot, Cursor, Gemini…)',
      'Squads route work to the right agent; Autopilots run recurring jobs on a schedule',
      'Self-hostable — agents execute on your own runtimes via a local daemon',
    ],
    videoSrc: '/videos/ai-agents.mp4',
    cta: { label: 'Explore MANDOR AI →', anchor: 'join-the-build' },
  },
  {
    id: 'ai-video',
    label: 'Generative Video',
    icon: '🎬',
    accent: 'gold',
    subtitle: 'From Prompt to Film',
    headline:
      'Cinematic AI video at scale — for products, ads, and brand storytelling.',
    description:
      'I produce broadcast-ready video without a camera or crew. Script to keyframes to cinematic render — all AI-generated with 3-layer audio and frame-accurate lip sync.',
    bullets: [
      'End-to-end AI video pipeline: script, storyboard, keyframes, video, audio — all AI-generated',
      'Published promotional videos on YouTube with real client projects',
      'Built ai-video-promo-engine: 6-phase production system as a Claude Code plugin',
      'Multi-platform: VEO 3.1 (Google), Kling AI (Kuaishou), Seedance 2.0 (ByteDance)',
    ],
    videoSrc: '/videos/ai-video.mp4',
    cta: { label: 'Learn it →', anchor: 'join-the-build' },
  },
]
