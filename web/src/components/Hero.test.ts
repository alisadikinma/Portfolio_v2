import { describe, it, expect } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import Hero from "./Hero.astro";

async function render(lang: "id" | "en", wa = "https://wa.me/620000000000") {
  const c = await AstroContainer.create();
  return c.renderToString(Hero, { props: { lang, wa } });
}

const IMG_TAG = /<img[^>]*class="hx-fallback"[^>]*>/;

describe.each(["id", "en"] as const)("Hero (%s)", (lang) => {
  it("renders the portrait <img> as an LCP-safe indexable image", async () => {
    const html = await render(lang);
    const tag = html.match(IMG_TAG)?.[0] ?? "";
    expect(tag).toContain('src="/images/ali-sadikin-ma-pakar-ai-indonesia.jpg"');
    expect(tag).toContain('width="1200"');
    expect(tag).toContain('height="1200"');
    expect(tag).toContain('fetchpriority="high"');
    expect(tag).not.toContain("loading=");
    const alt = lang === "id"
      ? "Ali Sadikin Ma, pakar AI Indonesia dan founder INDUSIA.ai"
      : "Ali Sadikin Ma, AI consultant Indonesia and founder of INDUSIA.ai";
    expect(tag).toContain(`alt="${alt}"`);
  });

  it("has the H1 with name span + keyword, last word on its own line (Ali 2026-11-09)", async () => {
    const html = await render(lang);
    const kw = lang === "id" ? "Pakar AI <span class=\"hx-h1-l2\">Indonesia</span>" : "AI Consultant <span class=\"hx-h1-l2\">Indonesia</span>";
    expect(html).toMatch(new RegExp(`<h1[^>]*id="hero-h1"[^>]*><span class="hx-h1-name">Ali Sadikin Ma</span> ${kw}</h1>`));
  });

  it("hero absorbs 'Who I am' (Ali 2026-11-09): #tentang anchor + dofollow product/company backlinks", async () => {
    const html = await render(lang);
    expect(html).toMatch(/<section[^>]*class="hx"[^>]*id="tentang"/);
    const base = lang === "id" ? "https://indusia.ai/id/" : "https://indusia.ai/";
    const want: [string, string][] = lang === "id"
      ? [["machine vision Indonesia", "ai-vision/"], ["fleet management system", "ai-fleet-transportation/"], ["chatbot WhatsApp AI", "ai-assistant/"], ["perusahaan AI Indonesia", ""]]
      : [["machine vision Indonesia", "ai-vision/"], ["fleet management system", "ai-fleet-transportation/"], ["WhatsApp AI chatbot", "ai-assistant/"], ["AI company Indonesia", ""]];
    const build = html.match(/<p[^>]*class="hx-build"[^>]*>[\s\S]*?<\/p>/)?.[0] ?? "";
    for (const [text, path] of want) {
      const a = [...build.matchAll(/<a ([^>]*)>([^<]*)<\/a>/g)].find((m) => m[2] === text);
      expect(a, text).toBeTruthy();
      expect(a![1]).toContain(`href="${base}${path}"`);
      expect(a![1]).not.toMatch(/\brel=/);
    }
  });

  it("lead has exact dofollow INDUSIA.ai anchor", async () => {
    const html = await render(lang);
    const href = lang === "id" ? "https://indusia.ai/id/" : "https://indusia.ai/";
    const lead = html.match(/<p[^>]*id="hero-lead"[^>]*>[\s\S]*?<\/p>/)?.[0] ?? "";
    const a = lead.match(/<a [^>]*>INDUSIA\.ai<\/a>/)?.[0] ?? "";
    expect(a).toContain(`href="${href}"`);
    expect(a).not.toMatch(/\srel=/);
    expect(lead).not.toContain("nofollow");
  });

  it("mobile toggle is collapsed and wired to the lead", async () => {
    const html = await render(lang);
    const btn = html.match(/<button[^>]*class="hx-more"[^>]*>/)?.[0] ?? "";
    expect(btn).toContain('aria-expanded="false"');
    expect(btn).toContain('aria-controls="hero-lead"');
    expect(btn).toContain('type="button"');
    expect(btn).toMatch(/\shidden[\s>=]/); // inert without JS; the script unhides it
  });

  it("renders exactly one detection box, on the face, and the HUD counters", async () => {
    const html = await render(lang);
    // Ali 2026-11-08: one box pointing at the face only; the other three were removed.
    const boxes = [...html.matchAll(/<span class="det[^"]*"[^>]*><b>([^<]+)<\/b><\/span>/g)].map((m) => m[1]);
    expect(boxes).toEqual(lang === "id" ? ["Ali Sadikin Ma · pakar AI"] : ["Ali Sadikin Ma · AI consultant"]);
    expect(html).toContain('data-count="56"');
    expect(html).toContain('data-count="17"');
  });

  it("INDUSIA.ai logo sits on the left chest over the point cloud (Ali 2026-11-09: must stand out)", async () => {
    const html = await render(lang);
    const logo = html.match(/<span class="hx-logo"[^>]*>[\s\S]*?<\/span>/)?.[0] ?? "";
    expect(logo).toMatch(/data-box="[\d.]+,[\d.]+,[\d.]+,[\d.]+"/);
    expect(logo).toContain('src="/images/indusia-logo-light.png"');
    expect(logo).toContain('alt=""');
  });

  it("never states forbidden facts", async () => {
    const html = await render(lang);
    expect(html).not.toMatch(/16 negara/i);
    expect(html).not.toMatch(/16 countries/i);
    expect(html).not.toMatch(/56 produk AI/i);
    expect(html).not.toMatch(/56 AI products/i);
  });

  it("CTA points to WhatsApp when given, omitted when not", async () => {
    expect(await render(lang)).toContain('href="https://wa.me/620000000000"');
    expect(await render(lang, "")).not.toContain("wa.me");
  });

  it("exposes hooks the client script needs", async () => {
    const html = await render(lang);
    for (const hook of ["data-hero", "data-cloud", "data-status", 'data-photo="/images/ali-sadikin-ma-pakar-ai-indonesia.jpg"']) {
      expect(html).toContain(hook);
    }
  });
});
