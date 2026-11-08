import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import { SAME_AS } from "../lib/schema";
import About from "../pages/[lang]/about.astro";

const AWARDS = [13, 10, 11, 7, 8, 9, 99].map((id) => ({
  id, title: `Award ${id}`, organization: `Org ${id}`, received_at: `20${10 + (id % 15)}-05-01`, slug: id === 13 ? "demo-day" : null,
}));
const BIO = '<p>Ali bio paragraph one.</p><script>alert(1)</script><p>Paragraph two.</p>';

let aboutStatus: number;
let about: Record<string, unknown>;
beforeEach(() => {
  resetApiCache();
  aboutStatus = 200;
  about = { bio: BIO, name: "Ali Sadikin Ma", updated_at: "2026-10-30T08:00:00Z" };
  vi.stubGlobal("fetch", vi.fn(async (url: string) => {
    const u = new URL(String(url));
    if (u.pathname.endsWith("/settings/about"))
      return aboutStatus === 200 ? new Response(JSON.stringify({ success: true, data: about }), { status: 200 }) : new Response("x", { status: aboutStatus });
    if (u.pathname.endsWith("/awards")) return new Response(JSON.stringify({ success: true, data: AWARDS }), { status: 200 });
    return new Response("{}", { status: 404 });
  }));
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(About, { params: { lang }, request: new Request(`https://alisadikinma.com/${lang}/about`) });
  return { status: res.status, html: await res.text(), headers: res.headers };
}
const ld = (html: string) => JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)![1])["@graph"];

describe("/{lang}/about", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    const spec = (ROUTES as unknown as { about: { lang: string }[] }).about.find((s) => s.lang === lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it("ProfilePage with mainEntity #person, dateModified, and INDUSIA org node", async () => {
    const { html } = await load("id");
    const g = ld(html);
    const pp = g.find((n: { "@type": string }) => n["@type"] === "ProfilePage");
    expect(pp.mainEntity).toEqual({ "@id": "https://alisadikinma.com/#person" });
    expect(pp.dateModified).toBe("2026-10-30");
    expect(g.some((n: { "@id": string }) => n["@id"] === "https://indusia.ai/#org")).toBe(true);
  });

  it("never invents a date: no updated_at means no 'updated' line and no dateModified", async () => {
    about = { bio: BIO, name: "Ali Sadikin Ma" };
    const { html } = await load("en");
    expect(html).not.toContain('class="about-updated"');
    const pp = ld(html).find((n: { "@type": string }) => n["@type"] === "ProfilePage");
    expect(pp.dateModified).toBeUndefined();
  });

  it("dated career timeline with time[datetime]; 2019-2021 gap has no text", async () => {
    const { html } = await load("id");
    for (const dt of ["2008", "2015", "2016", "2019", "2021-01", "2025-12", "2026-01-01"])
      expect(html).toContain(`<time datetime="${dt}"`);
    expect(html).toMatch(/<li class="gap" aria-hidden="true"><\/li>/);
  });

  it("awards list: real titles, attendee wording for award 11, no 'tampil'", async () => {
    const { html } = await load("id");
    for (const id of [13, 10, 11, 7, 8, 9]) expect(html).toContain(`Award ${id}`);
    expect(html).not.toContain("Award 99");
    expect(html).toContain("peserta");
    expect(html).not.toMatch(/tampil/i);
  });

  it("links every sameAs profile and shows 'Diperbarui' date", async () => {
    const { html } = await load("id");
    for (const u of SAME_AS) expect(html).toContain(`href="${u}"`);
    expect(html).toMatch(/Diperbarui <time datetime="2026-10-30">/);
  });

  it("sanitizes bio and renders it in full", async () => {
    const { html } = await load("id");
    expect(html).toContain("Ali bio paragraph one.");
    expect(html).toContain("Paragraph two.");
    expect(html).not.toContain("alert(1)");
  });

  it("INDUSIA anchors are dofollow", async () => {
    const { html } = await load("id");
    expect(html).toMatch(/<a href="https:\/\/indusia\.ai\/id\/">perusahaan AI Indonesia<\/a>/);
  });

  it("503 + Retry-After when about API is down", async () => {
    aboutStatus = 500;
    const { status, headers } = await load("id");
    expect(status).toBe(503);
    expect(headers.get("Retry-After")).toBe("120");
  });

  it("unknown lang is 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });
});
