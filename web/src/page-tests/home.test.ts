import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import Home from "../pages/[lang]/index.astro";

const HOST = "https://alisadikinma.com/storage";
const gallery = (id: number) => ({
  id, thumbnail: `${HOST}/t${id}.png`,
  items: Array.from({ length: 3 }, (_, i) => ({ type: "image", file_url: `${HOST}/g${id}-${i}.jpg`, sequence: i })),
});
const awardGalleries = (id: number) => ({
  award: { id }, galleries: [{ id, thumbnail: null, items: [{ type: "image", image: `${HOST}/a${id}.jpg`, sequence: 0 }] }],
});
const TESTIMONIALS = [1, 2, 3, 4].map((i) => ({
  client_name: `Person ${i}`, company_name: "Co", job_title: "Lead", sort_order: i,
  testimonial_text: "Ali builds production AI that factories actually use. He leads with clarity and ships fast.",
  client_photo: `/uploads/testimonials/p${i}.jpg`, linkedin_url: "https://www.linkedin.com/in/x/",
}));
const ARTICLES = [1, 2, 3].map((i) => ({ slug: `post-${i}`, title: `Post ${i}`, published_at: "2026-10-0" + i + "T00:00:00Z" }));

let sections: unknown;
let sectionsStatus: number;
function respond(url: string): Response {
  const u = new URL(url);
  const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });
  let m: RegExpMatchArray | null;
  if (u.pathname.endsWith("/page-sections")) return sectionsStatus === 200 ? ok(sections) : new Response("x", { status: sectionsStatus });
  if (u.pathname.endsWith("/settings/site")) return ok({ contact_phone: "+62 813-8016-3758" });
  if ((m = u.pathname.match(/\/galleries\/(\d+)$/))) return ok(gallery(Number(m[1])));
  if ((m = u.pathname.match(/\/awards\/(\d+)\/galleries$/))) return ok(awardGalleries(Number(m[1])));
  if (u.pathname.endsWith("/awards")) return ok([]);
  if (u.pathname.endsWith("/testimonials")) return ok(TESTIMONIALS);
  if (u.pathname.endsWith("/homepage/featured")) return ok({ latest_articles: ARTICLES });
  return new Response("{}", { status: 404 });
}

beforeEach(() => {
  resetApiCache();
  sections = [];
  sectionsStatus = 200;
  vi.stubGlobal("fetch", vi.fn(async (url: string) => respond(String(url))));
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(Home, { params: { lang }, request: new Request(`https://alisadikinma.com/${lang}`) });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const row = (section_type: string, is_active: boolean) => ({ page_type: "homepage", section_type, is_active });
const pos = (html: string, marker: string) => html.indexOf(marker);
const jsonld = (html: string) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)![1]);

describe("/{lang} home", () => {
  it.each(["id", "en"] as const)("passes the SEO gate incl. hero/who/services/journey/footer backlinks (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    const spec = (ROUTES as unknown as { home: { lang: string }[] }).home.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it("Person + WebSite JSON-LD, H1 with name and keyword", async () => {
    const { html } = await load("id");
    const types = jsonld(html)["@graph"].map((n: { "@type": string }) => n["@type"]);
    expect(types).toEqual(expect.arrayContaining(["Person", "WebSite"]));
    const h1 = html.match(/<h1[^>]*>([\s\S]*?)<\/h1>/)![1].replace(/<[^>]+>/g, " ");
    expect(h1).toContain("Ali Sadikin Ma");
    expect(h1).toContain("Pakar AI Indonesia");
    expect((html.match(/<h1/g) ?? []).length).toBe(1);
  });

  it("renders sections in order: hero, who, proof, journey, services, stages, testimonials, writing, newsletter", async () => {
    const { html } = await load("id");
    const order = ['class="hx', 'id="tentang"', 'class="proof"', 'id="perjalanan"', 'id="layanan"', 'id="panggung"', 'id="testimoni"', 'id="tulisan"', "data-newsletter"]
      .map((m) => pos(html, m));
    expect(order.every((p) => p > 0)).toBe(true);
    expect([...order].sort((a, b) => a - b)).toEqual(order);
  });

  it("stages section links to the Work page awards tab", async () => {
    const id = await load("id");
    expect(id.html).toContain('href="/id/work?tab=awards"');
    expect(id.html).toContain("Lihat semua penghargaan dan galeri");
    const en = await load("en");
    expect(en.html).toContain('href="/en/work?tab=awards"');
  });

  it("a section turned off in page-sections is not rendered; absent rows stay on", async () => {
    sections = [row("services", false), row("testimonials", false), row("hero", true)];
    const { html } = await load("id");
    expect(html).not.toContain('id="layanan"');
    expect(html).not.toContain('id="testimoni"');
    expect(html).toContain('id="tentang"'); // no row -> on
    expect(html).toContain('class="hx');
  });

  it("international-stages toggles both journey and stages; join-the-build the newsletter", async () => {
    sections = [row("international-stages", false), row("join-the-build", false)];
    const { html } = await load("id");
    expect(html).not.toContain('id="perjalanan"');
    expect(html).not.toContain('id="panggung"');
    expect(html).not.toContain("data-newsletter");
  });

  it("hero ignores its toggle: it carries the only H1 and the LCP image", async () => {
    sections = [row("hero", false)];
    const { html } = await load("id");
    expect(html).toContain('class="hx');
    expect((html.match(/<h1/g) ?? []).length).toBe(1);
  });

  it("page-sections API down -> every section on (fail open)", async () => {
    sectionsStatus = 500;
    const { status, html } = await load("en");
    expect(status).toBe(200);
    expect(html).toContain('id="layanan"');
    expect(html).toContain('id="perjalanan"');
  });

  it("hero gets the WhatsApp link from settings; nav anchors stay in-page", async () => {
    const { html } = await load("id");
    expect(html).toContain("https://wa.me/6281380163758");
    expect(html).toContain('href="#layanan"');
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });
});
