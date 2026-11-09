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

  it("bar flags sit outside the nav; on phones a labelled copy lives inside the menu (Ali 2026-11-09)", async () => {
    const html = await render("id", "/id");
    const nav = html.match(/<nav[\s\S]*?<\/nav>/)?.[0] ?? "";
    expect(nav).not.toContain('class="lang-sw"');
    expect(html).toContain('class="lang-sw"');
    const menu = nav.match(/<div class="lang-menu"[\s\S]*?<\/div>/)?.[0] ?? "";
    expect(menu).toContain(">English</span>");
    expect(menu).toMatch(/<a class="lang flag" href="\/en"/);
    expect(menu).toMatch(/is-on[^>]*>[\s\S]*?>Bahasa Indonesia<\/span>/);
  });

  it("missing translation: only the current flag, no link", async () => {
    const sw = switcher(await render("id", "/id/blog/x", ["id"]));
    expect(sw).toContain("is-on");
    expect(sw).not.toContain("<a ");
  });
});

// Ali 2026-11-09: on the AI Speaker page the "AI Speaker" nav item was not highlighted.
describe("current page in the nav", () => {
  const nav = async (lang: "id" | "en", path: string) => (await render(lang, path)).match(/<nav[\s\S]*?<\/nav>/)?.[0] ?? "";
  const current = (html: string) => [...html.matchAll(/<a [^>]*aria-current="page"[^>]*>([^<]*)<\/a>/g)].map((m) => m[1]);

  it.each([
    ["id", "/id/pembicara-ai", "Pembicara AI"],
    ["en", "/en/ai-speaker", "AI Speaker"],
    ["id", "/id/pelatihan-ai", "Pelatihan AI"],
    ["en", "/en/ai-training", "AI Training"],
    ["id", "/id/blog/judul-artikel", "Blog"],
    ["en", "/en/blog", "Blog"],
  ] as const)("%s %s marks %s", async (lang, path, label) => {
    expect(current(await nav(lang, path))).toEqual([label]);
  });

  it("home and other pages mark nothing as the current page", async () => {
    expect(current(await nav("id", "/id"))).toEqual([]);
    expect(current(await nav("en", "/en/faq"))).toEqual([]);
  });
});

// Ali 2026-11-09: on phones the menu disappeared; it must collapse behind a hamburger button.
describe("mobile menu", () => {
  it.each(["id", "en"] as const)("a labelled hamburger button opens the nav as a popover (%s)", async (lang) => {
    const html = await render(lang, `/${lang}`);
    const btn = html.match(/<button[^>]*class="nav-toggle"[^>]*>/)?.[0] ?? "";
    expect(btn).toContain('popovertarget="site-nav"');
    expect(btn).toContain('type="button"');
    expect(btn).toMatch(/aria-label="(Menu|Buka menu)"/);
    expect(html).toMatch(/<nav[^>]*id="site-nav"[^>]*popover/);
  });

  it("every nav link is still in the nav (one source for desktop and mobile)", async () => {
    const nav = (await render("id", "/id")).match(/<nav[\s\S]*?<\/nav>/)?.[0] ?? "";
    expect((nav.match(/<a (?!class="lang)/g) ?? []).length).toBe(6);
  });
});
