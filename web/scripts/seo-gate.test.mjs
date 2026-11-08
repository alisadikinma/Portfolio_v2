import { describe, it, expect } from "vitest";
import { readFileSync } from "node:fs";
import { checkHtml, GATE_ITEMS } from "./seo-gate.mjs";
import { ROUTES } from "./seo-gate.config.mjs";

const read = (f) => readFileSync(new URL(`./fixtures/${f}`, import.meta.url), "utf8");
const good = read("good.html");
const bad = read("bad.html");
const spec = ROUTES.home.find((r) => r.route === "/id");
const items = (html, s = spec) => [...new Set(checkHtml(html, s).map((f) => f.item))];
const mutate = (from, to) => {
  expect(good).toContain(from);
  return good.replace(from, to);
};

describe("seo-gate checkHtml", () => {
  it("has the 12 spec gate items", () => expect(GATE_ITEMS).toHaveLength(12));

  it("good.html passes", () => expect(checkHtml(good, spec)).toEqual([]));

  it("bad.html fails every one of the 12 items", () => {
    const got = items(bad);
    for (const item of GATE_ITEMS) expect(got, item).toContain(item);
  });

  const cases = {
    lang: ['<html lang="id">', '<html lang="en">'],
    title: ["<title>Ali Sadikin Ma — Pakar AI Indonesia, Founder INDUSIA.ai</title>", "<title>Beranda</title>"],
    description: ['content="Ali Sadikin Ma, pakar AI Indonesia dan founder INDUSIA.ai di Batam. Konsultasi AI, machine vision, fleet management, dan chatbot WhatsApp untuk perusahaan."', 'content="Pendek."'],
    h1: ["<h1>Ali Sadikin Ma — Pakar AI Indonesia</h1>", "<h1>Ali Sadikin Ma</h1>"],
    "canonical-hreflang": ['<link rel="alternate" hreflang="en" href="https://alisadikinma.com/en">', ""],
    jsonld: ['"worksFor":{"@id":"https://indusia.ai/#org"},"founder":{"@id":"https://indusia.ai/#org"}', '"name":"x"'],
    og: ['<meta property="og:image" content="https://alisadikinma.com/og/ali-sadikin-ma-pakar-ai-indonesia.jpg">', ""],
    img: ['alt="Ali Sadikin Ma, pakar AI Indonesia dan founder INDUSIA.ai" width="800" height="1000"', 'width="800" height="1000"'],
    backlinks: ['<a href="https://indusia.ai/id/">Founder INDUSIA.ai</a>', "Founder INDUSIA.ai"],
    "internal-links": ['Lihat <a href="/id/work">karya saya</a> dan <a href="/id/pelatihan-ai">pelatihan AI</a>.', "Lihat karya saya."],
    noindex: ['content="index,follow"', 'content="noindex"'],
  };
  for (const [item, [from, to]] of Object.entries(cases)) {
    it(`fails alone on ${item}`, () => {
      const html = mutate(from, to);
      expect(items(html)).toContain(item);
    });
  }

  it("lede mutation removes the 25-word paragraph", () => {
    const html = good.replace(/<p>Ali Sadikin Ma adalah[\s\S]*?<\/p>\n<p>Kami adalah[\s\S]*?<\/p>/, "<p>Pendek.</p>");
    expect(checkHtml(html, spec).some((f) => f.item === "lede")).toBe(true);
  });

  describe("backlinks", () => {
    const foot = '<a href="https://indusia.ai/id/">Founder INDUSIA.ai</a>';
    it("fails on rel=nofollow", () => {
      const f = checkHtml(mutate(foot, foot.replace("<a ", '<a rel="nofollow" ')), spec);
      expect(f.find((x) => x.item === "backlinks").detail).toMatch(/not dofollow/);
    });
    for (const rel of ["ugc", "sponsored", "noopener nofollow"])
      it(`fails on rel=${rel}`, () => {
        expect(items(mutate(foot, foot.replace("<a ", `<a rel="${rel}" `)))).toContain("backlinks");
      });
    it("fails on wrong anchor", () => {
      const f = checkHtml(mutate(foot, foot.replace("Founder INDUSIA.ai", "klik di sini")), spec);
      expect(f.find((x) => x.item === "backlinks").detail).toMatch(/wrong anchor/);
    });
    it("fails on wrong target", () => {
      const f = checkHtml(mutate(foot, foot.replace("indusia.ai/id/", "indusia.ai/")), spec);
      expect(f.find((x) => x.item === "backlinks").detail).toMatch(/missing link|wrong anchor/);
    });
    it("allows rel=noopener", () => {
      expect(checkHtml(mutate(foot, foot.replace("<a ", '<a rel="noopener" ')), spec)).toEqual([]);
    });
  });

  it("jsonld rejects aggregateRating on Person", () => {
    const html = mutate('"name":"Ali Sadikin Ma",', '"name":"Ali Sadikin Ma","aggregateRating":{"ratingValue":5},');
    expect(items(html)).toContain("jsonld");
  });

  it("jsonld requires the page-type node", () => {
    expect(items(mutate('"@type":"WebPage"', '"@type":"Thing"'))).toContain("jsonld");
  });

  it("title over 60 chars fails", () => {
    expect(items(mutate("Founder INDUSIA.ai</title>", "Founder INDUSIA.ai dan konsultan perusahaan besar</title>"))).toContain("title");
  });

  it("og:url must equal canonical", () => {
    expect(items(mutate('property="og:url" content="https://alisadikinma.com/id"', 'property="og:url" content="https://alisadikinma.com/en"'))).toContain("og");
  });

  it("extras: price text and FAQ first question keyword", () => {
    expect(items(mutate("<h1>", "<p>Mulai Rp 5.000.000</p><h1>"))).toContain("no-price");
    const faqSpec = { ...spec, faq: true };
    expect(checkHtml(good, faqSpec)).toEqual([]);
    expect(items(mutate("Siapa pakar AI Indonesia ini?", "Siapa dia?"), faqSpec)).toContain("faq");
  });
});

describe("seo-gate config", () => {
  it("every route spec has keywords, backlinks incl. footer, and ID/EN alternates", () => {
    for (const r of ROUTES.all) {
      expect(r.keywords.length).toBeGreaterThan(0);
      expect(r.backlinks.some((b) => b.anchor === "Founder INDUSIA.ai")).toBe(true);
      expect(r.alternates.id).toMatch(/^\/id/);
      expect(r.alternates.en).toMatch(/^\/en/);
      for (const b of r.backlinks) expect(b.href.startsWith("https://indusia.ai/")).toBe(true);
    }
  });
  it("EN pages link unprefixed indusia.ai, ID pages link /id/", () => {
    for (const r of ROUTES.all)
      for (const b of r.backlinks)
        expect(b.href.startsWith("https://indusia.ai/id/")).toBe(r.lang === "id");
  });
});
