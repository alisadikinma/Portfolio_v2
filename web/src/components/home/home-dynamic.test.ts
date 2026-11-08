import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";

import { resetApiCache } from "../../lib/api";
import { excerptAtSentence } from "../../lib/excerpt";
import Testimonials from "./Testimonials.astro";
import LatestWriting from "./LatestWriting.astro";
import NewsletterForm from "../NewsletterForm.astro";

type Lang = "id" | "en";
async function render(C: unknown, lang: Lang, extra: Record<string, unknown> = {}) {
  const c = await AstroContainer.create();
  return c.renderToString(C as never, { props: { lang, ...extra } });
}
/** Stub global fetch (same approach as the page tests): body is the envelope `data`; "fail" = 500; "down" = network error. */
let seen: string[];
function api(data: unknown, mode: "ok" | "fail" | "down" = "ok") {
  vi.stubGlobal("fetch", vi.fn(async (url: string, init?: RequestInit) => {
    seen.push(`${url}|${(init?.headers as Record<string, string>)?.Cookie ?? ""}`);
    if (mode === "down") throw new TypeError("network down");
    if (mode === "fail") return new Response("err", { status: 500 });
    return new Response(JSON.stringify({ success: true, data }), { status: 200 });
  }));
}
beforeEach(() => { resetApiCache(); seen = []; });
afterEach(() => vi.unstubAllGlobals());

const T = (n: number, o: Record<string, unknown> = {}) => ({
  client_name: `Person ${n}`, company_name: `Co ${n}`, job_title: `Role ${n}`,
  testimonial_text: `Great work number ${n}. Second sentence here.`,
  client_photo: `/uploads/testimonials/p${n}.jpg`, source: "linkedin",
  linkedin_url: `https://www.linkedin.com/in/p${n}/`, sort_order: n, ...o,
});

describe("excerptAtSentence", () => {
  it("returns short text unchanged", () => {
    expect(excerptAtSentence("Short one.")).toBe("Short one.");
  });
  it("cuts at the last sentence end within the limit", () => {
    const s1 = "Ali led the program with real clarity. ";
    const s2 = "He shipped fast and mentored the whole team every week. ";
    const s3 = "Third sentence is long enough to push the total well beyond the limit of the excerpt for sure yes.";
    const out = excerptAtSentence(s1 + s2 + s3, 120);
    expect(out).toBe((s1 + s2).trim());
  });
  it("does not split on e.g. or decimals", () => {
    const t = "He built tools, e.g. Python scripts and Industry 4.0 dashboards that saved 3.5 hours daily for the whole plant. Then more happened across the other departments over time with great success overall.";
    const out = excerptAtSentence(t, 150);
    expect(out.endsWith("whole plant.")).toBe(true);
    expect(out).toContain("e.g. Python");
  });
  it("falls back to a word boundary with ellipsis when no sentence fits", () => {
    const out = excerptAtSentence("word ".repeat(100).trim(), 50);
    expect(out.endsWith("…")).toBe(true);
    expect(out.length).toBeLessThanOrEqual(51);
  });
});

describe("Testimonials", () => {

  it("renders rows in sort_order with photo, lang=en quote, role and LinkedIn link", async () => {
    api([T(2), T(1)]);
    const html = await render(Testimonials, "id");
    expect(html).toContain('id="testimoni"');
    expect((html.match(/<figure class="tm">/g) ?? []).length).toBe(2);
    expect(html.indexOf("Person 1")).toBeLessThan(html.indexOf("Person 2"));
    expect(html).toContain('<blockquote lang="en">');
    // Relative paths stay same-origin (served by this site's /uploads), so a local or
    // staging stack never pulls testimonial photos from production.
    expect(html).toMatch(/<img[^>]*src="\/uploads\/testimonials\/p1\.jpg"[^>]*alt="Person 1"/);
    expect(html).toContain('width="56"');
    expect(html).toContain('loading="lazy"');
    expect(html).toContain("Role 1 · Co 1");
    expect(html).toMatch(/<a[^>]*href="https:\/\/www\.linkedin\.com\/in\/p1\/"[^>]*rel="noopener"[^>]*>Rekomendasi lengkap di LinkedIn<\/a>/);
    expect(html).toContain('target="_blank"');
  });
  it("en copy", async () => {
    api([T(1)]);
    expect(await render(Testimonials, "en")).toContain("Full recommendation on LinkedIn");
  });
  it("caps at 4 and omits link when linkedin_url missing", async () => {
    api([1, 2, 3, 4, 5].map((n) => T(n, n === 1 ? { linkedin_url: null } : {})));
    const html = await render(Testimonials, "id");
    expect((html.match(/<figure class="tm">/g) ?? []).length).toBe(4);
    expect((html.match(/Rekomendasi lengkap/g) ?? []).length).toBe(3);
  });
  it("escapes quote text", async () => {
    api([T(1, { testimonial_text: "<script>alert(1)</script> Nice." })]);
    const html = await render(Testimonials, "id");
    expect(html).not.toContain("<script>alert(1)");
  });
  it.each([[[]], [null]])("hidden when data is %j", async (v) => {
    api(v);
    expect((await render(Testimonials, "id")).trim()).toBe("");
  });
  it("hidden when the API throws", async () => {
    api(null, "fail");
    expect((await render(Testimonials, "id")).trim()).toBe("");
    resetApiCache();
    api(null, "down");
    expect((await render(Testimonials, "id")).trim()).toBe("");
  });
});

describe("LatestWriting", () => {
  const P = (n: number) => ({ slug: `post-${n}`, title: `Judul ${n}`, published_at: `2026-11-0${n}T03:00:00Z` });

  it("lists 3 posts with localized links and time elements", async () => {
    api({ latest_articles: [P(5), P(4), P(3), P(2), P(1)] });
    const html = await render(LatestWriting, "id");
    expect(html).toContain('id="tulisan"');
    expect((html.match(/<li>/g) ?? []).length).toBe(3);
    expect(html).toContain('href="/id/blog/post-5"');
    expect(html).toContain('datetime="2026-11-05');
    expect(seen[0]).toContain("/homepage/featured");
    expect(seen[0]).toContain("lang_preference=id");
  });
  it("en links", async () => {
    api({ latest_articles: [P(1)] });
    expect(await render(LatestWriting, "en")).toContain('href="/en/blog/post-1"');
  });
  it.each([[{ latest_articles: [] }], [null], [{}]])("hidden for %j", async (v) => {
    api(v);
    expect((await render(LatestWriting, "id")).trim()).toBe("");
  });
  it("hidden on failure", async () => {
    api(null, "fail");
    expect((await render(LatestWriting, "id")).trim()).toBe("");
    resetApiCache();
    api(null, "down");
    expect((await render(LatestWriting, "id")).trim()).toBe("");
  });
});

describe("NewsletterForm", () => {
  it.each(["id", "en"] as const)("%s: 3 fields, endpoint and source", async (lang) => {
    const html = await render(NewsletterForm, lang);
    expect(html).toContain("data-newsletter");
    expect(html).toContain('data-endpoint="/api/newsletter/subscribe"');
    expect(html).toContain('data-source="homepage_join"');
    for (const n of ["name", "email", "whatsapp"]) expect(html).toContain(`name="${n}"`);
    expect(html).toContain('data-status');
    expect(html).toContain('autocomplete="tel"');
  });
  it("id has the Indonesian sending label", async () => {
    expect(await render(NewsletterForm, "id")).toContain('data-sending="Mengirim…"');
  });
});
