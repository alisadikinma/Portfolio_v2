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

// ---- 1F review fixes ----
import { robotsBlocks, parseHtml } from "./seo-gate.mjs";
import { basicSpec, TITLES, overlongTitles, TITLE_MAX } from "./seo-gate.config.mjs";

const FOOT = '<a href="https://indusia.ai/id/">Founder INDUSIA.ai</a>';
const wrapFoot = (open, close) => mutate(FOOT, `${open}${FOOT}${close}`);
const hasBacklinkFail = (html, headers) => checkHtml(html, spec, headers).some((f) => f.item === "backlinks");

describe("backlinks: hidden / noscript / template", () => {
  const cases = {
    noscript: ["<noscript>", "</noscript>"],
    template: ["<template>", "</template>"],
    "hidden attr ancestor": ["<div hidden>", "</div>"],
    "aria-hidden ancestor": ['<div aria-hidden="true">', "</div>"],
    "display:none ancestor": ['<div style="color:red; display: none">', "</div>"],
    "visibility:hidden ancestor": ['<span style="visibility:hidden">', "</span>"],
  };
  for (const [name, [o, c]] of Object.entries(cases))
    it(`does not count a footer link inside ${name}`, () => expect(hasBacklinkFail(wrapFoot(o, c))).toBe(true));
  it("does not count a hidden anchor itself", () => {
    expect(hasBacklinkFail(mutate(FOOT, FOOT.replace("<a ", '<a hidden ')))).toBe(true);
    expect(hasBacklinkFail(mutate(FOOT, FOOT.replace("<a ", '<a style="display:none" ')))).toBe(true);
  });
  it("visible links still pass", () => expect(hasBacklinkFail(good)).toBe(false));
});

describe("page-level robots", () => {
  it("meta nofollow fails backlinks", () => {
    expect(hasBacklinkFail(mutate('content="index,follow"', 'content="index,nofollow"'))).toBe(true);
  });
  it("meta none fails backlinks and noindex", () => {
    const html = mutate('content="index,follow"', 'content="none"');
    expect(items(html)).toEqual(expect.arrayContaining(["backlinks", "noindex"]));
  });
  it("X-Robots-Tag nofollow fails backlinks", () => {
    expect(hasBacklinkFail(good, { "x-robots-tag": "nofollow" })).toBe(true);
  });
  it("X-Robots-Tag noindex fails noindex", () => {
    expect(items(good, spec)).not.toContain("noindex");
    expect(checkHtml(good, spec, { "x-robots-tag": "noindex" }).some((f) => f.item === "noindex")).toBe(true);
  });
  it("accepts a Headers object", () => {
    expect(checkHtml(good, spec, new Headers({ "X-Robots-Tag": "noindex" })).some((f) => f.item === "noindex")).toBe(true);
  });
});

describe("robotsBlocks (RFC 9309)", () => {
  it("groups consecutive user-agent lines", () => {
    const t = "User-agent: GPTBot\nUser-agent: Googlebot\nDisallow: /private\n\nUser-agent: *\nDisallow:\n";
    expect(robotsBlocks(t, "/private/x", "GPTBot")).toBe(true);
    expect(robotsBlocks(t, "/private/x", "Googlebot")).toBe(true);
    expect(robotsBlocks(t, "/private/x", "*")).toBe(false);
  });
  it("a specific group overrides *", () => {
    const t = "User-agent: *\nDisallow: /\n\nUser-agent: GPTBot\nAllow: /\n";
    expect(robotsBlocks(t, "/id", "*")).toBe(true);
    expect(robotsBlocks(t, "/id", "GPTBot")).toBe(false);
    expect(robotsBlocks(t, "/id", "Googlebot")).toBe(true);
  });
  it("longest match wins, allow wins ties", () => {
    const t = "User-agent: *\nDisallow: /id\nAllow: /id/about\n";
    expect(robotsBlocks(t, "/id/about", "*")).toBe(false);
    expect(robotsBlocks(t, "/id/work", "*")).toBe(true);
    expect(robotsBlocks("User-agent: *\nDisallow: /a\nAllow: /a\n", "/a", "*")).toBe(false);
  });
  it("supports * and $", () => {
    const t = "User-agent: *\nDisallow: /*.pdf$\n";
    expect(robotsBlocks(t, "/x/y.pdf", "*")).toBe(true);
    expect(robotsBlocks(t, "/x/y.pdf?a", "*")).toBe(false);
  });
  it("empty disallow blocks nothing; no group blocks nothing", () => {
    expect(robotsBlocks("User-agent: *\nDisallow:\n", "/id", "*")).toBe(false);
    expect(robotsBlocks("", "/id", "*")).toBe(false);
  });
  it("ignores comments and is case-insensitive on field names/agent", () => {
    expect(robotsBlocks("USER-AGENT: gptbot # x\nDISALLOW: /id # y\n", "/id", "GPTBot")).toBe(true);
  });
});

describe("x-default", () => {
  it("must equal SITE + alternates.en", () => {
    const html = mutate('hreflang="x-default" href="https://alisadikinma.com/en"', 'hreflang="x-default" href="https://alisadikinma.com/id"');
    expect(checkHtml(html, spec).some((f) => f.item === "canonical-hreflang" && /x-default/.test(f.detail))).toBe(true);
  });
});

describe("parseHtml implicit p close", () => {
  it("closes an open <p> when a block tag opens", () => {
    const { els } = parseHtml("<p>one<div>two</div><ul><li>x</li></ul><p>three");
    const ps = els.filter((e) => e.tag === "p");
    expect(ps[0].text).toBe("one");
    expect(ps[0].children).toEqual([]);
    expect(els.find((e) => e.tag === "div").parent.tag).not.toBe("p");
  });
  it("lede is not inflated by an unclosed <p> swallowing following blocks", () => {
    const html = good.replace(/<p>Ali Sadikin Ma adalah[\s\S]*?<\/p>/, "<p>Pendek sekali<div>" + "kata ".repeat(40) + "</div>");
    expect(checkHtml(html, spec).some((f) => f.item === "lede")).toBe(true);
  });
});

describe("recursive rating scan", () => {
  it("fails on a nested aggregateRating", () => {
    const html = mutate('{"@type":"WebPage","@id":"https://alisadikinma.com/id#webpage"', '{"@type":"WebPage","mentions":{"x":[{"aggregateRating":{"ratingValue":5}}]},"@id":"https://alisadikinma.com/id#webpage"');
    expect(items(html)).toContain("jsonld");
  });
  it("fails on review on a non-Person node", () => {
    const html = mutate('{"@type":"WebPage"', '{"@type":"WebPage","review":[]');
    expect(items(html)).toContain("jsonld");
  });
});

describe("internal links", () => {
  it("normalizes trailing slashes (same page counted once)", () => {
    const html = mutate('Lihat <a href="/id/work">karya saya</a> dan <a href="/id/pelatihan-ai">pelatihan AI</a>.', 'Lihat <a href="/id/work">a</a> <a href="/id/work/">b</a>.');
    expect(items(html)).toContain("internal-links");
  });
  it("route with trailing slash is not 'another page'", () => {
    const html = mutate('Lihat <a href="/id/work">karya saya</a> dan <a href="/id/pelatihan-ai">pelatihan AI</a>.', 'Lihat <a href="/id/">a</a> <a href="/id/work">b</a>.');
    expect(items(html)).toContain("internal-links");
  });
  it("excludes /api/ and /storage/", () => {
    const html = mutate('Lihat <a href="/id/work">karya saya</a> dan <a href="/id/pelatihan-ai">pelatihan AI</a>.', 'Lihat <a href="/id/work">a</a> <a href="/api/posts">b</a> <a href="/storage/x.pdf">c</a>.');
    expect(items(html)).toContain("internal-links");
  });
});

describe("primary keyword", () => {
  const multi = { ...spec, keywords: ["pakar AI Indonesia", "ahli AI"] };
  it("title missing keywords[0] fails even if another keyword is present", () => {
    const html = mutate("<title>Ali Sadikin Ma — Pakar AI Indonesia, Founder INDUSIA.ai</title>", "<title>Ali Sadikin Ma — Ahli AI, Founder INDUSIA.ai</title>");
    expect(items(html, multi)).toContain("title");
  });
  it("h1 missing keywords[0] fails", () => {
    expect(items(mutate("<h1>Ali Sadikin Ma — Pakar AI Indonesia</h1>", "<h1>Ali Sadikin Ma — Ahli AI</h1>"), multi)).toContain("h1");
  });
  it("good passes with extra keywords", () => expect(checkHtml(good, multi)).toEqual([]));
});

describe("SITE escaping in reciprocal regex", () => {
  it("source has no raw SITE interpolated into RegExp", async () => {
    const src = readFileSync(new URL("./seo-gate.mjs", import.meta.url), "utf8");
    expect(src).toMatch(/escapeRe/);
    expect(src).not.toMatch(/new RegExp\(`[^`]*\$\{SITE\}/);
  });
});

describe("og:image dimensions", () => {
  const og = '<meta property="og:image" content="https://alisadikinma.com/og/ali-sadikin-ma-pakar-ai-indonesia.jpg">';
  const withDims = (w, h) => mutate(og, `${og}<meta property="og:image:width" content="${w}"><meta property="og:image:height" content="${h}">`);
  it("passes at 1200x630", () => expect(checkHtml(withDims(1200, 630), spec)).toEqual([]));
  it("fails when too small", () => {
    expect(items(withDims(600, 315))).toContain("og");
    expect(items(withDims(1200, 600))).toContain("og");
  });
  it("absent dimensions are fine", () => expect(items(good)).not.toContain("og"));
});

describe("coverage specs", () => {
  it("basicSpec always includes the footer backlink", () => {
    const s = basicSpec("id", "/id/faq", { id: "/id/faq", en: "/en/faq" }, ["FAQ"], "FAQPage");
    expect(s.backlinks).toEqual([{ anchor: "Founder INDUSIA.ai", href: "https://indusia.ai/id/" }]);
    expect(s.pageType).toBe("FAQPage");
    expect(basicSpec("en", "/en/blog", { id: "/id/blog", en: "/en/blog" }, ["Blog"], "WebPage").backlinks[0].href).toBe("https://indusia.ai/");
  });
  it("faq, blog, projects, work exist for both languages", () => {
    const routes = ROUTES.all.map((r) => r.route);
    for (const p of ["faq", "blog", "projects", "work"])
      for (const l of ["id", "en"]) expect(routes).toContain(`/${l}/${p}`);
  });
});

describe("titles in config", () => {
  it("TITLES covers every route and the length rule lives in config", () => {
    for (const r of ROUTES.all) expect(typeof TITLES[r.route], r.route).toBe("string");
    expect(TITLE_MAX).toBe(60);
    expect(overlongTitles({ a: "x".repeat(61), b: "ok" })).toEqual(["a"]);
  });
  it("checkHtml uses TITLE_MAX", () => {
    const long = "Ali Sadikin Ma — Pakar AI Indonesia " + "x".repeat(TITLE_MAX);
    expect(checkHtml(mutate("Ali Sadikin Ma — Pakar AI Indonesia, Founder INDUSIA.ai</title>", long + "</title>"), spec).some((f) => f.item === "title")).toBe(true);
  });
});

import { vi, afterEach } from "vitest";
import { runCli } from "./seo-gate.mjs";

describe("runCli", () => {
  afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks(); });
  const run = async (handler) => {
    const calls = [];
    vi.stubGlobal("fetch", async (url, init) => { calls.push({ url, init }); return handler(String(url)); });
    const log = vi.spyOn(console, "log").mockImplementation(() => {});
    const code = await runCli(["--base", "http://x", "--routes", "home"]);
    return { code, out: log.mock.calls.map((c) => c[0]).join("\n"), calls };
  };
  const page = (url) => new Response(url.endsWith("/en") ? good.replace('lang="id"', 'lang="en"') : good, { status: 200 });
  const okRobots = "User-agent: *\nAllow: /\n";
  const sitemap = `<urlset><url><loc>https://alisadikinma.com/id</loc></url><url><loc>https://alisadikinma.com/en</loc></url></urlset>`;

  it("fails item 12 when robots.txt is not 200", async () => {
    const r = await run((u) => u.endsWith("/robots.txt") ? new Response("x", { status: 500 }) : u.endsWith("sitemap.xml") ? new Response(sitemap) : page(u));
    expect(r.out).toMatch(/\* \| noindex \| robots\.txt status 500/);
    expect(r.code).toBe(1);
  });
  it("fails item 12 when sitemap is empty", async () => {
    const r = await run((u) => u.endsWith("/robots.txt") ? new Response(okRobots) : u.endsWith("sitemap.xml") ? new Response("") : page(u));
    expect(r.out).toMatch(/noindex \| sitemap\.xml status 200, empty body/);
  });
  it("fails when robots.txt blocks GPTBot specifically", async () => {
    const r = await run((u) => u.endsWith("/robots.txt") ? new Response("User-agent: GPTBot\nDisallow: /id\n") : u.endsWith("sitemap.xml") ? new Response(sitemap) : page(u));
    expect(r.out).toMatch(/\/id \| noindex \| robots\.txt disallows route for GPTBot/);
  });
  it("every fetch has a timeout signal and headers are forwarded (X-Robots-Tag noindex)", async () => {
    const r = await run((u) => u.endsWith("/robots.txt") ? new Response(okRobots) : u.endsWith("sitemap.xml") ? new Response(sitemap)
      : new Response(page(u).body, { status: 200, headers: { "X-Robots-Tag": "noindex" } }));
    for (const c of r.calls) expect(c.init.signal).toBeInstanceOf(AbortSignal);
    expect(r.out).toMatch(/\/id \| noindex \| X-Robots-Tag: noindex/);
  });
  it("passes when all is healthy", async () => {
    const r = await run((u) => u.endsWith("/robots.txt") ? new Response(okRobots) : u.endsWith("sitemap.xml") ? new Response(sitemap) : page(u));
    expect(r.out).not.toMatch(/robots\.txt|sitemap/);
  });
});
