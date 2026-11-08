import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import Training from "../pages/[lang]/[trainingSeg].astro";

beforeEach(() => {
  resetApiCache();
  vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: true, data: {} }), { status: 200 })));
});
afterEach(() => vi.unstubAllGlobals());

const HOST = "https://alisadikinma.com/storage";
/** Galleries 20 (Demo Day), 12 (Alibaba eFounders), 16 (Satnusa): 10 photos each, file name = g{id}-s{sequence}. */
function stubGalleries() {
  vi.stubGlobal("fetch", vi.fn(async (url: string) => {
    const m = new URL(String(url)).pathname.match(/\/galleries\/(\d+)$/);
    const data = m
      ? { id: Number(m[1]), thumbnail: null, items: Array.from({ length: 10 }, (_, i) => ({ type: "image", sequence: 9 - i, file_url: `${HOST}/g${m[1]}-s${9 - i}.jpg`, image_variants: { "640w": `/storage/g${m[1]}-s${9 - i}-640w.webp`, "1280w": `/storage/g${m[1]}-s${9 - i}-1280w.webp` } })) }
      : {};
    return new Response(JSON.stringify({ success: true, data }), { status: 200 });
  }));
}

async function load(lang: string, seg: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(Training, {
    params: { lang, trainingSeg: seg },
    request: new Request(`https://alisadikinma.com/${lang}/${seg}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const jsonld = (html: string) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)![1]);
const CASES = [["id", "pelatihan-ai"], ["en", "ai-training"]] as const;

describe("training page", () => {
  it.each(CASES)("passes the SEO gate (%s)", async (lang, seg) => {
    const { status, html, headers } = await load(lang, seg);
    expect(status).toBe(200);
    const spec = (ROUTES as unknown as { training: { lang: string }[] }).training.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it.each([["en", "pelatihan-ai"], ["id", "ai-training"], ["id", "faq"], ["fr", "pelatihan-ai"], ["id", "x"]])(
    "404 for %s/%s", async (lang, seg) => {
      expect((await load(lang, seg)).status).toBe(404);
    });

  it("Service + FAQPage + Person + BreadcrumbList JSON-LD", async () => {
    const { html } = await load("id", "pelatihan-ai");
    const types = jsonld(html)["@graph"].map((n: { "@type": string }) => n["@type"]);
    expect(types).toEqual(expect.arrayContaining(["Service", "FAQPage", "Person", "WebSite", "BreadcrumbList"]));
  });

  it("one H1, status badge, form wired to the waitlist endpoint with page path + locale", async () => {
    const { html } = await load("id", "pelatihan-ai");
    expect((html.match(/<h1/g) ?? []).length).toBe(1);
    expect(html).toContain("Segera dibuka");
    expect(html).toContain('data-endpoint="/api/training-waitlist"');
    expect(html).toContain('data-source-page="/id/pelatihan-ai"');
    expect(html).toContain('data-locale="id"');
    const en = (await load("en", "ai-training")).html;
    expect(en).toContain('data-source-page="/en/ai-training"');
    expect(en).toContain('data-locale="en"');
  });

  it("dofollow INDUSIA anchor, no price", async () => {
    const { html } = await load("id", "pelatihan-ai");
    const m = html.match(/<a ([^>]*)>jasa pembuatan AI INDUSIA<\/a>/);
    expect(m).not.toBeNull();
    expect(m![1]).toContain('href="https://indusia.ai/id/ai-development-services/"');
    expect(m![1]).not.toContain("rel=");
    expect(html).not.toMatch(/Rp\s?\d/);
  });

  it("language switch + hreflang point to the paired URL", async () => {
    const { html } = await load("id", "pelatihan-ai");
    expect(html).toContain('hreflang="en" href="https://alisadikinma.com/en/ai-training"');
  });

  it.each(CASES)("real photos: hero stage photo only; no out-of-context photo strip (Ali 2026-11-09) (%s)", async (lang, seg) => {
    stubGalleries();
    const { html, headers } = await load(lang, seg);
    const spec = (ROUTES as unknown as { training: { lang: string }[] }).training.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
    const hero = html.match(/<figure class="th-photo"[\s\S]*?<\/figure>/)?.[0] ?? "";
    expect(hero).toContain("g20-s1-");
    expect(hero).toMatch(/<figcaption>[^<]*Global AI Demo Day 2026/);
    expect(html).not.toContain("th-strip");
    expect(html).not.toContain("g12-s9-");
    // the lede stays the first paragraph after the H1
    const afterH1 = html.slice(html.indexOf("</h1>"));
    expect(afterH1.match(/<p[^>]*>/)![0]).toContain('class="lead"');
  });

  it("gallery API down: no photo blocks, page still passes the gate", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response("x", { status: 500 })));
    const { status, html, headers } = await load("id", "pelatihan-ai");
    expect(status).toBe(200);
    expect(html).not.toContain('class="th-photo"');
    expect(html).not.toContain('class="th-strip"');
    const spec = (ROUTES as unknown as { training: { lang: string }[] }).training.find((s) => s.lang === "id");
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it("topics carry decorative SVG icons and the training flow is a 3-step ordered list", async () => {
    const { html } = await load("id", "pelatihan-ai");
    const topics = html.match(/<ul class="topics[\s\S]*?<\/ul>/)![0];
    expect((topics.match(/<svg[^>]*aria-hidden="true"/g) ?? []).length).toBe(4);
    const flow = html.match(/<ol class="th-flow"[\s\S]*?<\/ol>/)?.[0] ?? "";
    expect((flow.match(/<li/g) ?? []).length).toBe(3);
    expect(flow).toContain("alur kerja AI");
    const en = (await load("en", "ai-training")).html;
    expect(en).toContain('class="th-flow"');
    expect(en).toContain("AI workflow");
  });
});
