import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import Work from "../pages/[lang]/work.astro";

const PROJECTS = [1, 2].map((n) => ({
  id: n, slug: `proj-${n}`, title: `Project ${n} <b>`, description: `Desc ${n}`, category: "AI Vision",
  featured_image: n === 1 ? "https://alisadikinma.com/storage/p1.jpg" : null,
}));
const AWARDS = [
  { id: 13, slug: "1st-place-demo-day", title: "1st Place Winner — Global AI Demo Day 2026", organization: "OUTSKILL - INDIA", received_at: "17 Jan 2026" },
  { id: 11, slug: "google-startup-grind-silicon-valley", title: "Google Startup Grind Silicon Valley - San Fransisco", organization: "Google - USA", received_at: "2018" },
  { id: 99, slug: null, title: "No Slug Award", organization: "X", received_at: "2020" },
];
const item = (n: number, over: Record<string, unknown> = {}) => ({
  id: n, type: "image", file_url: `https://alisadikinma.com/storage/g/p${n}.jpg`,
  image_variants: { "320w": `/storage/g/p${n}-320.webp`, "1920w": `/storage/g/p${n}-1920.webp` }, sequence: n, ...over,
});
const GALLERIES = [
  { id: 1, title: "Singapore <i>", company: "MNC", period: "2008", thumbnail: "https://alisadikinma.com/storage/g/t.png", items_count: 2, items: [item(1), item(2), item(3, { file_url: "https://evil.example/x.jpg" })] },
  { id: 2, title: "Empty one", thumbnail: null, items: [] },
];

let handler: (url: string) => Response;
const calls: string[] = [];
const ok = (data: unknown, meta: unknown = { last_page: 1 }) =>
  new Response(JSON.stringify({ success: true, data, meta }), { status: 200 });

beforeEach(() => {
  resetApiCache();
  calls.length = 0;
  handler = (url) => {
    if (url.includes("/projects")) return ok(PROJECTS);
    if (url.includes("/awards")) return ok(AWARDS);
    if (url.includes("/galleries")) return ok(GALLERIES);
    return new Response("nope", { status: 404 });
  };
  vi.stubGlobal("fetch", vi.fn(async (url: string) => (calls.push(String(url)), handler(String(url)))));
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, query = "") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(Work, {
    params: { lang },
    request: new Request(`https://alisadikinma.com/${lang}/work${query}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const spec = (lang: "id" | "en") => (ROUTES as unknown as { work: { lang: string }[] }).work.find((s) => s.lang === lang);

describe("/{lang}/work", () => {
  it.each(["", "?tab=awards", "?tab=gallery", "?tab=projects", "?tab=bogus"])("passes the SEO gate (id+en) for %s", async (q) => {
    for (const lang of ["id", "en"] as const) {
      const { status, html, headers } = await load(lang, q);
      expect(status).toBe(200);
      expect(checkHtml(html, spec(lang), headers)).toEqual([]);
    }
  });

  it("missing or invalid tab defaults to projects", async () => {
    for (const q of ["", "?tab=nope", "?tab=AWARDS", "?tab="]) {
      const { html } = await load("id", q);
      expect(html).toContain('href="/id/projects/proj-1"');
      expect(html).not.toContain("/penghargaan/1st-place-demo-day");
      expect(html).toMatch(/href="\/id\/work\?tab=projects"[^>]*aria-current="page"/);
    }
  });

  it("canonical has no query string for every tab", async () => {
    for (const q of ["", "?tab=awards", "?tab=gallery", "?tab=projects", "?tab=x&utm=1"]) {
      const { html } = await load("en", q);
      expect(html).toContain('<link rel="canonical" href="https://alisadikinma.com/en/work"');
    }
  });

  it("awards tab: server-rendered cards link to award pages by slug, honest result lines", async () => {
    const { html } = await load("id", "?tab=awards");
    expect(html).toContain('href="/id/penghargaan/1st-place-demo-day"');
    expect(html).toContain('href="/id/penghargaan/google-startup-grind-silicon-valley"');
    expect(html).toContain("Juara 1");
    expect(html).toContain("Hadir sebagai peserta");
    expect(html).not.toMatch(/Startup Grind[^<]*(tampil|juara|menang)/i);
    expect(html).not.toContain('href="/id/work?tab=awards"></a>');
    // no slug -> shown but not linked
    expect(html).toContain("No Slug Award");
    expect(html).not.toContain("/penghargaan/null");
    expect(html).not.toContain('href="/id/projects/proj-1"');
  });

  it("awards tab EN uses the /awards/ segment", async () => {
    const { html } = await load("en", "?tab=awards");
    expect(html).toContain('href="/en/awards/1st-place-demo-day"');
    expect(html).toContain("Attended as a participant");
  });

  it("gallery tab: own images only, srcset, dimensions, lightbox wiring, escaped titles", async () => {
    const { html } = await load("id", "?tab=gallery");
    expect(calls.some((u) => u.includes("/galleries"))).toBe(true);
    expect(html).toContain("Singapore &lt;i&gt;");
    expect(html).toContain("/storage/g/p1-320.webp 320w");
    expect(html).toMatch(/width="640" height="480"/);
    expect(html).toContain("data-lightbox");
    expect(html).toContain('id="lb"');
    expect(html).not.toContain("evil.example");
    expect(html).not.toContain('href="/id/projects/proj-1"');
  });

  it("projects tab: cards link to project pages, escaped, requests /projects", async () => {
    const { html } = await load("id", "?tab=projects");
    expect(calls.some((u) => u.includes("/projects?"))).toBe(true);
    expect(html).toContain("Project 1 &lt;b&gt;");
  });

  it("only the active tab's source is fetched", async () => {
    await load("id", "?tab=awards");
    expect(calls.some((u) => u.includes("/awards"))).toBe(true);
    expect(calls.some((u) => u.includes("/projects") || u.includes("/galleries"))).toBe(false);
  });

  it("emits CollectionPage + BreadcrumbList + Person JSON-LD, no aggregateRating", async () => {
    const { html } = await load("en", "?tab=awards");
    expect(html).toContain("CollectionPage");
    expect(html).toContain('"@type":"BreadcrumbList"');
    expect(html).toContain("https://alisadikinma.com/#person");
    expect(html).not.toContain("aggregateRating");
  });

  it("empty gallery and awards -> 200 empty state", async () => {
    handler = () => ok([]);
    for (const [tab, text] of [["gallery", "Belum ada galeri"], ["awards", "Belum ada penghargaan"], ["projects", "Belum ada proyek"]]) {
      const { status, html } = await load("id", `?tab=${tab}`);
      expect(status).toBe(200);
      expect(html).toContain(text);
    }
  });

  it("active tab source down -> 503 with Retry-After, tab nav still rendered", async () => {
    handler = () => new Response("boom", { status: 500 });
    const { status, html, headers } = await load("id", "?tab=awards");
    expect(status).toBe(503);
    expect(headers.get("Retry-After")).toBe("120");
    expect(html).toContain("tidak bisa dimuat");
    expect(html).toContain('href="/id/work?tab=gallery"');
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });
});
