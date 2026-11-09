import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiGet, ApiError, resetApiCache } from "./api";

const BASE = "http://127.0.0.1:8080/api";
const ok = (data: unknown) =>
  new Response(JSON.stringify({ success: true, data }), { status: 200 });

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
  vi.useFakeTimers();
  resetApiCache();
  fetchMock = vi.fn();
  vi.stubGlobal("fetch", fetchMock);
});
afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe("apiGet", () => {
  it("builds url with lang, sends cookie, returns data", async () => {
    fetchMock.mockResolvedValueOnce(ok({ a: 1 }));
    const r = await apiGet("/posts", { lang: "id" });
    expect(r).toEqual({ a: 1 });
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`${BASE}/posts?lang=id`);
    expect(init.headers.Cookie).toBe("lang_preference=id");
  });

  it("appends lang with & when path has query", async () => {
    fetchMock.mockResolvedValueOnce(ok(1));
    await apiGet("/posts?page=2", { lang: "en" });
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/posts?page=2&lang=en`);
  });

  it("cache hit: one fetch for two calls", async () => {
    fetchMock.mockResolvedValue(ok("x"));
    await apiGet("/p", { lang: "id" });
    await apiGet("/p", { lang: "id" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("different lang is a different cache key", async () => {
    fetchMock.mockImplementation(async () => ok("x"));
    await apiGet("/p", { lang: "id" });
    await apiGet("/p", { lang: "en" });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("cache expiry: refetch after ttl", async () => {
    fetchMock.mockImplementation(async () => ok("x"));
    await apiGet("/p", { lang: "id", ttl: 1000 });
    vi.advanceTimersByTime(1001);
    await apiGet("/p", { lang: "id", ttl: 1000 });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("404 returns null", async () => {
    fetchMock.mockResolvedValueOnce(new Response("{}", { status: 404 }));
    expect(await apiGet("/posts/nope", { lang: "id" })).toBeNull();
  });

  it("5xx with cached value returns stale and warns with url+status", async () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(ok("old"));
    await apiGet("/p", { lang: "id", ttl: 1000 });
    vi.advanceTimersByTime(2000);
    fetchMock.mockResolvedValueOnce(new Response("boom", { status: 503 }));
    expect(await apiGet("/p", { lang: "id", ttl: 1000 })).toBe("old");
    expect(warn).toHaveBeenCalledWith("[api] stale", `${BASE}/p?lang=id`, 503);
  });

  it("network error with cached value returns stale", async () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(ok("old"));
    await apiGet("/p", { lang: "id", ttl: 1000 });
    vi.advanceTimersByTime(2000);
    fetchMock.mockRejectedValueOnce(new TypeError("fetch failed"));
    expect(await apiGet("/p", { lang: "id", ttl: 1000 })).toBe("old");
    expect(warn).toHaveBeenCalledWith("[api] stale", `${BASE}/p?lang=id`, 0);
  });

  it("5xx without cache throws ApiError", async () => {
    fetchMock.mockResolvedValueOnce(new Response("boom", { status: 500 }));
    await expect(apiGet("/p", { lang: "id" })).rejects.toMatchObject({
      name: "ApiError",
      status: 500,
      url: `${BASE}/p?lang=id`,
    });
  });

  it("timeout aborts after 4000ms and throws ApiError", async () => {
    fetchMock.mockImplementation(
      (_u: string, init: RequestInit) =>
        new Promise((_res, rej) => {
          init.signal!.addEventListener("abort", () => rej(new DOMException("aborted", "AbortError")));
        }),
    );
    const p = apiGet("/p", { lang: "id" });
    const assertion = expect(p).rejects.toBeInstanceOf(ApiError);
    await vi.advanceTimersByTimeAsync(4000);
    await assertion;
  });

  it("malformed JSON throws ApiError", async () => {
    fetchMock.mockResolvedValueOnce(new Response("<html>", { status: 200 }));
    await expect(apiGet("/p", { lang: "id" })).rejects.toBeInstanceOf(ApiError);
  });

  it("concurrent identical requests share one fetch", async () => {
    fetchMock.mockImplementation(async () => ok("x"));
    const [a, b] = await Promise.all([
      apiGet("/p", { lang: "id" }),
      apiGet("/p", { lang: "id" }),
    ]);
    expect(a).toBe("x");
    expect(b).toBe("x");
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});

// ---- 1C review fixes ----
import { apiGetPage, apiGetText, apiGetPost, apiPath, resetApiCache as _reset, CACHE_MAX_ENTRIES, MAX_STALE_MS } from "./api";

const page = (data: unknown[], extra: Record<string, unknown> = {}) =>
  new Response(JSON.stringify({ success: true, data, meta: { current_page: 1, last_page: 3 }, links: { next: "n" }, ...extra }), { status: 200 });

describe("apiGetPage", () => {
  it("returns data, meta and links", async () => {
    fetchMock.mockResolvedValueOnce(page([1, 2]));
    const r = await apiGetPage<number>("/posts?page=1", { lang: "id" });
    expect(r).toEqual({ data: [1, 2], meta: { current_page: 1, last_page: 3 }, links: { next: "n" } });
  });
  it("caches the whole body", async () => {
    fetchMock.mockImplementation(async () => page([1]));
    await apiGetPage("/posts", { lang: "id" });
    const r = await apiGetPage("/posts", { lang: "id" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(r?.meta).toBeTruthy();
  });
  it("404 returns null", async () => {
    fetchMock.mockResolvedValueOnce(new Response("{}", { status: 404 }));
    expect(await apiGetPage("/posts", { lang: "id" })).toBeNull();
  });
  it("missing meta/links become null", async () => {
    fetchMock.mockResolvedValueOnce(new Response(JSON.stringify({ data: [] }), { status: 200 }));
    expect(await apiGetPage("/posts", { lang: "id" })).toEqual({ data: [], meta: null, links: null });
  });
});

describe("cache policy", () => {
  it("evicts least recently used beyond the cap", async () => {
    fetchMock.mockImplementation(async () => ok("x"));
    for (let i = 0; i < CACHE_MAX_ENTRIES + 5; i++) await apiGet(`/p${i}`, { lang: "id" });
    fetchMock.mockClear();
    await apiGet("/p0", { lang: "id" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
    await apiGet(`/p${CACHE_MAX_ENTRIES + 4}`, { lang: "id" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
  it("does not serve stale older than max-stale", async () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(ok("old"));
    await apiGet("/p", { lang: "id", ttl: 1000 });
    vi.advanceTimersByTime(1000 + MAX_STALE_MS + 1);
    fetchMock.mockResolvedValueOnce(new Response("boom", { status: 503 }));
    await expect(apiGet("/p", { lang: "id", ttl: 1000 })).rejects.toBeInstanceOf(ApiError);
  });
  it("never uses cached null (404) as stale fallback", async () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(new Response("{}", { status: 404 }));
    expect(await apiGet("/p", { lang: "id", ttl: 1000 })).toBeNull();
    vi.advanceTimersByTime(2000);
    fetchMock.mockResolvedValueOnce(new Response("boom", { status: 503 }));
    await expect(apiGet("/p", { lang: "id", ttl: 1000 })).rejects.toBeInstanceOf(ApiError);
  });
});

describe("path safety", () => {
  it("rejects path without leading slash", async () => {
    await expect(apiGet("posts", { lang: "id" })).rejects.toThrow(/start with "\/"/);
    expect(fetchMock).not.toHaveBeenCalled();
  });
  it("apiPath encodes each dynamic segment", () => {
    expect(apiPath`/posts/${"foo?preview=1"}`).toBe("/posts/foo%3Fpreview%3D1");
    expect(apiPath`/a/${"x/y"}/b/${"é"}`).toBe("/a/x%2Fy/b/%C3%A9");
  });
  it("apiPath rejects dot segments and empty", () => {
    expect(() => apiPath`/posts/${".."}`).toThrow();
    expect(() => apiPath`/posts/${""}`).toThrow();
  });
  it("slug with query chars cannot inject a query param", async () => {
    fetchMock.mockResolvedValueOnce(ok(1));
    await apiGet(apiPath`/posts/${"foo?preview=1"}`, { lang: "id" });
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/posts/foo%3Fpreview%3D1?lang=id`);
  });
});

describe("apiGetText", () => {
  it("returns raw text, caches, 404 null", async () => {
    fetchMock.mockImplementation(async () => new Response("<urlset/>", { status: 200 }));
    expect(await apiGetText("/sitemap.xml")).toBe("<urlset/>");
    await apiGetText("/sitemap.xml");
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/sitemap.xml`);
    resetApiCache();
    fetchMock.mockResolvedValueOnce(new Response("", { status: 404 }));
    expect(await apiGetText("/llms.txt")).toBeNull();
  });
  it("stale text on 5xx", async () => {
    vi.spyOn(console, "warn").mockImplementation(() => {});
    fetchMock.mockResolvedValueOnce(new Response("t", { status: 200 }));
    await apiGetText("/llms.txt", { ttl: 1000 });
    vi.advanceTimersByTime(2000);
    fetchMock.mockResolvedValueOnce(new Response("e", { status: 500 }));
    expect(await apiGetText("/llms.txt", { ttl: 1000 })).toBe("t");
  });
  it("times out as status 0", async () => {
    fetchMock.mockImplementation(
      (_u: string, init: RequestInit) =>
        new Promise((_r, rej) => init.signal!.addEventListener("abort", () => rej(new DOMException("a", "AbortError")))),
    );
    const a = expect(apiGetText("/llms.txt")).rejects.toMatchObject({ status: 0 });
    await vi.advanceTimersByTimeAsync(4000);
    await a;
  });
});

describe("post detail tracking", () => {
  it("apiGetPost sends track=0 and encodes slug", async () => {
    fetchMock.mockResolvedValueOnce(ok({ id: 1 }));
    await apiGetPost("a?b", { lang: "en" });
    expect(fetchMock.mock.calls[0][0]).toBe(`${BASE}/posts/a%3Fb?track=0&lang=en`);
  });
});

describe("timeout during body read", () => {
  it("reports status 0, not malformed JSON", async () => {
    fetchMock.mockImplementation(async (_u: string, init: RequestInit) => ({
      status: 200,
      ok: true,
      json: () =>
        new Promise((_r, rej) => init.signal!.addEventListener("abort", () => rej(new SyntaxError("Unexpected end")))),
    }));
    const a = expect(apiGet("/p", { lang: "id" })).rejects.toMatchObject({ status: 0 });
    await vi.advanceTimersByTimeAsync(4000);
    await a;
  });
});

describe("request headers", () => {
  it("only sends Accept and Cookie: lang_preference; never Authorization or session cookies", async () => {
    fetchMock.mockResolvedValue(ok(1));
    await apiGet("/p", { lang: "id" });
    const init = fetchMock.mock.calls[0][1];
    expect(Object.keys(init.headers).sort()).toEqual(["Accept", "Cookie"]);
    expect(init.headers.Cookie).toBe("lang_preference=id");
    expect(init.credentials).toBeUndefined();
  });
});
