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
