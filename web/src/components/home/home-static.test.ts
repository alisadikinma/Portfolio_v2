import { describe, it, expect } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { ROUTES } from "../../../scripts/seo-gate.config.mjs";
import ProofBand from "./ProofBand.astro";
import Services from "./Services.astro";

type Lang = "id" | "en";
const WA = "https://wa.me/620000000000";

async function render(C: unknown, lang: Lang, extra: Record<string, unknown> = {}) {
  const c = await AstroContainer.create();
  return c.renderToString(C as never, { props: { lang, ...extra } });
}
const link = (lang: Lang, anchor: "company" | "devServices") => {
  const page = ROUTES.home.find((r: { lang: string }) => r.lang === lang)!;
  const names = { company: lang === "id" ? "perusahaan AI Indonesia" : "AI company Indonesia", devServices: lang === "id" ? "jasa pembuatan AI" : "AI development services Indonesia" };
  const hit = page.backlinks.find((b: { anchor: string }) => b.anchor === names[anchor])!;
  return hit as { anchor: string; href: string };
};
const anchorTag = (html: string, text: string) =>
  [...html.matchAll(/<a [^>]*>([^<]*)<\/a>/g)].find((m) => m[1] === text)?.[0] ?? "";

describe("ProofBand", () => {
  it("id: exact proof facts", async () => {
    const html = await render(ProofBand, "id");
    expect(html).toContain("#1");
    expect(html).toContain("dari 26 startup AI asal 16 negara, Global AI Demo Day 2026");
    expect(html).toContain("<strong>56+</strong>");
    expect(html).toContain("produk digital di Satnusa: AI, IoT, aplikasi mobile");
    expect(html).toContain("<strong>17</strong>");
    expect(html).toContain("5 penghargaan dan fellowship startup".replace(/^5 /, ""));
    expect(html).toContain("<strong>5</strong>");
  });
  it.each(["id", "en"] as const)("%s: never states forbidden facts", async (lang) => {
    const html = await render(ProofBand, lang);
    expect(html).not.toMatch(/16 negara dalam perjalanan karier/i);
    expect(html).not.toMatch(/56 produk AI/i);
    expect(html).not.toMatch(/56 AI products/i);
    expect(html).toContain("<strong>56+</strong>");
  });
  it("en: states 26 startups / 16 countries as event context", async () => {
    const html = await render(ProofBand, "en");
    expect(html).toContain("26 AI startups from 16 countries, Global AI Demo Day 2026");
  });
});

describe.each(["id", "en"] as const)("Services (%s)", (lang) => {
  it("has 3 rows, id layanan, and routes", async () => {
    const html = await render(Services, lang, { wa: WA });
    expect(html).toContain('id="layanan"');
    expect((html.match(/<div class="row">/g) ?? []).length).toBe(3);
    const t = lang === "id" ? "/id/pelatihan-ai" : "/en/ai-training";
    const s = lang === "id" ? "/id/pembicara-ai" : "/en/ai-speaker";
    expect(html).toContain(`href="${t}"`);
    expect(html).toContain(`href="${s}"`);
    expect(html).toContain(`href="${WA}"`);
  });
  it("devServices anchor is exact and dofollow", async () => {
    const html = await render(Services, lang, { wa: WA });
    const l = link(lang, "devServices");
    const a = anchorTag(html, l.anchor);
    expect(a).toContain(`href="${l.href}"`);
    expect(a).not.toMatch(/\srel=/);
  });
  it("hides WhatsApp link when wa is empty", async () => {
    const html = await render(Services, lang, { wa: "" });
    expect(html).not.toContain("wa.me");
    expect((html.match(/<div class="row">/g) ?? []).length).toBe(3);
  });
});
