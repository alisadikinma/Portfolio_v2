export type Node = Record<string, unknown>;

const SITE = "https://alisadikinma.com";
export const PERSON_ID = `${SITE}/#person`;
export const ORG_ID = "https://indusia.ai/#org";
const PERSON_REF = { "@id": PERSON_ID };

export const SAME_AS: string[] = [
  "https://www.instagram.com/alisadikinma",
  "https://www.tiktok.com/@alisadikinma",
  "https://www.linkedin.com/in/alisadikinma/",
  "https://www.threads.com/@alisadikinma",
  "https://www.youtube.com/@alisadikinma",
  "https://github.com/alisadikinma",
];

export function personNode(opts: { sameAs?: string[]; image?: string } = {}): Node {
  return {
    "@type": "Person",
    "@id": PERSON_ID,
    name: "Ali Sadikin Ma",
    alternateName: "Ali Sadikin",
    url: SITE,
    jobTitle: "AI Consultant",
    disambiguatingDescription:
      "Indonesian AI consultant and founder of INDUSIA.ai, based in Batam. Not the former Governor of Jakarta Ali Sadikin.",
    image: opts.image ?? `${SITE}/images/ali-sadikin-ma.jpg`,
    worksFor: { "@id": ORG_ID },
    founder: { "@id": ORG_ID },
    award: "1st Place, Global AI Demo Day 2026 (Outskill, Bengaluru)",
    knowsAbout: [
      "AI Agents", "AI Agent Operating Systems", "Vibe Coding", "Generative AI Video",
      "AI Automation", "Computer Vision", "Edge AI", "Large Language Model Applications",
    ],
    knowsLanguage: ["Indonesian", "English", "Mandarin"],
    sameAs: opts.sameAs ?? SAME_AS,
  };
}

export function websiteNode(): Node {
  return {
    "@type": "WebSite",
    "@id": `${SITE}/#website`,
    url: SITE,
    name: "Ali Sadikin Ma",
    inLanguage: ["id", "en"],
    publisher: PERSON_REF,
  };
}

export function breadcrumb(items: { name: string; url: string }[]): Node {
  return {
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: it.name,
      item: it.url,
    })),
  };
}

export function faqPage(qas: { q: string; a: string }[]): Node {
  return {
    "@type": "FAQPage",
    mainEntity: qas.map((x) => ({
      "@type": "Question",
      name: x.q,
      acceptedAnswer: { "@type": "Answer", text: x.a },
    })),
  };
}

export function blogPosting(p: {
  title: string; url: string; datePublished: string; dateModified?: string; image?: string; lang: string;
}): Node {
  const n: Node = {
    "@type": "BlogPosting",
    headline: p.title,
    mainEntityOfPage: p.url,
    datePublished: p.datePublished,
    dateModified: p.dateModified ?? p.datePublished,
    author: PERSON_REF,
    publisher: PERSON_REF,
    inLanguage: p.lang,
  };
  if (p.image) n.image = p.image;
  return n;
}

export function creativeWork(p: { name: string; url: string; description?: string; image?: string }): Node {
  const n: Node = { "@type": "CreativeWork", name: p.name, url: p.url, author: PERSON_REF };
  if (p.description) n.description = p.description;
  if (p.image) n.image = p.image;
  return n;
}

export function serviceNode(s: { name: string; url: string; lang: string }): Node {
  return {
    "@type": "Service",
    name: s.name,
    url: s.url,
    inLanguage: s.lang,
    provider: PERSON_REF,
    areaServed: "Indonesia",
  };
}

export function profilePage(): Node {
  return { "@type": "ProfilePage", mainEntity: PERSON_REF };
}

export function eventNode(a: {
  name: string; startDate: string; location: string; organizer: string; url?: string;
}): Node {
  const n: Node = {
    "@type": "Event",
    name: a.name,
    startDate: a.startDate,
    location: { "@type": "Place", name: a.location },
    organizer: { "@type": "Organization", name: a.organizer },
    performer: PERSON_REF,
  };
  if (a.url) n.url = a.url;
  return n;
}

export function graph(...nodes: Node[]): Node {
  return { "@context": "https://schema.org", "@graph": nodes };
}
