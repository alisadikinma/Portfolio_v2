import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { basicSpec } from "../../scripts/seo-gate.config.mjs";
import CategoryPage from "./[lang]/blog/category/[slug].astro";

const post = (n: number) => ({
  id: n,
  slug: `post-${n}`,
  featured_image: `https://api.alisadikinma.com/storage/posts/p${n}.png`,
  image_variants: null,
  tags: ["ai"],
  published_at: "2026-10-05T03:00:00.000000Z",
  reading_time: 4,
  title: `Judul ${n}`,
  excerpt: `Ringkasan artikel ${n}.`,
  category: { id: 1, name: "AI Agents", slug: "ai-agents" },
});

let category: Record<string, unknown> | null;
let posts: unknown[];
let meta: Record<string, unknown>;
let postsResponse: () => Response;
let seenUrls: string[];

beforeEach(() => {
  resetApiCache();
  seenUrls = [];
  category = { id: 1, name: "AI Agents", slug: "ai-agents", description: null, posts_count: 2 };
  posts = [post(1), post(2)];
  meta = { current_page: 1, last_page: 2, per_page: 12, total: 14 };
  postsResponse = () => new Response(JSON.stringify({ data: posts, meta, links: {} }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      const u = String(url);
      seenUrls.push(u);
      if (u.includes("/categories/")) {
        return category === null
          ? new Response(JSON.stringify({ message: "Category not found" }), { status: 404 })
          : new Response(JSON.stringify({ data: category }), { status: 200 });
      }
      if (u.includes("/posts")) return postsResponse();
      return new Response(JSON.stringify({ data: {} }), { status: 200 });
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, slug = "ai-agents", query = "") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(CategoryPage, {
    params: { lang, slug },
    request: new Request(`https://alisadikinma.com/${lang}/blog/category/${slug}${query}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

describe("/{lang}/blog/category/{slug}", () => {
  it("renders category name as the only H1 and its posts", async () => {
    const { status, html } = await load("id");
    expect(status).toBe(200);
    expect(html.match(/<h1[\s>]/g)?.length).toBe(1);
    expect(html).toMatch(/<h1[^>]*>[^<]*AI Agents[^<]*<\/h1>/);
    expect(html).toContain('href="/id/blog/post-1"');
    expect(html).toContain('href="/id/blog/post-2"');
  });

  it("filters posts by category slug via the API", async () => {
    await load("en", "ai-agents", "?page=2");
    const u = new URL(seenUrls.find((x) => x.includes("/posts"))!);
    expect(u.searchParams.get("category")).toBe("ai-agents");
    expect(u.searchParams.get("page")).toBe("2");
    expect(u.searchParams.get("lang")).toBe("en");
  });

  it("emits CollectionPage + BreadcrumbList (Home > Blog > Category) and canonical", async () => {
    const { html } = await load("id");
    expect(html).toContain('"@type":"CollectionPage"');
    expect(html).toContain('"@type":"BreadcrumbList"');
    expect(html).toContain("https://alisadikinma.com/id/blog/category/ai-agents");
    expect(html).toContain('<link rel="canonical" href="https://alisadikinma.com/id/blog/category/ai-agents"');
    expect(html).toContain('rel="next" href="https://alisadikinma.com/id/blog/category/ai-agents?page=2"');
    expect(html).toContain("index,follow");
  });

  it.each(["id", "en"] as const)("non-empty category passes the SEO gate (%s)", async (lang) => {
    const spec = basicSpec(
      lang,
      `/${lang}/blog/category/ai-agents`,
      { id: "/id/blog/category/ai-agents", en: "/en/blog/category/ai-agents" },
      ["AI Agents"],
      "CollectionPage",
    );
    const { html, headers } = await load(lang);
    expect(checkHtml(html, spec, headers)).toEqual([]);
  });

  it("empty category: 200, empty state, noindex", async () => {
    posts = [];
    meta = { current_page: 1, last_page: 1, per_page: 12, total: 0 };
    const { status, html } = await load("id");
    expect(status).toBe(200);
    expect(html).toContain("Belum ada artikel di kategori ini");
    expect(html).toMatch(/<meta name="robots" content="noindex[^"]*"/);
    expect(html).not.toContain("index,follow");
  });

  it("unknown category -> 404", async () => {
    category = null;
    expect((await load("id", "nope")).status).toBe(404);
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });

  it("invalid page -> 301 to page 1; page beyond last -> 404", async () => {
    const r = await load("id", "ai-agents", "?page=abc");
    expect(r.status).toBe(301);
    expect(r.headers.get("location")).toBe("/id/blog/category/ai-agents");
    meta = { current_page: 9, last_page: 2, per_page: 12, total: 14 };
    posts = [];
    expect((await load("id", "ai-agents", "?page=9")).status).toBe(404);
  });

  it("API down (posts 500) -> 503", async () => {
    postsResponse = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Artikel sedang tidak bisa dimuat");
  });

  it("API down (category 500) -> 503", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response("boom", { status: 500 })));
    expect((await load("en")).status).toBe(503);
  });
});

describe("category: SEO fixes", () => {
  it("long category name: description clipped to <= 160 at a word boundary with a single ellipsis", async () => {
    category = { ...category!, name: "Kecerdasan Buatan untuk Manufaktur, Logistik, dan Operasional Pabrik Skala Besar di Indonesia" };
    const { html } = await load("id");
    const d = /<meta name="description" content="([^"]*)"/.exec(html)![1];
    expect(d.length).toBeLessThanOrEqual(160);
    expect(d.endsWith("…")).toBe(true);
    expect(d).not.toContain("...");
  });

  it("503 carries Retry-After: 120", async () => {
    postsResponse = () => new Response("boom", { status: 500 });
    const { status, headers } = await load("id");
    expect(status).toBe(503);
    expect(headers.get("retry-after")).toBe("120");
  });

  it("long category name: title clipped to <= 60 incl. page suffix", async () => {
    category = { ...category!, name: "Kecerdasan Buatan untuk Manufaktur, Logistik, dan Operasional Pabrik Skala Besar" };
    const t = /<title>([^<]*)<\/title>/.exec((await load("id", "ai-agents", "?page=2")).html)![1];
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.endsWith("(Halaman 2)")).toBe(true);
    expect(t).toContain("…");
  });
});
