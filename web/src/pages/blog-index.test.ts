import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import BlogIndex from "./[lang]/blog/index.astro";

const post = (n: number, extra: Record<string, unknown> = {}) => ({
  id: n,
  slug: `post-${n}`,
  featured_image: `https://api.alisadikinma.com/storage/posts/p${n}.png`,
  image_variants: {
    "320w": `/storage/posts/p${n}-320w.webp`,
    "640w": `/storage/posts/p${n}-640w.webp`,
    "1024w": `/storage/posts/p${n}-1024w.webp`,
    lqip: "data:image/jpeg;base64,AAAA",
  },
  tags: ["ai"],
  published_at: "2026-10-05T03:00:00.000000Z",
  reading_time: 4,
  title: `Judul <b>${n}</b> & lain`,
  excerpt: `Ringkasan artikel ${n}.`,
  category: { id: 1, name: "AI Agents", slug: "ai-agents" },
  ...extra,
});

let posts: unknown[];
let meta: Record<string, unknown> | null;
let apiResponse: () => Response;
let seenUrls: string[];

beforeEach(() => {
  resetApiCache();
  seenUrls = [];
  posts = [post(1), post(2), post(3, { image_variants: null })];
  meta = { current_page: 1, last_page: 3, per_page: 12, total: 30 };
  apiResponse = () => new Response(JSON.stringify({ data: posts, meta, links: {} }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      seenUrls.push(String(url));
      if (String(url).includes("/posts")) return apiResponse();
      return new Response(JSON.stringify({ success: true, data: {} }), { status: 200 });
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, query = "") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(BlogIndex, {
    params: { lang },
    request: new Request(`https://alisadikinma.com/${lang}/blog${query}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

const spec = (lang: "id" | "en") =>
  (ROUTES as unknown as { blog: { lang: string }[] }).blog.find((s) => s.lang === lang);

describe("/{lang}/blog", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    expect(checkHtml(html, spec(lang), headers)).toEqual([]);
  });

  it("requests the API with lang, page and per_page", async () => {
    meta = { current_page: 2, last_page: 3, per_page: 12, total: 30 };
    await load("id", "?page=2");
    const u = new URL(seenUrls.find((x) => x.includes("/posts"))!);
    expect(u.searchParams.get("lang")).toBe("id");
    expect(u.searchParams.get("page")).toBe("2");
    expect(u.searchParams.get("per_page")).toBe("12");
  });

  it("renders post cards (escaped) linking to the localized detail URL", async () => {
    const { html } = await load("id");
    expect(html).toContain('href="/id/blog/post-1"');
    expect(html).toContain("Judul &lt;b&gt;1&lt;/b&gt; &amp; lain");
    expect(html).not.toContain("<b>1</b>");
    expect(html).toContain("Ringkasan artikel 2.");
    expect(html).toContain('datetime="2026-10-05');
  });

  it("uses image_variants srcset with width/height; falls back to featured_image", async () => {
    const { html } = await load("id");
    expect(html).toContain("https://api.alisadikinma.com/storage/posts/p1-320w.webp 320w");
    expect(html).toContain("p1-640w.webp 640w");
    expect(html).toMatch(/<img[^>]*src="https:\/\/api\.alisadikinma\.com\/storage\/posts\/p3\.png"[^>]*>/);
    expect(html).not.toContain("lqip");
    for (const m of html.matchAll(/<img[^>]*>/g)) {
      expect(m[0]).toMatch(/width="\d+"/);
      expect(m[0]).toMatch(/height="\d+"/);
    }
  });

  it("page 1: canonical has no ?page, next link only", async () => {
    const { html } = await load("id");
    expect(html).toContain('<link rel="canonical" href="https://alisadikinma.com/id/blog"');
    expect(html).toContain('rel="next" href="https://alisadikinma.com/id/blog?page=2"');
    expect(html).not.toContain('rel="prev"');
  });

  it("page 2: canonical keeps ?page=2, prev (to page 1 without param) and next", async () => {
    meta = { current_page: 2, last_page: 3, per_page: 12, total: 30 };
    const { status, html } = await load("en", "?page=2");
    expect(status).toBe(200);
    expect(html).toContain('<link rel="canonical" href="https://alisadikinma.com/en/blog?page=2"');
    expect(html).toContain('rel="prev" href="https://alisadikinma.com/en/blog"');
    expect(html).toContain('rel="next" href="https://alisadikinma.com/en/blog?page=3"');
    expect(html).toContain('"@type":"CollectionPage"');
    expect(html).toContain('"@type":"BreadcrumbList"');
  });

  it("last page: prev only", async () => {
    meta = { current_page: 3, last_page: 3, per_page: 12, total: 30 };
    const { html } = await load("id", "?page=3");
    expect(html).toContain('rel="prev" href="https://alisadikinma.com/id/blog?page=2"');
    expect(html).not.toContain('rel="next"');
  });

  it("empty list shows the empty state, 200", async () => {
    posts = [];
    meta = { current_page: 1, last_page: 1, per_page: 12, total: 0 };
    const { status, html } = await load("id");
    expect(status).toBe(200);
    expect(html).toContain("Belum ada artikel");
    expect(html).not.toContain('rel="next"');
  });

  it.each(["?page=0", "?page=abc", "?page=-2", "?page=1.5", "?page=1"])("%s -> 301 to page 1", async (q) => {
    const { status, headers } = await load("id", q);
    expect(status).toBe(301);
    expect(headers.get("location")).toBe("/id/blog");
  });

  it("page beyond last -> 404", async () => {
    meta = { current_page: 9, last_page: 3, per_page: 12, total: 30 };
    posts = [];
    expect((await load("id", "?page=9")).status).toBe(404);
  });

  it("API down -> 503 with plain message, no crash", async () => {
    apiResponse = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Artikel sedang tidak bisa dimuat");
  });

  it("API network failure -> 503", async () => {
    apiResponse = () => {
      throw new Error("down");
    };
    expect((await load("en")).status).toBe(503);
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });
});
