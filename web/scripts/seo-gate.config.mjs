// Per-route SEO/GEO gate config. Source: brief section 3 (keyword map) and the
// spec backlink table (docs/plans/2026-11-08-PF-2-portfolio-redesign-seo-spec.md).
export const SITE = "https://alisadikinma.com";
export const PERSON_ID = `${SITE}/#person`;
// Spec gate item 7 uses "#org" (brief section 5 says "#organization"). Spec wins; one place to change.
export const ORG_ID = "https://indusia.ai/#org";
export const NAME = "Ali Sadikin Ma";
export const TITLE_MAX = 60;

// Single place for page titles (brief section 3). The title-length check and the
// config tests read from here; edit a title here when the owner decides.
export const TITLES = {
  "/id": "Ali Sadikin Ma — Pakar AI Indonesia, Founder INDUSIA.ai",
  "/en": "Ali Sadikin Ma — AI Consultant Indonesia, INDUSIA.ai",
  "/id/about": "Tentang Ali Sadikin Ma — Ahli AI Indonesia dari Batam",
  "/en/about": "About Ali Sadikin Ma — AI Expert from Batam",
  "/id/pelatihan-ai": "Pelatihan AI & Workshop AI untuk Perusahaan — Ali Sadikin Ma",
  "/en/ai-training": "AI Training Indonesia for Companies — Ali Sadikin Ma",
  "/id/pembicara-ai": "Pembicara AI & Narasumber Seminar AI — Ali Sadikin Ma",
  "/en/ai-speaker": "AI Speaker Indonesia — Ali Sadikin Ma",
  "/id/faq": "FAQ Ali Sadikin Ma — Pertanyaan Umum",
  "/en/faq": "FAQ — Ali Sadikin Ma",
  "/id/blog": "Blog AI — Ali Sadikin Ma",
  "/en/blog": "AI Blog — Ali Sadikin Ma",
  "/id/projects": "Proyek AI — Ali Sadikin Ma",
  "/en/projects": "AI Projects — Ali Sadikin Ma",
  "/id/work": "Karya — Ali Sadikin Ma",
  "/en/work": "Work — Ali Sadikin Ma",
  "/id/contact": "Kontak Ali Sadikin Ma — Konsultasi & Kerja Sama AI",
  "/en/contact": "Contact Ali Sadikin Ma — AI Consulting & Partnerships",
};

/** Routes whose configured title exceeds `max` characters. */
export function overlongTitles(titles = TITLES, max = TITLE_MAX) {
  return Object.keys(titles).filter((r) => titles[r].length > max);
}

const I = "https://indusia.ai";

// ID anchors/targets are verbatim from the spec table. EN anchors are English
// equivalents (spec lists ID only) and EN targets are the unprefixed indusia.ai paths.
const A = {
  id: {
    hero: { anchor: "INDUSIA.ai", href: `${I}/id/` },
    company: { anchor: "perusahaan AI Indonesia", href: `${I}/id/` },
    devServices: { anchor: "jasa pembuatan AI", href: `${I}/id/ai-development-services/` },
    devServicesTraining: { anchor: "jasa pembuatan AI INDUSIA", href: `${I}/id/ai-development-services/` },
    vision: { anchor: "machine vision Indonesia", href: `${I}/id/ai-vision/` },
    fleet: { anchor: "fleet management system", href: `${I}/id/ai-fleet-transportation/` },
    chatbot: { anchor: "chatbot WhatsApp AI", href: `${I}/id/ai-assistant/` },
    speakerBio: { anchor: "founder INDUSIA.ai", href: `${I}/id/` },
    footer: { anchor: "Founder INDUSIA.ai", href: `${I}/id/` },
  },
  en: {
    hero: { anchor: "INDUSIA.ai", href: `${I}/` },
    company: { anchor: "AI company Indonesia", href: `${I}/` },
    devServices: { anchor: "AI development services Indonesia", href: `${I}/ai-development-services/` },
    devServicesTraining: { anchor: "INDUSIA AI development services Indonesia", href: `${I}/ai-development-services/` },
    vision: { anchor: "machine vision Indonesia", href: `${I}/ai-vision/` },
    fleet: { anchor: "fleet management system", href: `${I}/ai-fleet-transportation/` },
    chatbot: { anchor: "WhatsApp AI chatbot", href: `${I}/ai-assistant/` },
    speakerBio: { anchor: "founder INDUSIA.ai", href: `${I}/` },
    footer: { anchor: "Founder INDUSIA.ai", href: `${I}/` },
  },
};

function page(lang, route, alt, o) {
  const a = A[lang];
  return {
    route,
    lang,
    alternates: { id: alt.id, en: alt.en },
    keywords: o.keywords,
    pageType: o.pageType,
    isHome: !!o.isHome,
    faq: !!o.faq,
    backlinks: [...o.links(a), a.footer],
  };
}

/** Exact-match INDUSIA anchors per language: the one source for components and the gate. */
export const ANCHORS = A;

const products = (a) => [a.vision, a.fleet, a.chatbot];

export const ROUTES = {
  home: [
    ...[["id", "/id", ["pakar AI Indonesia"]], ["en", "/en", ["AI consultant Indonesia"]]].map(([l, r, k]) =>
      page(l, r, { id: "/id", en: "/en" }, {
        keywords: k, pageType: "WebPage", isHome: true,
        links: (a) => [a.hero, a.company, a.devServices, ...products(a)],
      })),
  ],
  about: [
    ...[["id", "/id/about", ["ahli AI Indonesia"]], ["en", "/en/about", ["AI expert"]]].map(([l, r, k]) =>
      page(l, r, { id: "/id/about", en: "/en/about" }, {
        keywords: k, pageType: "ProfilePage",
        links: (a) => [a.hero, a.company, ...products(a)],
      })),
  ],
  training: [
    page("id", "/id/pelatihan-ai", { id: "/id/pelatihan-ai", en: "/en/ai-training" }, {
      keywords: ["pelatihan AI", "workshop AI", "training AI"], pageType: "Service", faq: true,
      links: (a) => [a.devServicesTraining],
    }),
    page("en", "/en/ai-training", { id: "/id/pelatihan-ai", en: "/en/ai-training" }, {
      keywords: ["AI training Indonesia"], pageType: "Service", faq: true,
      links: (a) => [a.devServicesTraining],
    }),
  ],
  speaker: [
    page("id", "/id/pembicara-ai", { id: "/id/pembicara-ai", en: "/en/ai-speaker" }, {
      keywords: ["pembicara AI", "seminar AI"], pageType: "Service",
      links: (a) => [a.speakerBio],
    }),
    page("en", "/en/ai-speaker", { id: "/id/pembicara-ai", en: "/en/ai-speaker" }, {
      keywords: ["AI speaker Indonesia"], pageType: "Service",
      links: (a) => [a.speakerBio],
    }),
  ],
};

/** Minimal spec for pages without a backlink table: only the footer backlink "Founder INDUSIA.ai". */
// Content pages (posts, projects, post collections) legitimately quote prices.
const PRICE_EXEMPT_TYPES = new Set(["BlogPosting", "CreativeWork", "CollectionPage"]);

export function basicSpec(lang, route, alternates, keywords, pageType) {
  return {
    route, lang, alternates: { id: alternates.id, en: alternates.en }, keywords, pageType,
    isHome: false, faq: false, checkPrice: !PRICE_EXEMPT_TYPES.has(pageType),
    backlinks: [A[lang].footer],
  };
}

// Keywords are the page name (brief section 3 gives no keyword for these pages).
const KW = {
  faq: { id: ["FAQ"], en: ["FAQ"] },
  blog: { id: ["Blog"], en: ["Blog"] },
  projects: { id: ["Proyek"], en: ["Projects"] },
  work: { id: ["Karya"], en: ["Work"] },
  contact: { id: ["Kontak"], en: ["Contact"] },
};
const basicGroup = (name, pageType) =>
  ["id", "en"].map((l) =>
    basicSpec(l, `/${l}/${name}`, { id: `/id/${name}`, en: `/en/${name}` }, KW[name][l], pageType));
ROUTES.faq = basicGroup("faq", "FAQPage");
ROUTES.blog = basicGroup("blog", "WebPage");
ROUTES.projects = basicGroup("projects", "WebPage");
ROUTES.work = basicGroup("work", "WebPage");
ROUTES.contact = basicGroup("contact", "ContactPage");

ROUTES.all = [
  ...ROUTES.home, ...ROUTES.about, ...ROUTES.training, ...ROUTES.speaker,
  ...ROUTES.faq, ...ROUTES.blog, ...ROUTES.projects, ...ROUTES.work, ...ROUTES.contact,
];

// Blog posts: every post has an author box. Build a spec per post with this.
// titleName:false — Ali 2026-11-08: post <title> = meta_title as-is (keyword first, as on the old site);
// the name is carried by the author box, byline and BlogPosting.author instead.
export function blogPostSpec(lang, route, alt, keyword) {
  const a = A[lang];
  return {
    route, lang, alternates: alt, keywords: [keyword], pageType: "BlogPosting", isHome: false, faq: false, checkPrice: false, titleName: false,
    backlinks: [a.speakerBio, a.company, a.footer],
  };
}

// Award pages (/id/penghargaan/{slug}, /en/awards/{slug}): Event schema, footer backlink only.
// Keyword = the award's main words; the page title leads with the award name and ends with the person name.
export function awardSpec(lang, slug, keyword) {
  return basicSpec(
    lang,
    `/${lang}/${lang === "id" ? "penghargaan" : "awards"}/${slug}`,
    { id: `/id/penghargaan/${slug}`, en: `/en/awards/${slug}` },
    [keyword],
    "Event",
  );
}

// The 6 awards on the live site (slugs = backfill of the current titles, migration 2026_11_08_000001).
export const AWARD_PAGES = [
  ["1st-place-winner-global-ai-demo-day-2026-outskill-ai-generalist-fellowship", "Global AI Demo Day"],
  ["alibaba-efounders-fellowship-hangzhou-china", "Alibaba efounders"],
  ["1st-place-winner-nextdev-startup-competition", "Nextdev"],
  ["1st-place-winner-wild-card-fenox-startup-world-cup-competition", "Fenox Startup"],
  ["top-8-finalist-idbyte-2017-connected", "IDBYTE"],
  ["google-startup-grind-silicon-valley-san-fransisco", "Google Startup Grind"],
];
ROUTES.award = AWARD_PAGES.flatMap(([slug, kw]) => ["id", "en"].map((l) => awardSpec(l, slug, kw)));
