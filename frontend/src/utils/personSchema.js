// Pure JSON-LD builders for the homepage — GEO levers G2 (Person entity) and
// G3 (FAQPage answer-shaped copy). No DOM / Vue deps so they're unit-testable in
// node. useMetaTags.js injects the output; Home.vue calls the injectors on mount.
//
// Facts sourced from the WHY-vault (10-Identity/ali.md, awards.md). When the live
// CMS provides a portrait or social links, pass them as overrides — otherwise the
// curated defaults below keep the entity graph consistent.

const SITE = 'https://alisadikinma.com'

const DEFAULT_SAME_AS = [
  'https://www.linkedin.com/in/alisadikinma/',
  'https://www.instagram.com/alisadikinma',
  'https://www.tiktok.com/@alisadikinma',
  'https://www.youtube.com/@alisadikinma',
  'https://github.com/alisadikinma',
]

/**
 * Schema.org Person entity for Ali Sadikin Ma.
 * @param {object} [opts]
 * @param {string} [opts.image]   live portrait URL (settings.about.profile_photo)
 * @param {string[]} [opts.sameAs] live social URLs (overrides curated defaults)
 */
export function buildPersonSchema(opts = {}) {
  const sameAs = Array.isArray(opts.sameAs) && opts.sameAs.length
    ? opts.sameAs
    : DEFAULT_SAME_AS

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'Person',
    name: 'Ali Sadikin Ma',
    alternateName: 'Ali Sadikin',
    url: SITE,
    jobTitle: 'AI Generalist',
    description:
      'Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products — not slide decks. 17 years building across 16 countries; ranked #1 at the Global AI Demo Day 2026. Now teaching AI Agents, Vibe Coding, and Generative Video.',
    knowsAbout: [
      'AI Agents',
      'AI Agent Operating Systems',
      'Vibe Coding',
      'Generative AI Video',
      'AI Automation',
      'Computer Vision',
      'Edge AI',
      'Large Language Model Applications',
    ],
    knowsLanguage: ['Indonesian', 'English', 'Mandarin'],
    nationality: { '@type': 'Country', name: 'Indonesia' },
    award: [
      '#1 Champion — Global AI Demo Day 2026 (Outskill AI Generalist Fellowship)',
      'UN-UNCTAD × Alibaba eFounders Fellowship 2019 (1 of 48 in Asia)',
      '1st Place — Telkomsel NextDev',
      'Wild Card Winner — Fenox Startup World Cup',
      'Top 8 Finalist — IDBYTE',
    ],
    alumniOf: [
      { '@type': 'Organization', name: 'UN-UNCTAD × Alibaba eFounders Fellowship' },
      { '@type': 'Organization', name: 'Google Startup Grind — Silicon Valley' },
    ],
    worksFor: { '@type': 'Organization', name: 'INDUSIA.ai', url: 'https://indusia.ai' },
    sameAs,
  }

  if (opts.image) schema.image = opts.image
  return schema
}

/**
 * FAQPage — answer-shaped Q/A that LLMs lift verbatim (G3). Questions mirror
 * what people actually ask: who is Ali, what he does, whether they can learn AI.
 */
export function buildFaqSchema() {
  const qa = [
    [
      'Who is Ali Sadikin Ma?',
      'Ali Sadikin Ma is an AI Generalist who turns frontier AI models into shipped products — not slide decks. With 17 years building across 16 countries, he was ranked #1 at the Global AI Demo Day 2026 and runs INDUSIA.ai.',
    ],
    [
      'What does Ali Sadikin Ma do?',
      'He builds and ships AI products across three disciplines: vibe coding (production software at AI speed), AI Agent operating systems (MANDOR AI), and generative AI video — and now teaches all three.',
    ],
    [
      'Can I learn AI from Ali Sadikin Ma?',
      'Yes. Ali teaches what he builds — AI Agents, Vibe Coding, and AI Video Generation — through hands-on courses and a public build log at alisadikinma.com.',
    ],
  ]

  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: qa.map(([q, a]) => ({
      '@type': 'Question',
      name: q,
      acceptedAnswer: { '@type': 'Answer', text: a },
    })),
  }
}
