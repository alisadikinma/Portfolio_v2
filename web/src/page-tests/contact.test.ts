import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import Contact from "../pages/[lang]/contact.astro";

let phone: string | null;
beforeEach(() => {
  resetApiCache();
  phone = "+62 813-8016-3758";
  vi.stubGlobal("fetch", vi.fn(async (url: string) => {
    const u = new URL(String(url));
    if (u.pathname.endsWith("/settings/site"))
      return new Response(JSON.stringify({ success: true, data: phone === null ? {} : { contact_phone: phone } }), { status: 200 });
    return new Response("{}", { status: 404 });
  }));
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(Contact, { params: { lang }, request: new Request(`https://alisadikinma.com/${lang}/contact`) });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const jsonld = (html: string) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)![1]);

describe("contact form timestamp", () => {
  it("renders a server data-ts and is never cached", async () => {
    const before = Math.floor(Date.now() / 1000);
    const c = await AstroContainer.create();
    const res = await c.renderToResponse(Contact, { params: { lang: "id" }, request: new Request("https://alisadikinma.com/id/contact") });
    const html = await res.text();
    const ts = Number(html.match(/data-ts="(\d+)"/)?.[1]);
    expect(ts).toBeGreaterThanOrEqual(before);
    expect(ts).toBeLessThanOrEqual(Math.floor(Date.now() / 1000));
    expect(res.headers.get("cache-control")).toContain("no-store");
  });
});

describe("/{lang}/contact", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    const spec = (ROUTES as unknown as { contact: { lang: string }[] }).contact.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it("JSON-LD: Person, WebSite, ContactPage and BreadcrumbList", async () => {
    const { html } = await load("id");
    const nodes = jsonld(html)["@graph"] as { "@type": string | string[] }[];
    const types = nodes.flatMap((n) => (Array.isArray(n["@type"]) ? n["@type"] : [n["@type"]]));
    expect(types).toEqual(expect.arrayContaining(["Person", "WebSite", "WebPage", "ContactPage", "BreadcrumbList"]));
  });

  it("form posts to same-origin /api/contact with all messages as data attributes", async () => {
    const { html } = await load("id");
    expect(html).toContain("data-contact");
    expect(html).toContain('data-endpoint="/api/contact"');
    for (const a of ["data-sending", "data-rate", "data-network", "data-server", "data-required", "data-summary-title"])
      expect(html).toContain(a);
    expect(html).toContain('name="whatsapp"');
    expect(html).toContain('name="website"'); // honeypot
    expect(html).toContain("data-summary");
    expect(html).toContain("data-done");
  });

  it("shows the WhatsApp link from settings and social links", async () => {
    const { html } = await load("id");
    expect(html).toContain("https://wa.me/6281380163758");
    expect(html).toContain("linkedin.com");
  });

  it("hides the WhatsApp link when no phone is configured", async () => {
    phone = null;
    const { html } = await load("id");
    expect(html).not.toContain("wa.me");
  });

  it("links to training and about", async () => {
    const id = (await load("id")).html;
    expect(id).toContain('href="/id/pelatihan-ai"');
    expect(id).toContain('href="/id/about"');
    const en = (await load("en")).html;
    expect(en).toContain('href="/en/ai-training"');
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });
});
