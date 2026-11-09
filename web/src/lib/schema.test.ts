import { describe, it, expect } from "vitest";
import {
  personNode, websiteNode, breadcrumb, faqPage, blogPosting, creativeWork,
  serviceNode, profilePage, eventNode, graph, SAME_AS, orgNode, ORG_ID,
} from "./schema";

const PERSON_ID = "https://alisadikinma.com/#person";
const ORG_REF = { "@id": "https://indusia.ai/#org" };

const all = () => [
  personNode(),
  websiteNode(),
  breadcrumb([{ name: "Home", url: "https://alisadikinma.com/en" }]),
  faqPage([{ q: "Q?", a: "A." }]),
  blogPosting({ title: "T", url: "https://alisadikinma.com/en/blog/t", datePublished: "2026-01-01", dateModified: "2026-02-01", image: "https://alisadikinma.com/i.jpg", lang: "en" }),
  creativeWork({ name: "P", url: "https://alisadikinma.com/en/projects/p", description: "d", image: "https://alisadikinma.com/p.jpg" }),
  serviceNode({ name: "AI Training", url: "https://alisadikinma.com/en/ai-training", lang: "en" }),
  profilePage({ url: "https://alisadikinma.com/en/about" }),
  eventNode({ name: "Global AI Demo Day 2026", startDate: "2026-01-01", location: "Bengaluru", organizer: "Outskill", url: "https://alisadikinma.com/en/awards/x" }),
];

describe("personNode", () => {
  it("has fixed identity facts", () => {
    const p = personNode() as Record<string, unknown>;
    expect(p["@type"]).toBe("Person");
    expect(p["@id"]).toBe(PERSON_ID);
    expect(p.name).toBe("Ali Sadikin Ma");
    expect(p.jobTitle).toBe("AI Consultant");
    expect(p.disambiguatingDescription).toBe(
      "Indonesian AI consultant and founder of INDUSIA.ai, based in Batam. Not the former Governor of Jakarta Ali Sadikin.",
    );
    expect(p.worksFor).toEqual(ORG_REF);
    expect(p.founder).toEqual(ORG_REF);
    expect(p.award).toBe("1st Place, Global AI Demo Day 2026 (Outskill, Bengaluru)");
    expect(typeof p.image).toBe("string");
    expect(Array.isArray(p.knowsAbout)).toBe(true);
    expect(p.sameAs).toEqual(SAME_AS);
  });
  it("accepts sameAs override", () => {
    expect((personNode({ sameAs: ["https://x.test"] }) as any).sameAs).toEqual(["https://x.test"]);
  });
  it("sameAs includes the six profiles", () => {
    for (const h of ["instagram.com/alisadikinma", "tiktok.com/@alisadikinma", "linkedin.com/in/alisadikinma", "threads.com/@alisadikinma", "youtube.com/@alisadikinma", "github.com/alisadikinma"]) {
      expect(SAME_AS.some((u) => u.includes(h))).toBe(true);
    }
  });
  it("never has foundingDate", () => {
    expect(JSON.stringify(personNode())).not.toContain("foundingDate");
  });
});

describe("other nodes", () => {
  it("blogPosting", () => {
    const b = all()[4] as any;
    expect(b["@type"]).toBe("BlogPosting");
    expect(b.headline).toBe("T");
    expect(b.author["@id"]).toBe(PERSON_ID);
    expect(b.inLanguage).toBe("en");
    expect(b.datePublished).toBe("2026-01-01");
    expect(b.dateModified).toBe("2026-02-01");
  });
  it("breadcrumb positions", () => {
    const b = breadcrumb([{ name: "A", url: "u1" }, { name: "B", url: "u2" }]) as any;
    expect(b.itemListElement.map((i: any) => i.position)).toEqual([1, 2]);
  });
  it("faqPage", () => {
    const f = faqPage([{ q: "Q", a: "A" }]) as any;
    expect(f["@type"]).toBe("FAQPage");
    expect(f.mainEntity[0].acceptedAnswer.text).toBe("A");
  });
  it("service has provider, areaServed, no offers", () => {
    const s = serviceNode({ name: "n", url: "u", lang: "id" }) as any;
    expect(s.provider).toEqual({ "@id": PERSON_ID });
    expect(s.areaServed).toBe("Indonesia");
    expect(s.offers).toBeUndefined();
  });
  it("profilePage mainEntity", () => {
    expect((profilePage({ url: "u" }) as any).mainEntity).toEqual({ "@id": PERSON_ID });
  });
  it("eventNode", () => {
    const e = eventNode({ name: "n", startDate: "2026-01-01", location: "L", organizer: "O", url: "u" }) as any;
    expect(e["@type"]).toBe("Event");
    expect(e.organizer.name).toBe("O");
    expect(e.location.name).toBe("L");
  });
  it("eventNode omits startDate and location when unknown, never guesses", () => {
    const e = eventNode({ name: "n", organizer: "O" }) as any;
    expect(e).not.toHaveProperty("startDate");
    expect(e).not.toHaveProperty("location");
    expect(e).not.toHaveProperty("attendee");
    expect(e.organizer).toEqual({ "@type": "Organization", name: "O" });
  });
  it("eventNode carries description, image and an attendee link to the Person", () => {
    const e = eventNode({ name: "n", organizer: "O", startDate: "2026-01-17", location: "Bengaluru, India", description: "d", image: "https://alisadikinma.com/i.jpg", attendee: true }) as any;
    expect(e.startDate).toBe("2026-01-17");
    expect(e.location).toEqual({ "@type": "Place", name: "Bengaluru, India", address: "Bengaluru, India" });
    expect(e.description).toBe("d");
    expect(e.image).toBe("https://alisadikinma.com/i.jpg");
    expect(e.attendee).toEqual({ "@id": PERSON_ID });
  });
  it("personNode award can be overridden for an award page", () => {
    expect((personNode({ award: "Top 8 Finalist" }) as any).award).toBe("Top 8 Finalist");
  });
  it("graph wraps nodes with single @context", () => {
    const g = graph(websiteNode(), breadcrumb([])) as any;
    expect(g["@context"]).toBe("https://schema.org");
    expect(g["@graph"]).toHaveLength(2);
    expect(g["@graph"][0]["@context"]).toBeUndefined();
  });
});

describe("invariants", () => {
  it("no aggregateRating or review anywhere, valid JSON", () => {
    const s = JSON.stringify(graph(...all()));
    expect(() => JSON.parse(s)).not.toThrow();
    expect(s).not.toContain("aggregateRating");
    expect(s).not.toContain('"review"');
  });
});

// ---- 1D review fixes ----
import { PORTRAIT_URL, jsonLdString, webPageNode, collectionPage } from "./schema";

describe("1D review fixes", () => {
  it("portrait url is the person image", () => {
    expect(PORTRAIT_URL).toBe("https://alisadikinma.com/images/ali-sadikin-ma-pakar-ai-indonesia.jpg");
    expect((personNode() as any).image).toBe(PORTRAIT_URL);
  });
  it("jsonLdString escapes script-breaking chars", () => {
    const s = jsonLdString(graph(blogPosting({ title: "</script><!-- & > \u2028\u2029", url: "u", datePublished: "2026-01-01", lang: "en" })));
    expect(s).not.toContain("</script>");
    expect(s).not.toContain("<!--");
    expect(s).not.toMatch(/[<>&\u2028\u2029]/);
    expect(JSON.parse(s)["@graph"][0].headline).toBe("</script><!-- & > \u2028\u2029");
  });
  it("webPageNode", () => {
    const w = webPageNode({ url: "https://alisadikinma.com/id", lang: "id", name: "N", description: "D" }) as any;
    expect(w["@type"]).toBe("WebPage");
    expect(w["@id"]).toBe("https://alisadikinma.com/id#webpage");
    expect(w.about).toEqual({ "@id": PERSON_ID });
    expect(w.mainEntity).toEqual({ "@id": PERSON_ID });
    expect(w.isPartOf).toEqual({ "@id": "https://alisadikinma.com/#website" });
    expect(w.inLanguage).toBe("id");
    expect(w.description).toBe("D");
    expect((webPageNode({ url: "u", lang: "en", name: "N" }) as any).description).toBeUndefined();
  });
  it("person: no alternateName, has spec fields", () => {
    const p = personNode() as any;
    expect(p.alternateName).toBeUndefined();
    expect(typeof p.description).toBe("string");
    expect(p.homeLocation).toEqual({
      "@type": "Place",
      address: { "@type": "PostalAddress", addressLocality: "Batam", addressRegion: "Kepulauan Riau", addressCountry: "ID" },
    });
    expect(p.hasOccupation["@type"]).toBe("Occupation");
    expect(p.hasOccupation.name).toBe("AI Consultant");
    expect(p.alumniOf.map((a: any) => a.name)).toEqual(["BINUS University", "Politeknik Caltex Riau"]);
    expect(p.alumniOf[0]["@type"]).toBe("CollegeOrUniversity");
  });
  it("service has no inLanguage", () => {
    expect((serviceNode({ name: "n", url: "u", lang: "id" }) as any).inLanguage).toBeUndefined();
  });
  it("event: address, eventStatus, no performer", () => {
    const e = eventNode({ name: "n", startDate: "2026-01-01", location: "Bengaluru", organizer: "O" }) as any;
    expect(e.performer).toBeUndefined();
    expect(e.eventStatus).toBe("https://schema.org/EventScheduled");
    expect(e.location.address).toBe("Bengaluru");
  });
  it("blogPosting: @id, description, author person node", () => {
    const b = blogPosting({ title: "T", url: "https://alisadikinma.com/en/blog/t", datePublished: "2026-01-01", lang: "en", description: "desc" }) as any;
    expect(b["@id"]).toBe("https://alisadikinma.com/en/blog/t#article");
    expect(b.description).toBe("desc");
    // SEO audit 2026-11-09: reference the one Person node, no inline copy.
    expect(b.author).toEqual({ "@id": PERSON_ID });
  });
  it("profilePage: url, @id, optional dateModified", () => {
    const p = profilePage({ url: "https://alisadikinma.com/id/about", dateModified: "2026-02-01" }) as any;
    expect(p.url).toBe("https://alisadikinma.com/id/about");
    expect(p["@id"]).toBe("https://alisadikinma.com/id/about#profilepage");
    expect(p.dateModified).toBe("2026-02-01");
    expect((profilePage({ url: "u" }) as any).dateModified).toBeUndefined();
    expect(p.mainEntity).toEqual({ "@id": PERSON_ID });
  });
  it("collectionPage: type, @id, isPartOf website, ItemList positions, optional parts", () => {
    const c = collectionPage({
      url: "https://alisadikinma.com/id/blog", lang: "id", name: "Blog", description: "d",
      items: [{ name: "A", url: "https://alisadikinma.com/id/blog/a" }, { name: "B", url: "https://alisadikinma.com/id/blog/b" }],
    }) as any;
    expect(c["@type"]).toBe("CollectionPage");
    expect(c["@id"]).toBe("https://alisadikinma.com/id/blog#collection");
    expect(c.isPartOf).toEqual({ "@id": "https://alisadikinma.com/#website" });
    expect(c.mainEntity.itemListElement.map((x: any) => x.position)).toEqual([1, 2]);
    const bare = collectionPage({ url: "u", lang: "en", name: "n" }) as any;
    expect(bare.description).toBeUndefined();
    expect(bare.mainEntity).toBeUndefined();
  });
});

describe("creativeWork creator", () => {
  it("references the Person node as creator and keeps author", () => {
    const c = creativeWork({ name: "P", url: "https://alisadikinma.com/en/projects/p" }) as any;
    expect(c.creator).toEqual({ "@id": PERSON_ID });
    expect(c.author).toEqual({ "@id": PERSON_ID });
  });
  it("carries @id, language, keywords, genre and the site for AI search (GEO)", () => {
    const c = creativeWork({
      name: "P", url: "https://alisadikinma.com/id/projects/p", lang: "id",
      keywords: ["Computer Vision", " ", "IoT"], genre: "AI",
    }) as any;
    expect(c["@id"]).toBe("https://alisadikinma.com/id/projects/p#work");
    expect(c.inLanguage).toBe("id");
    expect(c.keywords).toBe("Computer Vision, IoT");
    expect(c.genre).toBe("AI");
    expect(c.isPartOf).toEqual({ "@id": "https://alisadikinma.com/#website" });
  });
  it("omits keywords and genre when empty", () => {
    const c = creativeWork({ name: "P", url: "https://alisadikinma.com/en/projects/p", keywords: [], genre: "" }) as any;
    expect(c.keywords).toBeUndefined();
    expect(c.genre).toBeUndefined();
  });
});

describe("graph: INDUSIA.ai organization + nationality", () => {
  it("adds the #org Organization node once when a node references it", () => {
    const g = graph(personNode(), websiteNode()) as any;
    const orgs = g["@graph"].filter((n: any) => n["@id"] === ORG_ID);
    expect(orgs).toHaveLength(1);
    expect(orgs[0]).toMatchObject({ "@type": "Organization", name: "INDUSIA.ai", url: "https://indusia.ai" });
    expect(orgs[0].founder).toEqual({ "@id": PERSON_ID });
  });
  it("does not add it when nothing references #org, nor twice", () => {
    expect(JSON.stringify(graph(websiteNode()))).not.toContain(ORG_ID);
    const g = graph(personNode(), orgNode()) as any;
    expect(g["@graph"].filter((n: any) => n["@id"] === ORG_ID)).toHaveLength(1);
  });
  it("Person carries nationality Indonesia as a Country", () => {
    expect(personNode().nationality).toEqual({ "@type": "Country", name: "Indonesia" });
  });
});


// SEO audit 2026-11-09: the INDUSIA.ai node mirrors the facts indusia.ai publishes about itself.
describe("orgNode (INDUSIA.ai)", () => {
  const o = orgNode() as any;
  it("carries logo, legal name, description, founding date, address and sameAs from indusia.ai", () => {
    expect(o["@id"]).toBe(ORG_ID);
    expect(o.name).toBe("INDUSIA.ai");
    expect(o.legalName).toBe("PT Indusia Kecerdasan Digital");
    expect(o.logo).toBe("https://indusia.ai/assets/logo-indusia-wordmark.png");
    expect(o.foundingDate).toBe("2026-01-01");
    expect(o.address.addressLocality).toBe("Kota Batam");
    expect(o.sameAs).toContain("https://www.linkedin.com/company/116164317/");
    expect(o.sameAs).toContain("https://www.wikidata.org/wiki/Q142076284");
    expect(o.description).toMatch(/AI/);
  });
});

describe("serviceNode / profilePage enrichment", () => {
  it("service gets an @id and an optional description", () => {
    const s = serviceNode({ name: "AI Training", url: "https://alisadikinma.com/en/ai-training", lang: "en", description: "d" }) as any;
    expect(s["@id"]).toBe("https://alisadikinma.com/en/ai-training#service");
    expect(s.description).toBe("d");
  });
  it("profile page gets name, description, language and isPartOf when given", () => {
    const p = profilePage({ url: "https://alisadikinma.com/id/about", name: "N", description: "D", lang: "id" }) as any;
    expect(p.name).toBe("N");
    expect(p.description).toBe("D");
    expect(p.inLanguage).toBe("id");
    expect(p.isPartOf).toEqual({ "@id": "https://alisadikinma.com/#website" });
  });
});
