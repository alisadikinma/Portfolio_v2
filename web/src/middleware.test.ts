import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { onRequest, resetCrawlerWarnings } from "./middleware";
import { resetApiCache } from "./lib/api";

type Ctx = Parameters<typeof onRequest>[0];

function ctx(path: string, headers: Record<string, string> = {}): Ctx {
  const request = new Request("https://alisadikinma.com" + path, { headers });
  return { request, url: new URL(request.url) } as unknown as Ctx;
}
const ok = () => Promise.resolve(new Response("page", { status: 200 }));

beforeEach(() => {
  vi.stubEnv("INTERNAL_API_TOKEN", "secret-token-xyz");
  vi.stubEnv("NODE_ENV", "test");
  resetCrawlerWarnings();
  resetApiCache();
});
afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("redirects", () => {
  it("/ with id Accept-Language -> 302 /id (no slash)", async () => {
    const res = (await onRequest(ctx("/", { "accept-language": "id-ID" }), ok)) as Response;
    expect(res.status).toBe(302);
    expect(res.headers.get("location")).toBe("/id");
  });
  it("/ without header -> 302 /en", async () => {
    const res = (await onRequest(ctx("/"), ok)) as Response;
    expect(res.status).toBe(302);
    expect(res.headers.get("location")).toBe("/en");
  });
  it("/ sends no-store + Vary", async () => {
    const res = (await onRequest(ctx("/"), ok)) as Response;
    expect(res.headers.get("cache-control")).toBe("private, no-store");
    expect(res.headers.get("vary")).toBe("Accept-Language, Cookie");
  });
  it("/ keeps query string", async () => {
    const res = (await onRequest(ctx("/?utm_source=x"), ok)) as Response;
    expect(res.headers.get("location")).toBe("/en?utm_source=x");
  });
  it("lang_preference cookie beats Accept-Language", async () => {
    const res = (await onRequest(
      ctx("/", { cookie: "a=b; lang_preference=id", "accept-language": "en-US" }),
      ok,
    )) as Response;
    expect(res.headers.get("location")).toBe("/id");
    const res2 = (await onRequest(
      ctx("/", { cookie: "lang_preference=en", "accept-language": "id-ID" }),
      ok,
    )) as Response;
    expect(res2.headers.get("location")).toBe("/en");
  });
  it("invalid cookie value falls back to Accept-Language", async () => {
    const res = (await onRequest(
      ctx("/", { cookie: "lang_preference=fr", "accept-language": "id-ID" }),
      ok,
    )) as Response;
    expect(res.headers.get("location")).toBe("/id");
  });
  it.each([
    ["/blog/x", "/en/blog/x"],
    ["/about", "/en/about"],
    ["/faq", "/en/faq"],
    ["/en/awards", "/en/work?tab=awards"],
    ["/en/gallery", "/en/work?tab=gallery"],
    ["/en/awards/", "/en/work?tab=awards"],
  ])("%s -> 301 %s", async (from, to) => {
    const res = (await onRequest(ctx(from), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe(to);
  });
  it("keeps query string on prefix redirect", async () => {
    const res = (await onRequest(ctx("/blog?page=2"), ok)) as Response;
    expect(res.headers.get("location")).toBe("/en/blog?page=2");
  });
  it("/en/awards-debug -> 404", async () => {
    const res = (await onRequest(ctx("/en/awards-debug"), ok)) as Response;
    expect(res.status).toBe(404);
  });
  it("/en/awards/slug is not redirected", async () => {
    const res = (await onRequest(ctx("/en/awards/global-ai-demo-day"), ok)) as Response;
    expect(res.status).toBe(200);
  });
  it("prefixed page passes through", async () => {
    const res = (await onRequest(ctx("/id/about"), ok)) as Response;
    expect(res.status).toBe(200);
  });
  it("HTML pages get new-tab links; status, headers and non-HTML bodies are kept", async () => {
    const html = () => Promise.resolve(new Response('<a href="/id/faq">F</a><a href="#x">S</a>',
      { status: 404, headers: { "Content-Type": "text/html; charset=utf-8", "X-Keep": "1", "Content-Length": "40" } }));
    const res = (await onRequest(ctx("/id/nope"), html)) as Response;
    expect(res.status).toBe(404);
    expect(res.headers.get("X-Keep")).toBe("1");
    expect(res.headers.get("Content-Length")).toBeNull();
    expect(await res.text()).toBe('<a href="/id/faq" target="_blank">F</a><a href="#x">S</a>');
    const json = () => Promise.resolve(new Response('<a href="/y">', { headers: { "Content-Type": "application/json" } }));
    expect(await ((await onRequest(ctx("/id/x"), json)) as Response).text()).toBe('<a href="/y">');
  });
});

describe("trailing slash + uppercase prefix", () => {
  it.each([
    ["/en/about/", "/en/about"],
    ["/id/blog/x/?page=2", "/id/blog/x?page=2"],
    ["/EN/about", "/en/about"],
    ["/ID/blog/x/", "/id/blog/x"],
    ["/EN", "/en"],
  ])("%s -> 301 %s", async (from, to) => {
    const res = (await onRequest(ctx(from), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe(to);
  });
  it("/en is not redirected", async () => {
    expect(((await onRequest(ctx("/en"), ok)) as Response).status).toBe(200);
  });
});

describe("legacy awards/gallery: exactly one hop", () => {
  it.each([
    ["/awards", "/en/work?tab=awards"],
    ["/awards/", "/en/work?tab=awards"],
    ["/gallery", "/en/work?tab=gallery"],
    ["/penghargaan", "/en/work?tab=awards"],
    ["/id/awards", "/id/work?tab=awards"],
    ["/id/penghargaan", "/id/work?tab=awards"],
    ["/id/gallery/", "/id/work?tab=gallery"],
    ["/en/penghargaan", "/en/work?tab=awards"],
    ["/en/awards", "/en/work?tab=awards"],
    ["/EN/gallery", "/en/work?tab=gallery"],
  ])("%s -> 301 %s", async (from, to) => {
    const res = (await onRequest(ctx(from), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe(to);
    // target must itself pass through untouched
    const hop2 = (await onRequest(ctx(to), ok)) as Response;
    expect(hop2.status).toBe(200);
  });
  it.each(["/awards-debug", "/awards-debug/", "/id/awards-debug", "/en/awards-debug/", "/EN/awards-debug"])(
    "%s -> 404 directly",
    async (p) => {
      expect(((await onRequest(ctx(p), ok)) as Response).status).toBe(404);
    },
  );
});

describe("unprefixed blog/project language fallback", () => {
  const post = (langs: string[]) =>
    new Response(JSON.stringify({ data: { available_translations: langs } }), { status: 200 });

  it("EN exists -> /en", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(post(["id", "en"])));
    const res = (await onRequest(ctx("/blog/hello"), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe("/en/blog/hello");
  });
  it("no EN -> first non-en language", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(post(["id"])));
    const res = (await onRequest(ctx("/blog/halo?x=1"), ok)) as Response;
    expect(res.headers.get("location")).toBe("/id/blog/halo?x=1");
  });
  it("API failure -> /en", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    const res = (await onRequest(ctx("/blog/boom"), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe("/en/blog/boom");
  });
  it("post 404 -> /en", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status: 404 })));
    const res = (await onRequest(ctx("/blog/gone"), ok)) as Response;
    expect(res.headers.get("location")).toBe("/en/blog/gone");
  });
  it("projects always /en and make no API call", async () => {
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    const res = (await onRequest(ctx("/projects/p1"), ok)) as Response;
    expect(res.headers.get("location")).toBe("/en/projects/p1");
    expect(f).not.toHaveBeenCalled();
  });
});

describe("static assets", () => {
  it("/en/blog/gpt-4.5 is NOT static", async () => {
    vi.stubGlobal("fetch", vi.fn());
    const spy = vi.fn(ok);
    const res = (await onRequest(ctx("/en/blog/gpt-4.5", { "user-agent": "GPTBot" }), spy)) as Response;
    expect(res.status).toBe(200);
    expect(globalThis.fetch).toHaveBeenCalledTimes(1); // crawler hit counted
  });
  it("unprefixed /blog/gpt-4.5 is redirected, not static", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("x")));
    const res = (await onRequest(ctx("/blog/gpt-4.5"), ok)) as Response;
    expect(res.status).toBe(301);
  });
  it.each(["/_astro/app.js", "/_image", "/robots.txt", "/images/a.png", "/llms.txt"])("%s untouched and not counted", async (p) => {
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    const res = (await onRequest(ctx(p, { "user-agent": "GPTBot/1.0" }), ok)) as Response;
    expect(res.status).toBe(200);
    expect(f).not.toHaveBeenCalled();
  });
});

describe("crawler logging", () => {
  it("rate-limits failure warnings to once per minute per status", async () => {
    vi.useFakeTimers();
    try {
      vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 401 })));
      const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
      for (let i = 0; i < 3; i++) {
        await onRequest(ctx("/id/about", { "user-agent": "CCBot" }), ok);
        await vi.advanceTimersByTimeAsync(0);
      }
      expect(warn).toHaveBeenCalledTimes(1);
      vi.setSystemTime(Date.now() + 61_000);
      await onRequest(ctx("/id/about", { "user-agent": "CCBot" }), ok);
      await vi.advanceTimersByTimeAsync(0);
      expect(warn).toHaveBeenCalledTimes(2);
    } finally {
      vi.useRealTimers();
    }
  });

  it("posts canonical bot with token and timeout signal", async () => {
    const f = vi.fn().mockResolvedValue(new Response(null, { status: 202 }));
    vi.stubGlobal("fetch", f);
    await onRequest(ctx("/id/about", { "user-agent": "Mozilla/5.0 (compatible; GPTBot/1.2)" }), ok);
    expect(f).toHaveBeenCalledTimes(1);
    const [url, init] = f.mock.calls[0];
    expect(String(url)).toMatch(/\/internal\/crawler-hit$/);
    expect(init.method).toBe("POST");
    expect(init.headers["X-Internal-Token"]).toBe("secret-token-xyz");
    expect(JSON.parse(init.body)).toEqual({ bot: "GPTBot" });
    expect(init.signal).toBeInstanceOf(AbortSignal);
  });
  it("no post for humans", async () => {
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    await onRequest(ctx("/id/about", { "user-agent": "Mozilla/5.0 Chrome" }), ok);
    expect(f).not.toHaveBeenCalled();
  });
  it("rejected fetch: status 200, no throw, warning without token", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("boom")));
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const res = (await onRequest(ctx("/id/about", { "user-agent": "ClaudeBot" }), ok)) as Response;
    await new Promise((r) => setTimeout(r, 0));
    expect(res.status).toBe(200);
    expect(warn).toHaveBeenCalled();
    expect(JSON.stringify(warn.mock.calls)).not.toContain("secret-token-xyz");
  });
  it("hanging fetch does not delay the response", async () => {
    vi.stubGlobal("fetch", vi.fn(() => new Promise(() => {})));
    const t = Date.now();
    const res = (await onRequest(ctx("/id/about", { "user-agent": "ClaudeBot" }), ok)) as Response;
    expect(res.status).toBe(200);
    expect(Date.now() - t).toBeLessThan(200);
  });
  it("non-2xx from backend warns, response still 200", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 401 })));
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const res = (await onRequest(ctx("/id/about", { "user-agent": "CCBot" }), ok)) as Response;
    await new Promise((r) => setTimeout(r, 0));
    expect(res.status).toBe(200);
    expect(warn).toHaveBeenCalled();
  });
  it("missing token: no send, warn once", async () => {
    vi.stubEnv("INTERNAL_API_TOKEN", "");
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    await onRequest(ctx("/id/about", { "user-agent": "GPTBot" }), ok);
    await onRequest(ctx("/id/about", { "user-agent": "GPTBot" }), ok);
    expect(f).not.toHaveBeenCalled();
    expect(warn).toHaveBeenCalledTimes(1);
  });
  it("missing token in production: no throw, no send", async () => {
    vi.stubEnv("INTERNAL_API_TOKEN", "");
    vi.stubEnv("NODE_ENV", "production");
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    vi.spyOn(console, "warn").mockImplementation(() => {});
    const res = (await onRequest(ctx("/id/about", { "user-agent": "GPTBot" }), ok)) as Response;
    expect(res.status).toBe(200);
    expect(f).not.toHaveBeenCalled();
  });
});

// SEO audit 2026-11-09: old /blog/<slug> goes straight to the language's own slug (one hop).
describe("unprefixed blog -> localized slug", () => {
  it("uses the EN slug when the post has one", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { available_translations: ["id", "en"], slugs: { id: "halo", en: "hello" } } }), { status: 200 })));
    const res = (await onRequest(ctx("/blog/halo?utm=1"), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe("/en/blog/hello?utm=1");
  });
});

describe("security headers on pages", () => {
  it("adds Referrer-Policy and Permissions-Policy to page responses", async () => {
    const res = (await onRequest(ctx("/id/about"), ok)) as Response;
    expect(res.headers.get("referrer-policy")).toBe("strict-origin-when-cross-origin");
    expect(res.headers.get("permissions-policy")).toBe("camera=(), microphone=(), geolocation=(), payment=()");
  });
  it("keeps a header a page already set", async () => {
    const own = () => Promise.resolve(new Response("p", { status: 200, headers: { "Referrer-Policy": "no-referrer" } }));
    const res = (await onRequest(ctx("/id/about"), own)) as Response;
    expect(res.headers.get("referrer-policy")).toBe("no-referrer");
  });
});

describe("canonical host (PF-3)", () => {
  it("www host -> 301 to apex, path and query kept", async () => {
    const res = (await onRequest(ctx("/id/pelatihan-ai?utm_source=x", { host: "www.alisadikinma.com" }), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe("https://alisadikinma.com/id/pelatihan-ai?utm_source=x");
  });
  it("www root -> 301 to apex root (one hop, not the language redirect)", async () => {
    const res = (await onRequest(ctx("/", { host: "WWW.alisadikinma.com:443" }), ok)) as Response;
    expect(res.status).toBe(301);
    expect(res.headers.get("location")).toBe("https://alisadikinma.com/");
  });
  it("www static asset also redirects", async () => {
    const res = (await onRequest(ctx("/_astro/a.js", { host: "www.alisadikinma.com" }), ok)) as Response;
    expect(res.status).toBe(301);
  });
  it("apex host is served, loopback host is served", async () => {
    const a = (await onRequest(ctx("/id", { host: "alisadikinma.com" }), ok)) as Response;
    expect(a.status).toBe(200);
    const b = (await onRequest(ctx("/id", { host: "127.0.0.1:4321" }), ok)) as Response;
    expect(b.status).toBe(200);
  });
});

describe("edge cache headers (PF-3)", () => {
  const html = () => Promise.resolve(new Response("<html></html>", { status: 200, headers: { "content-type": "text/html" } }));
  it("public HTML page gets a shared-cache header", async () => {
    const res = (await onRequest(ctx("/id/pembicara-ai"), html)) as Response;
    expect(res.headers.get("cache-control")).toBe("public, max-age=0, s-maxage=300, stale-while-revalidate=86400");
  });
  it("a page that set its own Cache-Control keeps it", async () => {
    const own = () => Promise.resolve(new Response("x", { status: 200, headers: { "content-type": "text/html", "Cache-Control": "private, no-store" } }));
    const res = (await onRequest(ctx("/id/contact"), own)) as Response;
    expect(res.headers.get("cache-control")).toBe("private, no-store");
  });
  it("404 is not shared-cached", async () => {
    const nf = () => Promise.resolve(new Response("nf", { status: 404, headers: { "content-type": "text/html" } }));
    const res = (await onRequest(ctx("/id/nope"), nf)) as Response;
    expect(res.headers.get("cache-control")).toBeNull();
  });
  it("non-GET is not shared-cached", async () => {
    const request = new Request("https://alisadikinma.com/id", { method: "POST" });
    const res = (await onRequest({ request, url: new URL(request.url) } as unknown as Ctx, html)) as Response;
    expect(res.headers.get("cache-control")).toBeNull();
  });
});
