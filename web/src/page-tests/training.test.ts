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
});
