// Static content for the What I Solve tabbed switcher (Phase 3).
// Three disciplines, default selection = vibe-coding (first in array).
export const tabs = [
  {
    id: 'vibe-coding',
    label: 'Vibe Coding',
    icon: '⚡',
    headline:
      'I ship production code at AI speed — without sacrificing architecture quality.',
    metrics: [
      { value: '3 days', label: 'Idea → deployed' },
      { value: '27', label: 'Plugins shipped' },
      { value: '0', label: 'Prod regressions' },
    ],
    visual: '/images/showcases/vibe-coding-cursor.png',
    cta: { label: 'Case study', to: '/work#cursor-portfolio-v2' },
  },
  {
    id: 'ai-agents',
    label: 'AI Agents',
    icon: '🤖',
    headline:
      'Multi-agent systems that handle complex business workflows autonomously.',
    metrics: [
      { value: '8', label: 'Agents in production' },
      { value: '200+ hrs', label: 'Saved per week' },
      { value: '99.2%', label: 'Task completion' },
    ],
    visual: '/images/showcases/openclaw-orchestrator.png',
    cta: { label: 'See live demo', to: '/work#openclaw' },
  },
  {
    id: 'video-gen',
    label: 'Video Generation',
    icon: '🎬',
    headline:
      'Cinematic AI video at scale — for products, ads, and brand storytelling.',
    metrics: [
      { value: '50+', label: 'Videos shipped' },
      { value: 'VEO 3.1', label: '+ Kling AI + Seedance 2.0' },
      { value: '4K', label: 'Production quality' },
    ],
    visual: '/images/showcases/veo-kling-reel.png',
    cta: { label: 'Watch reel', to: '/work#video-reel' },
  },
]
