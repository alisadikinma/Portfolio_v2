import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import FaqPage from "../pages/[lang]/faq.astro";

const ITEMS = [
  { question: "Who is Ali Sadikin Ma?", answer: "Ali Sadikin Ma is an AI Generalist based in Indonesia." },
  { question: "What is FAQ <b> & more?", answer: "Answer with \"quotes\" & <tags>." },
  { question: "Third?", answer: "Third answer." },
];

let faqResponse: () => Response;

beforeEach(() => {
  resetApiCache();
  faqResponse = () => new Response(JSON.stringify({ success: true, data: ITEMS }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      if (String(url).includes("/faq")) return faqResponse();
      return new Response(JSON.stringify({ success: true, data: {} }), { status: 200 });
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(FaqPage, {
    params: { lang },
    request: new Request(`https://alisadikinma.com/${lang}/faq`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

const spec = (lang: "id" | "en") => (ROUTES as unknown as { faq: { lang: string }[] }).faq.find((s: { lang: string }) => s.lang === lang);

describe("/{lang}/faq", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    expect(checkHtml(html, spec(lang), headers)).toEqual([]);
  });

  it("renders H1, a <dl> with every Q&A (escaped), FAQPage + Person JSON-LD", async () => {
    const { html } = await load("id");
    expect(html).toMatch(/<h1[^>]*>FAQ: /);
    expect(html).toContain("<dl");
    expect((html.match(/<dt/g) ?? []).length).toBe(3);
    expect((html.match(/<dd/g) ?? []).length).toBe(3);
    expect(html).toContain("Who is Ali Sadikin Ma?");
    expect(html).toContain("What is FAQ &lt;b&gt; &amp; more?");
    expect(html).not.toContain("<b> &");
    expect(html).toContain('"@type":"FAQPage"');
    expect(html).toContain("https://alisadikinma.com/#person");
  });

  it("links to training and about, localized", async () => {
    const id = (await load("id")).html;
    expect(id).toContain('href="/id/pelatihan-ai"');
    expect(id).toContain('href="/id/about"');
    const en = (await load("en")).html;
    expect(en).toContain('href="/en/ai-training"');
    expect(en).toContain('href="/en/about"');
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });

  it("API down -> 503, unavailable message, no FAQPage, no crash", async () => {
    faqResponse = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Daftar pertanyaan sedang tidak bisa dimuat");
    expect(html).not.toContain("FAQPage");
    expect(html).not.toContain("<dl");
  });

  it("API network failure -> 503", async () => {
    faqResponse = () => {
      throw new Error("down");
    };
    expect((await load("en")).status).toBe(503);
  });

  it("empty items -> 503, no FAQPage", async () => {
    faqResponse = () => new Response(JSON.stringify({ success: true, data: [] }), { status: 200 });
    const { status, html } = await load("en");
    expect(status).toBe(503);
    expect(html).not.toContain("FAQPage");
  });

  it("malformed items are skipped; none valid -> 503", async () => {
    faqResponse = () =>
      new Response(JSON.stringify({ success: true, data: [{ question: "q" }, null, { answer: "a" }] }), { status: 200 });
    expect((await load("id")).status).toBe(503);
  });
});
