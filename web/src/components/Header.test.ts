import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import Header from "./Header.astro";

beforeEach(() => {
  resetApiCache();
  vi.stubGlobal("fetch", vi.fn(async () => new Response("{}", { status: 404 })));
});
afterEach(() => vi.unstubAllGlobals());

async function render(lang: "id" | "en", path: string, availableLangs?: ("id" | "en")[]) {
  const c = await AstroContainer.create();
  return c.renderToString(Header, { props: { lang, path, availableLangs } });
}

const switcher = (html: string) => html.match(/<div class="lang-sw"[\s\S]*?<\/div>/)?.[0] ?? "";

// Ali 2026-11-08: flags like the live site (US + ID, current one highlighted), not "EN"/"ID" text.
describe("language switcher", () => {
  it("shows both flags; the current language is marked, the other links to the same page", async () => {
    const sw = switcher(await render("id", "/id/pelatihan-ai"));
    expect(sw).not.toBe("");
    expect((sw.match(/<svg/g) ?? []).length).toBe(2);
    expect(sw).toMatch(/<span class="flag is-on"[^>]*aria-current="true"[^>]*title="Bahasa Indonesia"/);
    expect(sw).toMatch(/<a class="lang flag" href="\/en\/ai-training" hreflang="en"[^>]*title="English"/);
    expect(sw).not.toMatch(/>\s*(EN|ID)\s*</);
  });

  it("works the other way round on an English page", async () => {
    const sw = switcher(await render("en", "/en/ai-speaker"));
    expect(sw).toMatch(/<span class="flag is-on"[^>]*title="English"/);
    expect(sw).toMatch(/<a class="lang flag" href="\/id\/pembicara-ai" hreflang="id"/);
  });

  it("sits outside the nav, so it stays visible when the nav collapses on small screens", async () => {
    const html = await render("id", "/id");
    const nav = html.match(/<nav[\s\S]*?<\/nav>/)?.[0] ?? "";
    expect(nav).not.toContain("lang-sw");
    expect(html).toContain('class="lang-sw"');
  });

  it("missing translation: only the current flag, no link", async () => {
    const sw = switcher(await render("id", "/id/blog/x", ["id"]));
    expect(sw).toContain("is-on");
    expect(sw).not.toContain("<a ");
  });
});
