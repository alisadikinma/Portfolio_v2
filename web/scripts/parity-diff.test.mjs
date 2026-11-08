import { describe, it, expect } from "vitest";
import { diffSeo, mapOldToNew } from "./parity-diff.mjs";

const page = ({ title = "Self Hosting Chinese Models | Ali Sadikin Ma", canon = "https://alisadikinma.com/blog/x", h1 = "Self Hosting Chinese Models",
  desc = "A description", og = "https://alisadikinma.com/a.jpg", robots = "index,follow", ld = [{ "@type": "BlogPosting" }, { "@type": "BreadcrumbList" }] } = {}) => `<!doctype html><html lang="en"><head>
<title>${title}</title><link rel="canonical" href="${canon}">
${desc ? `<meta name="description" content="${desc}">` : ""}${og ? `<meta property="og:image" content="${og}">` : ""}
<meta name="robots" content="${robots}">
<script type="application/ld+json">${JSON.stringify(ld)}</script></head><body>${h1 ? `<h1>${h1}</h1>` : ""}</body></html>`;
const ctx = { oldUrl: "https://alisadikinma.com/blog/x", newUrl: "http://127.0.0.1:4321/en/blog/x" };
const kinds = (r) => r.map((d) => d.kind);
const newCanon = "https://alisadikinma.com/en/blog/x";

describe("diffSeo", () => {
  it("passes on equivalent pages (language-prefixed canonical whitelisted)", () => {
    expect(diffSeo(page(), page({ canon: newCanon })).filter((d) => !d.whitelisted)).toEqual([]);
  });
  it("reports a dropped JSON-LD type, including inside @graph", () => {
    const o = page({ ld: { "@graph": [{ "@type": "BlogPosting" }, { "@type": ["Person", "Thing"] }, { "@type": "BreadcrumbList" }] } });
    const n = page({ canon: newCanon, ld: [{ "@type": "BlogPosting" }, { "@type": "BreadcrumbList" }] });
    const r = diffSeo(o, n);
    expect(r.find((d) => d.kind === "jsonld-type-dropped")).toMatchObject({ detail: expect.stringContaining("Person"), whitelisted: false });
  });
  it("reports changed canonical (not just a lang prefix)", () => {
    const r = diffSeo(page(), page({ canon: "https://alisadikinma.com/en/other" }));
    expect(kinds(r)).toContain("canonical-changed");
  });
  it("reports lost title keyword", () => {
    const r = diffSeo(page(), page({ canon: newCanon, title: "Something Else | Ali Sadikin Ma" }));
    expect(kinds(r)).toContain("title-keyword-lost");
  });
  it("ignores old-site title suffix", () => {
    const r = diffSeo(page({ title: "Alpha Beta Gamma | Ali Sadikin Ma" }), page({ canon: newCanon, title: "Alpha Beta Gamma" }));
    expect(kinds(r)).not.toContain("title-keyword-lost");
  });
  it("reports lost H1 and different H1", () => {
    expect(kinds(diffSeo(page(), page({ canon: newCanon, h1: "" })))).toContain("h1-lost");
    expect(kinds(diffSeo(page(), page({ canon: newCanon, h1: "Totally Different Words" })))).toContain("h1-lost");
  });
  it("reports lost description, og:image, noindex, breadcrumb", () => {
    const r = diffSeo(page(), page({ canon: newCanon, desc: "", og: "", robots: "noindex", ld: [{ "@type": "BlogPosting" }] }));
    expect(kinds(r)).toEqual(expect.arrayContaining(["description-lost", "og-image-lost", "robots-noindex", "breadcrumb-lost"]));
  });
  it("whitelists aggregateRating removal", () => {
    const o = page({ ld: [{ "@type": "BlogPosting", aggregateRating: { "@type": "AggregateRating" } }, { "@type": "BreadcrumbList" }] });
    const r = diffSeo(o, page({ canon: newCanon }));
    expect(r.filter((d) => !d.whitelisted)).toEqual([]);
    expect(r.some((d) => d.whitelisted)).toBe(true);
  });
});

describe("mapOldToNew", () => {
  it("prefixes language", () => {
    expect(mapOldToNew("/blog/x", "en")).toBe("/en/blog/x");
    expect(mapOldToNew("/blog/category/c", "id")).toBe("/id/blog/category/c");
    expect(mapOldToNew("/projects/p", "en")).toBe("/en/projects/p");
  });
});

describe("diffSeo: wave-13 whitelist", () => {
  const pc = { oldUrl: "https://alisadikinma.com/projects/asset-audit", newUrl: "http://127.0.0.1:4321/en/projects/asset-audit" };
  const pCanon = "https://alisadikinma.com/projects/asset-audit";
  it("whitelists the old site-wide FAQ block (FAQPage/Question/Answer)", () => {
    const o = page({ ld: [{ "@type": "BlogPosting" }, { "@type": "BreadcrumbList" }, { "@type": "FAQPage", mainEntity: [{ "@type": "Question", acceptedAnswer: { "@type": "Answer" } }] }] });
    const r = diffSeo(o, page({ canon: newCanon }));
    expect(r.filter((d) => !d.whitelisted)).toEqual([]);
    expect(r.filter((d) => d.kind === "jsonld-type-dropped")).toHaveLength(3);
  });
  it("whitelists the old project title category suffix, only on project URLs", () => {
    const o = page({ canon: pCanon, title: "Asset Audit - Web Platform" });
    const n = page({ canon: "https://alisadikinma.com/en/projects/asset-audit", title: "Asset Audit — Ali Sadikin Ma" });
    expect(diffSeo(o, n, pc).filter((d) => !d.whitelisted)).toEqual([]);
    expect(kinds(diffSeo(page({ title: "Asset Audit - Web Platform" }), page({ canon: newCanon, title: "Asset Audit" }), ctx))).toContain("title-keyword-lost");
  });
  it("still reports a lost project title word before the suffix", () => {
    const o = page({ canon: pCanon, title: "Asset Audit - Web Platform" });
    const n = page({ canon: "https://alisadikinma.com/en/projects/asset-audit", title: "Audit — Ali Sadikin Ma" });
    expect(kinds(diffSeo(o, n, pc))).toContain("title-keyword-lost");
  });
});
