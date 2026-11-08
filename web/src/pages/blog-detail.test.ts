import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { blogPostSpec } from "../../scripts/seo-gate.config.mjs";
import BlogDetail from "./[lang]/blog/[slug].astro";

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

beforeEach(() => {
  resetApiCache();
  seenUrls = [];
  post = basePost();
  apiResponse = () => new Response(JSON.stringify({ success: true, data: post }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      seenUrls.push(String(url));
      if (String(url).includes("/posts/")) return apiResponse(String(url));
      return new Response(JSON.stringify({ success: true, data: {} }), { status: 200 });
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, slug = SLUG) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(BlogDetail, {
    params: { lang, slug },
    request: new Request(`https://alisadikinma.com/${lang}/blog/${slug}`),
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

  it("post without the requested language -> 301 to the available language", async () => {
    post.available_translations = ["en"];
    const { status, headers } = await load("id");
    expect(status).toBe(301);
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
