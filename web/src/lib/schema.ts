export type Node = Record<string, unknown>;

export const SITE = "https://alisadikinma.com";
export const PERSON_ID = `${SITE}/#person`;
export const ORG_ID = "https://indusia.ai/#org";
const PERSON_REF = { "@id": PERSON_ID };
export const PORTRAIT_URL = `${SITE}/images/ali-sadikin-ma-pakar-ai-indonesia.jpg`;

/** JSON for inline <script type="application/ld+json">: escapes chars that can end the script element. */
export function jsonLdString(node: Node): string {
  return JSON.stringify(node)
    .replace(/</g, "\\u003c")
    .replace(/>/g, "\\u003e")
    .replace(/&/g, "\\u0026")
    .replace(/\u2028/g, "\\u2028")
    .replace(/\u2029/g, "\\u2029");
}

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
    url: SITE,
    jobTitle: "AI Consultant",
    disambiguatingDescription:
      "Indonesian AI consultant and founder of INDUSIA.ai, based in Batam. Not the former Governor of Jakarta Ali Sadikin.",
    description:
      "Ali Sadikin Ma is an Indonesian AI consultant based in Batam and founder of INDUSIA.ai, building production AI for industry.",
    homeLocation: {
      "@type": "Place",
      address: { "@type": "PostalAddress", addressLocality: "Batam", addressRegion: "Kepulauan Riau", addressCountry: "ID" },
    },
    hasOccupation: { "@type": "Occupation", name: "AI Consultant" },
    alumniOf: [
      { "@type": "CollegeOrUniversity", name: "BINUS University" },
      { "@type": "CollegeOrUniversity", name: "Politeknik Caltex Riau" },
    ],
    image: opts.image ?? PORTRAIT_URL,
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

export function webPageNode(p: { url: string; lang: string; name: string; description?: string }): Node {
  const n: Node = {
    "@type": "WebPage",
    "@id": `${p.url}#webpage`,
    url: p.url,
    name: p.name,
    inLanguage: p.lang,
    isPartOf: { "@id": `${SITE}/#website` },
    about: PERSON_REF,
    mainEntity: PERSON_REF,
  };
  if (p.description) n.description = p.description;
  return n;
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

export function collectionPage(p: {
  url: string; lang: string; name: string; description?: string; items?: { name: string; url: string }[];
}): Node {
  const n: Node = {
    "@type": "CollectionPage",
    "@id": `${p.url}#collection`,
    url: p.url,
    name: p.name,
    inLanguage: p.lang,
    isPartOf: { "@id": `${SITE}/#website` },
    about: PERSON_REF,
  };
  if (p.description) n.description = p.description;
  if (p.items && p.items.length > 0) {
    n.mainEntity = {
      "@type": "ItemList",
      itemListElement: p.items.map((it, i) => ({ "@type": "ListItem", position: i + 1, name: it.name, url: it.url })),
    };
  }
  return n;
}

export function blogPosting(p: {
  title: string; url: string; datePublished: string; dateModified?: string; image?: string; lang: string; description?: string;
}): Node {
  const n: Node = {
    "@type": "BlogPosting",
    "@id": `${p.url}#article`,
    headline: p.title,
    mainEntityOfPage: p.url,
    datePublished: p.datePublished,
    dateModified: p.dateModified ?? p.datePublished,
    author: { "@type": "Person", "@id": PERSON_ID, name: "Ali Sadikin Ma", url: SITE },
    publisher: PERSON_REF,
    inLanguage: p.lang,
  };
  if (p.description) n.description = p.description;
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
    provider: PERSON_REF,
    areaServed: "Indonesia",
  };
}

export function profilePage(p: { url: string; dateModified?: string }): Node {
  const n: Node = { "@type": "ProfilePage", "@id": `${p.url}#profilepage`, url: p.url, mainEntity: PERSON_REF };
  if (p.dateModified) n.dateModified = p.dateModified;
  return n;
}

export function eventNode(a: {
  name: string; startDate: string; location: string; organizer: string; url?: string;
}): Node {
  const n: Node = {
    "@type": "Event",
    name: a.name,
    startDate: a.startDate,
    eventStatus: "https://schema.org/EventScheduled",
    location: { "@type": "Place", name: a.location, address: a.location },
    organizer: { "@type": "Organization", name: a.organizer },
  };
  if (a.url) n.url = a.url;
  return n;
}

export function graph(...nodes: Node[]): Node {
  return { "@context": "https://schema.org", "@graph": nodes };
}
