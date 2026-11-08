import { describe, it, expect } from "vitest";
import {
  personNode, websiteNode, breadcrumb, faqPage, blogPosting, creativeWork,
  serviceNode, profilePage, eventNode, graph, SAME_AS,
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
  profilePage(),
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
    expect(b.author).toEqual({ "@id": PERSON_ID });
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
    expect((profilePage() as any).mainEntity).toEqual({ "@id": PERSON_ID });
  });
  it("eventNode", () => {
    const e = eventNode({ name: "n", startDate: "2026-01-01", location: "L", organizer: "O", url: "u" }) as any;
    expect(e["@type"]).toBe("Event");
    expect(e.organizer.name).toBe("O");
    expect(e.location.name).toBe("L");
  });
  it("graph wraps nodes with single @context", () => {
    const g = graph(personNode(), websiteNode()) as any;
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
