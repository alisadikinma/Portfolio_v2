import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import NotFound from "../pages/404.astro";

// SEO audit 2026-11-09: unknown URLs showed Astro's bare "404: Not Found" (no viewport, no nav).
beforeEach(() => {
  resetApiCache();
  vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: true, data: {} }), { status: 200 })));
});
afterEach(() => vi.unstubAllGlobals());

async function load(path: string) {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(NotFound, { request: new Request(`https://alisadikinma.com${path}`) });
  return { status: res.status, html: await res.text() };
}

describe("404 page", () => {
  it("is a real 404 with the site layout, noindex and a viewport", async () => {
    const { status, html } = await load("/id/tidak-ada");
    expect(status).toBe(404);
    expect(html).toContain('<meta name="robots" content="noindex, follow"');
    expect(html).toContain('name="viewport"');
    expect(html).toContain('<html lang="id"');
    expect(html.match(/<h1[ >]/g)).toHaveLength(1);
    expect(html).toContain('href="/id"');
  });
  it("English under /en and for unprefixed paths", async () => {
    expect((await load("/en/nope")).html).toContain('<html lang="en"');
    expect((await load("/nope")).html).toContain('<html lang="en"');
  });
});
