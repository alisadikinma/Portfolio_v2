import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { resetImageDimsCache } from "../lib/image-dims";
import { PORTRAIT_URL } from "../lib/schema";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { blogPostSpec } from "../../scripts/seo-gate.config.mjs";
import BlogDetail from "../pages/[lang]/blog/[slug].astro";

const SLUG = "vibe-coding-untuk-pabrik";
const DESC =
  "Cara memakai vibe coding untuk membangun aplikasi pabrik dengan cepat: langkah praktis, contoh nyata, dan jebakan yang perlu dihindari tim engineering.";

let post: Record<string, unknown>;
let apiResponse: (url: string) => Response;
let seenUrls: string[];

const basePost = () => ({
  id: 7,
  slug: SLUG,
  featured_image: "https://api.alisadikinma.com/storage/posts/vibe.png",
  image_variants: {
    "320w": "/storage/posts/vibe-320w.webp",
    "640w": "/storage/posts/vibe-640w.webp",
    lqip: "data:image/jpeg;base64,AAAA",
  },
  published_at: "2026-10-05T03:00:00.000000Z",
  updated_at: "2026-10-20T08:30:00.000000Z",
  reading_time: 5,
  title: "Vibe coding untuk pabrik <b>nyata</b>",
  excerpt:
    "Vibe coding membantu tim pabrik membangun aplikasi internal dengan cepat. Tulisan ini membahas langkah praktis, contoh kasus nyata, dan jebakan yang sering muncul saat dipakai.",
  content:
    '<h2>Mulai dari masalah</h2><p>Isi artikel.</p><script>alert(1)</script><img src="/x.png" alt="x" width="10" height="10" onerror="alert(2)">',
  seo: { meta_title: "Vibe coding untuk pabrik — Ali Sadikin Ma", meta_description: DESC, og_image: null },
  category: { id: 1, name: "Vibe Coding", slug: "vibe-coding" },
  available_translations: ["id", "en"],
  current_language: "id",
  related_posts: [
    { id: 8, slug: "ai-agent-pabrik", title: "AI agent di pabrik", featured_image: null, category: { name: "AI Agents", slug: "ai-agents" } },
    { id: 9, slug: "kosong", title: "", featured_image: null, category: null },
  ],
});

// Valid PNG header (IHDR 800x450): all image-size needs.
const PNG = (() => {
  const b = new Uint8Array(33);
  b.set([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a], 0);
  const v = new DataView(b.buffer);
  v.setUint32(8, 13);
  b.set([0x49, 0x48, 0x44, 0x52], 12);
  v.setUint32(16, 800);
  v.setUint32(20, 450);
  return b;
})();

beforeEach(() => {
  resetApiCache();
  resetImageDimsCache();
  seenUrls = [];
  post = basePost();
  apiResponse = () => new Response(JSON.stringify({ success: true, data: post }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      seenUrls.push(String(url));
      if (String(url).includes("/posts/")) return apiResponse(String(url));
      if (String(url).includes("/storage/blog-images/")) return new Response(PNG, { status: 206 });
      return new Response(JSON.stringify({ success: true, data: {} }), { status: 200 });
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, slug = SLUG, search = "") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(BlogDetail, {
    params: { lang, slug },
    request: new Request(`https://alisadikinma.com/${lang}/blog/${slug}${search}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

const specFor = (lang: "id" | "en") =>
  blogPostSpec(lang, `/${lang}/blog/${SLUG}`, { id: `/id/blog/${SLUG}`, en: `/en/blog/${SLUG}` }, "Vibe coding");

const ld = (html: string) => {
  const raw = /<script type="application\/ld\+json">([\s\S]*?)<\/script>/.exec(html)![1];
  return (JSON.parse(raw)["@graph"] as Record<string, any>[]);
};

describe("/{lang}/blog/{slug}", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    post.available_translations = ["id", "en"];
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    expect(checkHtml(html, specFor(lang), headers)).toEqual([]);
  });

  it("requests the post by slug with lang and track=0", async () => {
    await load("id");
    const u = new URL(seenUrls.find((x) => x.includes("/posts/"))!);
    expect(u.pathname.endsWith(`/posts/${SLUG}`)).toBe(true);
    expect(u.searchParams.get("lang")).toBe("id");
    expect(u.searchParams.get("track")).toBe("0");
  });

  it("H1 is the (escaped) title, once", async () => {
    const { html } = await load("id");
    expect(html.match(/<h1[ >]/g)).toHaveLength(1);
    expect(html).toMatch(/<h1[^>]*>Vibe coding untuk pabrik &lt;b&gt;nyata&lt;\/b&gt;<\/h1>/);
  });

  it("renders published and modified <time datetime>", async () => {
    const { html } = await load("id");
    expect(html).toContain('<time datetime="2026-10-05T03:00:00.000Z"');
    expect(html).toContain('<time datetime="2026-10-20T08:30:00.000Z"');
  });

  it("BlogPosting has dateModified = updated_at and datePublished", async () => {
    const { html } = await load("id");
    const bp = ld(html).find((n) => n["@type"] === "BlogPosting")!;
    expect(bp.dateModified).toBe("2026-10-20T08:30:00.000Z");
    expect(bp.datePublished).toBe("2026-10-05T03:00:00.000Z");
    expect(bp.inLanguage).toBe("id");
  });

  it("BreadcrumbList is Home > Blog > Category > Post", async () => {
    const { html } = await load("id");
    const bc = ld(html).find((n) => n["@type"] === "BreadcrumbList")!;
    expect(bc.itemListElement.map((i: any) => i.item)).toEqual([
      "https://alisadikinma.com/id",
      "https://alisadikinma.com/id/blog",
      "https://alisadikinma.com/id/blog/category/vibe-coding",
      `https://alisadikinma.com/id/blog/${SLUG}`,
    ]);
    expect(bc.itemListElement[2].name).toBe("Vibe Coding");
  });

  it("breadcrumb skips the category level when the post has none", async () => {
    post.category = null;
    const { html } = await load("id");
    const bc = ld(html).find((n) => n["@type"] === "BreadcrumbList")!;
    expect(bc.itemListElement).toHaveLength(3);
  });

  it("author box: dofollow INDUSIA anchors per language", async () => {
    const id = (await load("id")).html;
    expect(id).toMatch(/<a href="https:\/\/indusia\.ai\/id\/"[^>]*>founder INDUSIA\.ai<\/a>, <a href="https:\/\/indusia\.ai\/id\/"[^>]*>perusahaan AI Indonesia<\/a>/);
    const en = (await load("en")).html;
    expect(en).toMatch(/<a href="https:\/\/indusia\.ai\/"[^>]*>founder INDUSIA\.ai<\/a>/);
    expect(en).toMatch(/<a href="https:\/\/indusia\.ai\/"[^>]*>AI company Indonesia<\/a>/);
    for (const m of (id + en).matchAll(/<a [^>]*href="https:\/\/indusia\.ai[^>]*>/g)) {
      expect(m[0]).not.toMatch(/nofollow|ugc|sponsored/);
    }
  });

  it("author box links the money pages with keyword anchors (PF-3)", async () => {
    const id = (await load("id")).html;
    expect(id).toMatch(/<a href="\/id\/pelatihan-ai"[^>]*>pelatihan AI<\/a>/);
    expect(id).toMatch(/<a href="\/id\/pembicara-ai"[^>]*>pembicara AI<\/a>/);
    const en = (await load("en")).html;
    expect(en).toMatch(/<a href="\/en\/ai-training"[^>]*>AI training<\/a>/);
    expect(en).toMatch(/<a href="\/en\/ai-speaker"[^>]*>AI speaker<\/a>/);
  });

  it("sanitizes post content: no script, no on* handlers", async () => {
    const { html } = await load("id");
    expect(html).toContain("<h2>Mulai dari masalah</h2>");
    expect(html).toContain("Isi artikel.");
    expect(html).not.toContain("alert(");
    expect(html).not.toMatch(/onerror/i);
    expect(html).not.toMatch(/<script>alert/);
  });

  it("related posts: cards link to localized detail; untitled ones are dropped", async () => {
    const { html } = await load("id");
    expect(html).toContain('href="/id/blog/ai-agent-pabrik"');
    expect(html).toContain("AI agent di pabrik");
    expect(html).not.toContain("/id/blog/kosong");
  });

  it("hero image has width/height and non-empty alt; variants become srcset", async () => {
    const { html } = await load("id");
    const hero = /<img class="post-hero"(?:"[^"]*"|[^>"])*>/.exec(html)![0];
    expect(hero).toMatch(/width="\d+"/);
    expect(hero).toMatch(/height="\d+"/);
    expect(hero).toMatch(/alt="[^"]+"/);
    expect(hero).toContain("https://api.alisadikinma.com/storage/posts/vibe-320w.webp 320w");
    expect(hero).not.toContain("lqip");
  });

  it("uses og:type article and the seo.og_image when present", async () => {
    (post.seo as Record<string, unknown>).og_image = "https://api.alisadikinma.com/storage/og/vibe.png";
    const { html } = await load("id");
    expect(html).toContain('<meta property="og:type" content="article"');
    expect(html).toContain('<meta property="og:image" content="https://api.alisadikinma.com/storage/og/vibe.png"');
  });

  it("unknown slug -> 404", async () => {
    apiResponse = () => new Response("{}", { status: 404 });
    expect((await load("id", "nope")).status).toBe(404);
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });

  it("post without the requested language -> 302 (temporary, translation may follow) to the available language", async () => {
    post.available_translations = ["en"];
    const { status, headers } = await load("id");
    expect(status).toBe(302);
    expect(headers.get("location")).toBe(`/en/blog/${SLUG}`);
  });

  it("post with no translations at all -> 404", async () => {
    post.available_translations = [];
    expect((await load("id")).status).toBe(404);
  });

  it("API down -> 503 with plain message", async () => {
    apiResponse = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Artikel ini sedang tidak bisa dimuat");
  });

  it("API network failure -> 503", async () => {
    apiResponse = () => {
      throw new Error("down");
    };
    expect((await load("en")).status).toBe(503);
  });

  it("malformed payload (no title) -> 503", async () => {
    post = { slug: SLUG, available_translations: ["id"] };
    expect((await load("id")).status).toBe(503);
  });
});

const titleOf = (html: string) => /<title>([^<]*)<\/title>/.exec(html)![1];
const descOf = (html: string) => /<meta name="description" content="([^"]*)"/.exec(html)![1];
const LONG = "Kata ".repeat(40).trim();

describe("blog detail: title and description", () => {
  it("uses meta_title as-is when it fits in 60 chars", async () => {
    (post.seo as Record<string, unknown>).meta_title = "Self-Hosting Chinese AI Models: The Proven Prediction";
    expect(titleOf((await load("en")).html)).toBe("Self-Hosting Chinese AI Models: The Proven Prediction");
  });

  it("clips an over-long meta_title at a word boundary with a single ellipsis", async () => {
    (post.seo as Record<string, unknown>).meta_title =
      "Panduan lengkap vibe coding untuk pabrik dan logistik di Indonesia tahun ini";
    const t = titleOf((await load("id")).html);
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.endsWith("…")).toBe(true);
    expect(t).not.toContain("...");
  });

  it("without meta_title: '{title} | Ali Sadikin Ma' when it fits", async () => {
    (post.seo as Record<string, unknown>).meta_title = null;
    post.title = "Vibe coding pabrik";
    expect(titleOf((await load("id")).html)).toBe("Vibe coding pabrik | Ali Sadikin Ma");
  });

  it("without meta_title and a long title: clipped title alone, name not twice", async () => {
    (post.seo as Record<string, unknown>).meta_title = null;
    post.title = "Judul artikel yang panjang sekali tentang vibe coding untuk pabrik";
    const t = titleOf((await load("id")).html);
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.match(/Ali Sadikin Ma/g) ?? []).toHaveLength(0);
  });

  it("description: short meta_description yields to excerpt", async () => {
    (post.seo as Record<string, unknown>).meta_description = "Terlalu pendek.";
    const d = descOf((await load("id")).html);
    expect(d.startsWith("Vibe coding membantu tim pabrik")).toBe(true);
    expect(d.length).toBeGreaterThanOrEqual(120);
  });

  it("description: falls back to the first paragraph of the content", async () => {
    (post.seo as Record<string, unknown>).meta_description = null;
    post.excerpt = null;
    post.content = `<h2>H</h2><p>${"Paragraf pertama yang cukup panjang untuk deskripsi. ".repeat(4).trim()}</p>`;
    const d = descOf((await load("id")).html);
    expect(d.length).toBeGreaterThanOrEqual(120);
    expect(d.startsWith("Paragraf pertama")).toBe(true);
  });

  it("description: clipped to 160 with a single ellipsis, never empty", async () => {
    (post.seo as Record<string, unknown>).meta_description = LONG;
    const d = descOf((await load("id")).html);
    expect(d.length).toBeLessThanOrEqual(160);
    expect(d.endsWith("…")).toBe(true);
    resetApiCache();
    post.excerpt = null;
    post.content = "";
    (post.seo as Record<string, unknown>).meta_description = null;
    expect(descOf((await load("id")).html).length).toBeGreaterThan(0);
  });
});

describe("blog detail: lede", () => {
  it("renders the excerpt as post-lede only with >= 25 words", async () => {
    expect((await load("id")).html).toMatch(/class="post-lede"/);
    resetApiCache();
    post.excerpt = "Ringkasan yang terlalu pendek.";
    const { html } = await load("id");
    expect(html).not.toMatch(/class="post-lede"/);
    expect(html).not.toContain("Ringkasan yang terlalu pendek.</p>");
  });
});

describe("blog detail: dateModified", () => {
  it("prefers content_updated_at over updated_at (JSON-LD and visible label)", async () => {
    post.content_updated_at = "2026-10-25T01:00:00.000000Z";
    const { html } = await load("en");
    const bp = ld(html).find((n) => n["@type"] === "BlogPosting")!;
    expect(bp.dateModified).toBe("2026-10-25T01:00:00.000Z");
    expect(html).toContain('<time datetime="2026-10-25T01:00:00.000Z"');
    expect(html).not.toContain('<time datetime="2026-10-20T08:30:00.000Z"');
  });

  it("falls back to updated_at when content_updated_at is absent", async () => {
    const bp = ld((await load("en")).html).find((n) => n["@type"] === "BlogPosting")!;
    expect(bp.dateModified).toBe("2026-10-20T08:30:00.000Z");
  });
});

describe("blog detail: single-language posts", () => {
  it("only id: no en hreflang, x-default -> id, language switch hidden", async () => {
    post.available_translations = ["id"];
    const { html } = await load("id");
    expect(html).not.toContain('hreflang="en"');
    expect(html).toContain(`hreflang="x-default" href="https://alisadikinma.com/id/blog/${SLUG}"`);
    expect(html).not.toContain('class="lang flag"');
  });

  it("both languages: full hreflang + switch", async () => {
    const { html } = await load("id");
    expect(html).toContain('hreflang="en"');
    expect(html).toContain('class="lang flag"');
  });

  it("missing-language redirect: 302, Cache-Control max-age=3600, query kept", async () => {
    post.available_translations = ["en"];
    const { status, headers } = await load("id", SLUG, "?utm_source=x&a=1");
    expect(status).toBe(302);
    expect(headers.get("location")).toBe(`/en/blog/${SLUG}?utm_source=x&a=1`);
    expect(headers.get("cache-control")).toBe("max-age=3600");
  });
});

describe("blog detail: slug validation and errors", () => {
  it.each([".", "..", "a/b", "a\\b"])("slug %j -> 404 without calling the API", async (slug) => {
    expect((await load("id", slug)).status).toBe(404);
    expect(seenUrls).toHaveLength(0);
  });

  it("503 carries Retry-After: 120", async () => {
    apiResponse = () => new Response("boom", { status: 500 });
    const { status, headers } = await load("id");
    expect(status).toBe(503);
    expect(headers.get("retry-after")).toBe("120");
  });
});

describe("blog detail: images", () => {
  it("hero is the LCP image: fetchpriority=high, eager, no decoding=async", async () => {
    const hero = /<img class="post-hero"(?:"[^"]*"|[^>"])*>/.exec((await load("id")).html)![0];
    expect(hero).toContain('fetchpriority="high"');
    expect(hero).toContain('loading="eager"');
    expect(hero).not.toContain("decoding");
  });

  it("featured_image null: relative variants use the site origin", async () => {
    post.featured_image = null;
    const hero = /<img class="post-hero"(?:"[^"]*"|[^>"])*>/.exec((await load("id")).html)![0];
    expect(hero).toContain("https://alisadikinma.com/storage/posts/vibe-640w.webp 640w");
    expect(hero).toMatch(/src="https:\/\/alisadikinma\.com\/storage\/posts\/vibe-640w\.webp"/);
  });

  it("og_image over http is ignored in favour of the https featured image", async () => {
    (post.seo as Record<string, unknown>).og_image = "http://api.alisadikinma.com/o.png";
    expect((await load("id")).html).toContain(
      '<meta property="og:image" content="https://api.alisadikinma.com/storage/posts/vibe.png"',
    );
  });

  it("BlogPosting image falls back to the portrait when there is no image", async () => {
    post.featured_image = null;
    post.image_variants = null;
    (post.seo as Record<string, unknown>).og_image = null;
    const bp = ld((await load("id")).html).find((n) => n["@type"] === "BlogPosting")!;
    expect(bp.image).toBe(PORTRAIT_URL);
  });

  it("inline CMS images get real width/height and pass the gate", async () => {
    post.content = `<h2>H</h2><p>Teks.</p><img src="https://alisadikinma.com/storage/blog-images/a.jpg" alt="Diagram">`;
    const { html, headers } = await load("id");
    expect(html).toMatch(/<img [^>]*src="https:\/\/alisadikinma\.com\/storage\/blog-images\/a\.jpg"[^>]*width="800"[^>]*height="450"/);
    expect(checkHtml(html, specFor("id"), headers)).toEqual([]);
  });

  it("CMS ids cannot hijack site ids", async () => {
    post.content = '<h2 id="layanan">x</h2><h2 id="related-title">y</h2><p>t</p>';
    const { html } = await load("id");
    expect(html).toContain('id="uc-layanan"');
    expect(html.match(/id="related-title"/g)).toHaveLength(1);
    expect(html).not.toMatch(/id="layanan"/);
  });
});

describe("blog detail: related posts language", () => {
  it("links each related post in a language it exists in; assumes the page language otherwise", async () => {
    post.related_posts = [
      { id: 8, slug: "only-en", title: "Only English", featured_image: null, available_translations: ["en"] },
      { id: 9, slug: "both", title: "Both langs", featured_image: null, available_translations: ["id", "en"] },
      { id: 10, slug: "unknown", title: "No info", featured_image: null },
    ];
    const { html } = await load("id");
    expect(html).toContain('href="/en/blog/only-en"');
    expect(html).toContain('href="/id/blog/both"');
    expect(html).toContain('href="/id/blog/unknown"');
    expect(html).not.toContain('href="/id/blog/only-en"');
  });
});

// SEO audit 2026-11-09: each language has its own slug (the sitemap publishes them). The page must
// serve the localized slug, 301 the other one to it, and point hreflang/lang-switch at each language's slug.
describe("/{lang}/blog/{slug} per-language slugs", () => {
  const EN = "vibe-coding-for-factories";
  beforeEach(() => {
    post.available_translations = ["id", "en"];
    post.slugs = { id: SLUG, en: EN };
  });

  it("serves the EN slug with 200 and a self canonical", async () => {
    const { status, html } = await load("en", EN);
    expect(status).toBe(200);
    expect(html).toContain(`rel="canonical" href="https://alisadikinma.com/en/blog/${EN}"`);
  });

  it("hreflang id/en and the language switch use each language's own slug", async () => {
    const { html } = await load("en", EN);
    expect(html).toContain(`hreflang="id" href="https://alisadikinma.com/id/blog/${SLUG}"`);
    expect(html).toContain(`hreflang="en" href="https://alisadikinma.com/en/blog/${EN}"`);
    expect(html).toContain(`hreflang="x-default" href="https://alisadikinma.com/en/blog/${EN}"`);
    expect(html).toMatch(new RegExp(`class="lang flag" href="/id/blog/${SLUG}"`));
  });

  it("the other language's slug under /en 301s to the EN slug (query kept)", async () => {
    const { status, headers } = await load("en", SLUG, "?utm_source=x");
    expect(status).toBe(301);
    expect(headers.get("location")).toBe(`/en/blog/${EN}?utm_source=x`);
  });

  it("missing-language 302 goes to that language's own slug", async () => {
    post.available_translations = ["en"];
    post.slugs = { en: EN };
    const { status, headers } = await load("id", SLUG);
    expect(status).toBe(302);
    expect(headers.get("location")).toBe(`/en/blog/${EN}`);
  });

  it("without slugs in the payload, behaves as before (no redirect)", async () => {
    delete post.slugs;
    const { status } = await load("en", SLUG);
    expect(status).toBe(200);
  });
});
