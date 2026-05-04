// Static content for the What I Solve tabbed switcher (Phase 3 — expanded
// to 4 disciplines after operator request to merge old SkillShowcase
// content into this single tab section).
//
// Default selection = vibe-coding (first in array).
// Accent palette: gold | cyan | indigo (rotates per tab).
// Video paths point at /videos/{id}.mp4 — same files the old
// SkillShowcase used.
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
      'Shipped 3 production apps via vibe coding: Sparkfluence Studio, this portfolio, Indusia AI HMI',
      'Author of 3 open-source Claude Code plugins with active installs',
      'Full-stack: Vue 3, Laravel 12, Tailwind CSS 4, MySQL, deployed on production VPS',
      'Average build time: 3-7 days from first prompt to live deployment',
    ],
    videoSrc: '/videos/vibe-coding.mp4',
    cta: { label: 'Case study', to: '/work#vibe-coding' },
  },
  {
    id: 'ai-automation',
    label: 'AI Automation',
    icon: '⚙️',
    accent: 'cyan',
    subtitle: 'Zero Manual Work',
    headline:
      'I wire APIs, databases, and AI models into pipelines that run 24/7.',
    description:
      'Using n8n and custom integrations, I replace entire manual workflows with self-executing automation that never sleeps and never asks for a raise.',
    bullets: [
      'Built 56+ automation workflows across e-commerce, manufacturing, and SaaS',
      'Integrated n8n with OpenAI, Claude, WhatsApp Business API, Google Sheets, MySQL',
      'Reduced manual data entry by 92% for a logistics company (DHL supply chain project)',
      'API orchestration: webhook triggers, conditional routing, error retry, dead-letter queues',
    ],
    videoSrc: '/videos/ai-automation.mp4',
    cta: { label: 'See workflows', to: '/work#ai-automation' },
  },
  {
    id: 'ai-agents',
    label: 'AI Agents',
    icon: '🤖',
    accent: 'indigo',
    subtitle: 'Autonomous Task Execution',
    headline:
      'Multi-agent systems that handle complex business workflows autonomously.',
    description:
      'I architect multi-agent systems where specialized AI workers coordinate autonomously. Researcher, Coder, Reviewer — each purpose-built, then wired into an orchestration layer.',
    bullets: [
      'Built multi-agent systems with 3-5 specialized agents coordinating per task',
      'gaspol-dev plugin uses orchestrator + specialist agents for parallel code execution',
      'Agent architectures: sequential pipelines, parallel fan-out, hierarchical delegation',
      'Tools integration: web search, file system, database queries, API calls per agent',
    ],
    videoSrc: '/videos/ai-agents.mp4',
    cta: { label: 'See live demo', to: '/work#ai-agents' },
  },
  {
    id: 'ai-video',
    label: 'Video Generation',
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
      'Built ai-video-promo-engine: 6-phase production system as Claude Code plugin',
      'Multi-platform: VEO 3.1 (Google), Kling AI (Kuaishou), Seedance 2.0 (ByteDance)',
    ],
    videoSrc: '/videos/ai-video.mp4',
    cta: { label: 'Watch reel', to: '/work#ai-video' },
  },
]
