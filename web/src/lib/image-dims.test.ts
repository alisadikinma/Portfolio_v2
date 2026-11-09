import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { addImageDimensions, resetImageDimsCache, DIMS_CACHE_MAX } from "./image-dims";

// Smallest valid PNG header: signature + IHDR (width x height) is all image-size needs.
function png(w: number, h: number): Uint8Array<ArrayBuffer> {
  const b = new Uint8Array(new ArrayBuffer(33));
  b.set([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a], 0);
  const v = new DataView(b.buffer);
  v.setUint32(8, 13);
  b.set([0x49, 0x48, 0x44, 0x52], 12);
  v.setUint32(16, w);
  v.setUint32(20, h);
  b[24] = 8;
  b[25] = 6;
  return b;
}

const URL1 = "https://alisadikinma.com/storage/blog-images/a.png";
const img = (src: string, extra = "") => `<img src="${src}" alt="" ${extra}/>`;

let fetchMock: ReturnType<typeof vi.fn>;
beforeEach(() => {
  resetImageDimsCache();
  fetchMock = vi.fn(async () => new Response(png(800, 450), { status: 206 }));
  vi.stubGlobal("fetch", fetchMock);
});
afterEach(() => {
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe("addImageDimensions", () => {
  it("injects width and height from the first bytes", async () => {
    const out = await addImageDimensions(`<p>x</p>${img(URL1)}`);
    expect(out).toContain('width="800"');
    expect(out).toContain('height="450"');
    expect(out).toContain("<p>x</p>");
  });

  it("sends a Range request and an abort signal", async () => {
    await addImageDimensions(img(URL1));
    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers.Range).toBe("bytes=0-65535");
    expect(init.signal).toBeInstanceOf(AbortSignal);
  });

  it("refuses redirects so a public URL cannot bounce into a private host", async () => {
    await addImageDimensions(img(URL1));
    const [, init] = fetchMock.mock.calls[0];
    expect(init.redirect).toBe("error");
  });

  it("respects existing width and height and does not fetch", async () => {
    const html = img(URL1, 'width="10" height="20"');
    expect(await addImageDimensions(html)).toBe(html);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("fills the missing side from the aspect ratio when only one is set", async () => {
    const out = await addImageDimensions(img(URL1, 'width="400"'));
    expect(out).toContain('width="400"');
    expect(out).toContain('height="225"');
  });

  it("decodes &amp; in src before fetching", async () => {
    await addImageDimensions(img("https://a.test/x.png?a=1&amp;b=2"));
    expect(fetchMock.mock.calls[0][0]).toBe("https://a.test/x.png?a=1&b=2");
  });

  it("leaves relative, data: and private-host images alone", async () => {
    const html = img("/storage/a.png") + img("data:image/png;base64,AAAA") + img("http://127.0.0.1/a.png") + img("http://localhost/a.png");
    expect(await addImageDimensions(html)).toBe(html);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("404 -> unchanged", async () => {
    fetchMock.mockResolvedValue(new Response("nope", { status: 404 }));
    const html = img(URL1);
    expect(await addImageDimensions(html)).toBe(html);
  });

  it("non-image bytes -> unchanged", async () => {
    fetchMock.mockResolvedValue(new Response("<html>not an image</html>", { status: 200 }));
    const html = img(URL1);
    expect(await addImageDimensions(html)).toBe(html);
  });

  it("network error -> unchanged", async () => {
    fetchMock.mockRejectedValue(new Error("down"));
    const html = img(URL1);
    expect(await addImageDimensions(html)).toBe(html);
  });

  it("timeout aborts the request -> unchanged", async () => {
    fetchMock.mockImplementation(
      (_u: string, init: { signal: AbortSignal }) =>
        new Promise((_res, rej) => init.signal.addEventListener("abort", () => rej(new Error("aborted")))),
    );
    const html = img(URL1);
    expect(await addImageDimensions(html, { timeoutMs: 20 })).toBe(html);
  });

  it("cache hit: same URL fetched once across calls", async () => {
    await addImageDimensions(img(URL1));
    const out = await addImageDimensions(img(URL1));
    expect(out).toContain('width="800"');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("in-flight dedupe: same URL twice in one document fetched once", async () => {
    const out = await addImageDimensions(img(URL1) + img(URL1));
    expect(out.match(/width="800"/g)).toHaveLength(2);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("failures are cached briefly, then retried", async () => {
    vi.useFakeTimers({ toFake: ["Date"] });
    fetchMock.mockResolvedValue(new Response("x", { status: 500 }));
    await addImageDimensions(img(URL1));
    await addImageDimensions(img(URL1));
    expect(fetchMock).toHaveBeenCalledTimes(1);
    vi.setSystemTime(Date.now() + 11 * 60 * 1000);
    fetchMock.mockResolvedValue(new Response(png(10, 10), { status: 200 }));
    expect(await addImageDimensions(img(URL1))).toContain('width="10"');
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("success cache is bounded (LRU)", async () => {
    expect(DIMS_CACHE_MAX).toBe(1000);
    fetchMock.mockImplementation(async () => new Response(png(1, 1), { status: 200 }));
    for (let i = 0; i < DIMS_CACHE_MAX + 5; i++) await addImageDimensions(img(`https://a.test/${i}.png`));
    fetchMock.mockClear();
    await addImageDimensions(img("https://a.test/0.png")); // evicted -> refetched
    expect(fetchMock).toHaveBeenCalledTimes(1);
    await addImageDimensions(img(`https://a.test/${DIMS_CACHE_MAX + 4}.png`)); // newest -> cached
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("returns empty/no-img input unchanged", async () => {
    expect(await addImageDimensions("")).toBe("");
    expect(await addImageDimensions("<p>none</p>")).toBe("<p>none</p>");
  });
});

describe("IMAGE_DIMS_ORIGIN (same-host loopback)", () => {
  const LOOP = "http://127.0.0.1:8081";
  const fetchedUrls = () => fetchMock.mock.calls.map((c) => String(c[0]));
  afterEach(() => vi.unstubAllEnvs());

  it("reads own-site /storage images from the loopback origin", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP);
    const out = await addImageDimensions(img(URL1));
    expect(fetchedUrls()).toEqual([`${LOOP}/storage/blog-images/a.png`]);
    expect(out).toContain('width="800"');
  });

  it("also rewrites /uploads and keeps the query string", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP + "/");
    await addImageDimensions(img("https://alisadikinma.com/uploads/x/b.png?v=2"));
    expect(fetchedUrls()).toEqual([`${LOOP}/uploads/x/b.png?v=2`]);
  });

  it("does not rewrite other paths on the own site", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP);
    await addImageDimensions(img("https://alisadikinma.com/images/p.png"));
    expect(fetchedUrls()).toEqual(["https://alisadikinma.com/images/p.png"]);
  });

  it("never sends foreign hosts to the loopback origin", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP);
    await addImageDimensions(img("https://evil.example/storage/a.png"));
    expect(fetchedUrls()).toEqual(["https://evil.example/storage/a.png"]);
  });

  it("is a no-op when the env var is unset or not http(s)", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", "");
    await addImageDimensions(img(URL1));
    vi.stubEnv("IMAGE_DIMS_ORIGIN", "ftp://x");
    resetImageDimsCache();
    await addImageDimensions(img(URL1));
    expect(fetchedUrls()).toEqual([URL1, URL1]);
  });

  it.each([
    ["encoded slash", "https://alisadikinma.com/storage/..%2f..%2fapi/internal/x.png"],
    ["encoded backslash", "https://alisadikinma.com/storage/a%5c..%5cb.png"],
    ["encoded dot-dot", "https://alisadikinma.com/storage/%2e%2e/api/x.png"],
    ["upper-case escape", "https://alisadikinma.com/uploads/a%2Fb.png"],
  ])("does not rewrite to loopback when the path has %s", async (_n, src) => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP);
    await addImageDimensions(img(src));
    expect(fetchedUrls().every((u) => !u.startsWith(LOOP))).toBe(true);
  });

  it("caches by the public URL, not the loopback URL", async () => {
    vi.stubEnv("IMAGE_DIMS_ORIGIN", LOOP);
    await addImageDimensions(img(URL1));
    await addImageDimensions(img(URL1));
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
