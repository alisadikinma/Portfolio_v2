import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { onRequest, resetCrawlerWarnings } from "./middleware";

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
});
afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("redirects", () => {
  it("/ with id Accept-Language -> 302 /id/", async () => {
    const res = (await onRequest(ctx("/", { "accept-language": "id-ID" }), ok)) as Response;
    expect(res.status).toBe(302);
    expect(res.headers.get("location")).toBe("/id/");
  });
  it("/ without header -> 302 /en/", async () => {
    const res = (await onRequest(ctx("/"), ok)) as Response;
    expect(res.status).toBe(302);
    expect(res.headers.get("location")).toBe("/en/");
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
});

describe("static assets", () => {
  it.each(["/_astro/app.js", "/robots.txt", "/images/a.png", "/llms.txt"])("%s untouched and not counted", async (p) => {
    const f = vi.fn();
    vi.stubGlobal("fetch", f);
    const res = (await onRequest(ctx(p, { "user-agent": "GPTBot/1.0" }), ok)) as Response;
    expect(res.status).toBe(200);
    expect(f).not.toHaveBeenCalled();
  });
});

describe("crawler logging", () => {
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
