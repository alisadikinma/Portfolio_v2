import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { readFileSync } from "node:fs";
import { resetApiCache } from "../lib/api";
import { robotsBlocks } from "../../scripts/seo-gate.mjs";
import { GET as robots } from "../pages/robots.txt";
import { GET as sitemapIndex } from "../pages/sitemap.xml";
import { GET as sitemapPages } from "../pages/sitemap-pages.xml";
import { GET as sitemapPosts } from "../pages/sitemap-posts.xml";
import { GET as sitemapProjects } from "../pages/sitemap-projects.xml";

const call = (fn: unknown) => (fn as (c: { request: Request }) => Promise<Response>)({ request: new Request("https://alisadikinma.com/x") });
const XML = '<?xml version="1.0"?><urlset><url><loc>https://alisadikinma.com/id/about</loc></url></urlset>';

let upstream: (url: string) => Response;
beforeEach(() => {
  resetApiCache();
  upstream = () => new Response(XML, { status: 200 });
  vi.stubGlobal("fetch", vi.fn(async (url: string) => upstream(String(url))));
});
afterEach(() => vi.unstubAllGlobals());

describe("/robots.txt", () => {
  it("is served as text/plain, allows public routes, keeps the AI allow-list, points at the sitemap index", async () => {
    const res = await call(robots);
    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toMatch(/^text\/plain/);
    const body = await res.text();
    for (const bot of ["GPTBot", "ClaudeBot", "PerplexityBot", "GoogleOther", "Google-Extended", "OAI-SearchBot"]) {
      expect(body).toContain(`User-agent: ${bot}`);
      expect(robotsBlocks(body, "/id/pelatihan-ai", bot)).toBe(false);
    }
    expect(robotsBlocks(body, "/en/ai-speaker", "*")).toBe(false);
    expect(robotsBlocks(body, "/admin/posts", "*")).toBe(true);
    expect(body).toContain("Sitemap: https://alisadikinma.com/sitemap.xml");
    expect(body).not.toMatch(/\/api\/sitemap/);
  });
});

describe("sitemaps", () => {
  it("/sitemap.xml is an index of the three child sitemaps on the public host", async () => {
    const res = await call(sitemapIndex);
    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toMatch(/xml/);
    const body = await res.text();
    const locs = [...body.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
    expect(locs).toEqual([
      "https://alisadikinma.com/sitemap-pages.xml",
      "https://alisadikinma.com/sitemap-posts.xml",
      "https://alisadikinma.com/sitemap-projects.xml",
    ]);
    expect(body).not.toContain("<lastmod>");
  });

  it.each([
    ["pages", sitemapPages, "/sitemap.xml"],
    ["posts", sitemapPosts, "/sitemap-posts.xml"],
    ["projects", sitemapProjects, "/sitemap-projects.xml"],
  ])("child %s proxies the Laravel sitemap", async (_n, fn, apiPath) => {
    const seen: string[] = [];
    upstream = (u) => (seen.push(u), new Response(XML, { status: 200 }));
    const res = await call(fn);
    expect(res.status).toBe(200);
    expect(res.headers.get("content-type")).toMatch(/xml/);
    expect(await res.text()).toBe(XML);
    expect(seen[0]).toMatch(new RegExp(`/api${apiPath.replace(/\./g, "\\.")}$|${apiPath.replace(/\./g, "\\.")}$`));
  });

  it("answers 502 (not a fake empty sitemap) when the API is down", async () => {
    upstream = () => new Response("boom", { status: 500 });
    const res = await call(sitemapPosts);
    expect(res.status).toBe(502);
    expect(await res.text()).not.toContain("<loc>");
  });

  it("answers 502 when the API returns 404", async () => {
    upstream = () => new Response("nope", { status: 404 });
    expect((await call(sitemapPages)).status).toBe(502);
  });
});

describe("static llms.txt", () => {
  const body = readFileSync(new URL("../../public/llms.txt", import.meta.url), "utf8");
  it("has the corrected identity and language-prefixed pages", () => {
    expect(body).toContain("# Ali Sadikin Ma — pakar AI Indonesia / AI consultant, founder INDUSIA.ai (https://indusia.ai)");
    for (const p of ["/id/pelatihan-ai", "/en/ai-training", "/id/pembicara-ai", "/en/ai-speaker", "/id/about", "/en/faq"]) {
      expect(body).toContain(`https://alisadikinma.com${p}`);
    }
    expect(body).toContain("56+ digital products (AI, IoT, mobile) built at Satnusa");
  });
  it("has no unsupported claims or unprefixed page links", () => {
    expect(body).not.toMatch(/\bAI Generalist|16 countries|16 negara|56 AI products|Startup Grind|IDBYTE/i);
    expect(body).not.toMatch(/https:\/\/alisadikinma\.com\/(about|faq|blog|projects|contact)\b/);
  });
  it("keeps frontend/public/llms.txt identical until the cutover removes it", () => {
    const old = readFileSync(new URL("../../../frontend/public/llms.txt", import.meta.url), "utf8");
    expect(old).toBe(body);
  });
});
