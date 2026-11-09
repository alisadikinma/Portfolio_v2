// Phase 5A: every public URL of the OLD site (Vue router + Laravel web.php) must
// land on a live Astro page in 0 or 1 hop. Drives the real middleware; final
// 200 targets are checked against the Astro page tree.
import { readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { onRequest, resetCrawlerWarnings } from "../src/middleware";
import { resetApiCache } from "../src/lib/api";

const PAGES = join(import.meta.dirname, "..", "src", "pages");

function routeFiles(dir) {
  const out = [];
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) out.push(...routeFiles(p));
    else if (/\.(astro|ts)$/.test(name)) {
      const rel = relative(PAGES, p).replace(/\.(astro|ts)$/, "").replace(/(^|\/)index$/, "");
      out.push(rel.split("/").filter(Boolean));
    }
  }
  return out;
}
const ROUTES = routeFiles(PAGES);

/** True when an Astro page file serves this pathname (static or [param] segments). */
function hasPage(pathname) {
  const segs = pathname.split("/").filter(Boolean);
  return ROUTES.some((r) => r.length === segs.length && r.every((s, i) => (s === "[lang]" ? /^(id|en)$/.test(segs[i]) : /^\[.+\]$/.test(s) || s === segs[i])));
}

const ctx = (path, headers = {}) => {
  const request = new Request("https://alisadikinma.com" + path, { headers });
  return { request, url: new URL(request.url) };
};
const ok = () => Promise.resolve(new Response("page", { status: 200 }));
const run = (path, headers) => onRequest(ctx(path, headers), ok);

/** Mock public API: slug -> available_translations (missing -> 404). */
function mockPosts(map) {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input) => {
      const m = String(input).match(/\/posts\/([^/?]+)/);
      const langs = m ? map[decodeURIComponent(m[1])] : undefined;
      if (!langs) return new Response("{}", { status: 404 });
      return new Response(JSON.stringify({ data: { available_translations: langs } }), { status: 200 });
    }),
  );
}

beforeEach(() => {
  vi.stubEnv("INTERNAL_API_TOKEN", "t");
  vi.stubEnv("NODE_ENV", "test");
  resetCrawlerWarnings();
  resetApiCache();
  mockPosts({ both: ["id", "en"], "id-only": ["id"] });
});
afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

// [old path, expected final path+query]. Request has no Location of its own beyond one 301.
const MOVED = [
  // Vue legacy redirects (frontend/src/router/index.js lines 124-131)
  ["/about", "/en/about"],
  ["/about/", "/en/about"],
  ["/projects", "/en/projects"],
  ["/projects/x", "/en/projects/x"],
  ["/projects/x/", "/en/projects/x"],
  ["/work", "/en/work"],
  ["/blog", "/en/blog"],
  ["/blog/both", "/en/blog/both"],
  ["/blog/both/", "/en/blog/both"],
  ["/blog/missing", "/en/blog/missing"],
  ["/blog/id-only", "/id/blog/id-only"], // no chain 301->302
  ["/blog/category/x", "/en/blog/category/x"],
  ["/contact", "/en/contact"],
  ["/faq", "/en/faq"],
  // awards / gallery tabs
  ["/awards", "/en/work?tab=awards"],
  ["/gallery", "/en/work?tab=gallery"],
  ["/en/awards", "/en/work?tab=awards"],
  ["/id/awards", "/id/work?tab=awards"],
  ["/en/gallery", "/en/work?tab=gallery"],
  ["/id/gallery", "/id/work?tab=gallery"],
  ["/id/penghargaan", "/id/work?tab=awards"],
  // query strings + case + slash
  ["/about?utm_source=x&utm_medium=y", "/en/about?utm_source=x&utm_medium=y"],
  ["/blog/id-only?utm_campaign=z", "/id/blog/id-only?utm_campaign=z"],
  ["/awards?utm_source=x", "/en/work?tab=awards&utm_source=x"],
  ["/EN/about", "/en/about"],
  ["/ID/projects/x/", "/id/projects/x"],
  ["/en/about/", "/en/about"],
  // new-site sections reached unprefixed (not old URLs; default lang en, still one hop)
  ["/pelatihan-ai", "/en/ai-training"],
  ["/ai-training", "/en/ai-training"],
  ["/pembicara-ai", "/en/ai-speaker"],
  ["/ai-speaker", "/en/ai-speaker"],
];

const LIVE = [
  "/en",
  "/id",
  "/en/about",
  "/id/about",
  "/en/projects",
  "/en/projects/x",
  "/en/work",
  "/id/work?tab=awards",
  "/en/blog",
  "/en/blog/both",
  "/id/blog/id-only",
  "/en/blog/category/x",
  "/en/contact",
  "/en/faq",
  "/id/faq",
  "/id/pelatihan-ai",
  "/en/ai-training",
  "/id/pembicara-ai",
  "/en/ai-speaker",
  "/id/penghargaan/global-ai-demo-day",
  "/en/awards/global-ai-demo-day",
  "/robots.txt",
  "/sitemap.xml",
  "/sitemap-pages.xml",
  "/sitemap-posts.xml",
  "/sitemap-projects.xml",
];

// Not Astro: nginx routes these to Laravel / the Vue admin build (PF-2 5D).
const NGINX_OWNED = [
  ["/r/abc12", "Laravel ShortLinkController (302 to target)"],
  ["/login", "Vue admin SPA"],
  ["/newsletter/unsubscribe", "Vue SPA (token page)"],
  ["/admin", "Vue admin SPA"],
  ["/admin/posts", "Vue admin SPA"],
  ["/admin/content-engine/1/preview", "Vue admin SPA"],
];

describe("every old public URL: one 301 hop to a live page", () => {
  it.each(MOVED)("%s -> 301 %s", async (from, to) => {
    const res = await run(from);
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe(to);
    const hop = new URL(to, "https://alisadikinma.com");
    const final = await run(hop.pathname + hop.search);
    expect(final.status).toBe(200); // no second hop
    expect(hasPage(hop.pathname)).toBe(true);
  });
});

describe("live URLs pass straight through", () => {
  it.each(LIVE)("%s -> 200", async (p) => {
    const res = await run(p);
    expect(res.status).toBe(200);
    const pathname = p.split("?")[0];
    if (!/^\/(en|id)\/?$/.test(pathname)) expect(hasPage(pathname) || /^\/(robots\.txt|sitemap.*\.xml)$/.test(pathname)).toBe(true);
  });
});

describe("302 only for / and none elsewhere", () => {
  it("/ -> 302 language", async () => {
    expect((await run("/")).status).toBe(302);
  });
  it.each(MOVED.map((m) => m[0]))("%s is not 302", async (p) => {
    expect((await run(p)).status).not.toBe(302);
  });
});

describe("removed", () => {
  it.each(["/awards-debug", "/en/awards-debug", "/id/awards-debug"])("%s -> 404 directly", async (p) => {
    expect((await run(p)).status).toBe(404);
  });
});

describe("nginx-owned paths are not Astro pages", () => {
  it.each(NGINX_OWNED)("%s -> %s", (p) => {
    expect(hasPage(p)).toBe(false);
  });
});

describe("legacy awards/gallery keep the query (UTM)", () => {
  it("/awards?utm_source=x -> /en/work?tab=awards&utm_source=x", async () => {
    const r = await run("/awards?utm_source=x&utm_medium=y");
    expect(r.status).toBe(301);
    expect(r.headers.get("location")).toBe("/en/work?tab=awards&utm_source=x&utm_medium=y");
  });
  it("an old ?tab= never overrides the mapped tab", async () => {
    const r = await run("/id/gallery?tab=awards&ref=z");
    expect(r.headers.get("location")).toBe("/id/work?tab=gallery&ref=z");
  });
});
