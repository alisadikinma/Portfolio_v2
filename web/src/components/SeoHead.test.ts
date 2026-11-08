import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { resetApiCache } from "../lib/api";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import SeoHead from "./SeoHead.astro";
import BaseLayout from "../layouts/BaseLayout.astro";
import { graph, personNode, PORTRAIT_URL } from "../lib/schema";

const props = {
  title: "FAQ Ali Sadikin Ma — Pertanyaan Umum",
  description: "Jawaban singkat tentang Ali Sadikin Ma.",
  path: "/id/faq",
  lang: "id" as const,
  jsonld: graph(personNode()),
};

async function head(p: Record<string, unknown> = props) {
  const c = await AstroContainer.create();
  return c.renderToString(SeoHead, { props: p });
}

describe("SeoHead", () => {
  it("renders title, description, canonical", async () => {
    const h = await head();
    expect(h).toContain("<title>FAQ Ali Sadikin Ma — Pertanyaan Umum</title>");
    expect(h).toContain('name="description" content="Jawaban singkat tentang Ali Sadikin Ma."');
    expect(h).toContain('rel="canonical" href="https://alisadikinma.com/id/faq"');
  });

  it("renders absolute https hreflang id/en/x-default (x-default = en)", async () => {
    const h = await head();
    expect(h).toContain('hreflang="id" href="https://alisadikinma.com/id/faq"');
    expect(h).toContain('hreflang="en" href="https://alisadikinma.com/en/faq"');
    expect(h).toContain('hreflang="x-default" href="https://alisadikinma.com/en/faq"');
  });

  it("maps translated segments in hreflang", async () => {
    const h = await head({ ...props, path: "/id/pelatihan-ai" });
    expect(h).toContain('hreflang="en" href="https://alisadikinma.com/en/ai-training"');
  });

  it("renders OG + Twitter with portrait default image", async () => {
    const h = await head();
    expect(h).toContain('property="og:url" content="https://alisadikinma.com/id/faq"');
    expect(h).toContain('property="og:title"');
    expect(h).toContain('property="og:description"');
    expect(h).toContain(`property="og:image" content="${PORTRAIT_URL}"`);
    expect(h).toContain('property="og:locale" content="id_ID"');
    expect(h).toContain('name="twitter:card" content="summary_large_image"');
    expect(h).toContain(`name="twitter:image" content="${PORTRAIT_URL}"`);
  });

  it("renders exactly one JSON-LD script, escaped", async () => {
    const h = await head({ ...props, jsonld: graph({ "@type": "Thing", name: "</script><b>x" }) });
    expect(h.match(/application\/ld\+json/g)).toHaveLength(1);
    expect(h).not.toContain("</script><b>");
    expect(h).toContain("\\u003c/script\\u003e");
  });

  it("canonical equals self hreflang, even for /id/ input", async () => {
    const h = await head({ ...props, path: "/id/" });
    expect(h).toContain('rel="canonical" href="https://alisadikinma.com/id"');
    expect(h).toContain('hreflang="id" href="https://alisadikinma.com/id"');
    expect(h).toContain('property="og:url" content="https://alisadikinma.com/id"');
  });

  it("og:type defaults to website, article when asked", async () => {
    expect(await head()).toContain('property="og:type" content="website"');
    expect(await head({ ...props, type: "article" })).toContain('property="og:type" content="article"');
  });

  it("robots index,follow", async () => {
    expect(await head()).toContain('name="robots" content="index,follow"');
  });
});

describe("BaseLayout", () => {
  let settings: Record<string, unknown> = {};
  beforeEach(() => {
    resetApiCache();
    settings = {};
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(JSON.stringify({ success: true, data: settings }), { status: 200 })),
    );
  });
  afterEach(() => vi.unstubAllGlobals());

  it("WhatsApp button uses site.contact_phone digits", async () => {
    settings = { contact_phone: "+62 812-3456-7890" };
    const h = await render();
    expect(h).toContain('href="https://wa.me/6281234567890"');
  });

  it("WhatsApp button hidden when contact_phone empty or API fails", async () => {
    settings = { contact_phone: "" };
    expect(await render()).not.toContain("wa.me");
    expect(await render()).not.toContain('href="#wa"');
    resetApiCache();
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await render()).not.toContain("wa.me");
  });

  it("brand/home link has no trailing slash", async () => {
    expect(await render()).toContain('class="brand" href="/id"');
  });

  it("no social link falls back to #", async () => {
    const h = await render();
    expect(h).not.toContain('href="#"');
  });

  const render = async (p: Record<string, unknown> = {}) => {
    const c = await AstroContainer.create();
    return c.renderToString(BaseLayout, {
      props: { ...props, ...p },
      slots: { default: "<main>body</main>" },
    });
  };

  it("sets html lang and renders slot", async () => {
    const h = await render();
    expect(h).toContain('<html lang="id"');
    expect(h).toContain("<main>body</main>");
  });

  it("empty GA4 id emits no googletagmanager", async () => {
    expect(await render({ ga4Id: "" })).not.toContain("googletagmanager");
  });

  it("GA4 id emits gtag", async () => {
    expect(await render({ ga4Id: "G-ABC123" })).toContain("googletagmanager.com/gtag/js?id=G-ABC123");
  });

  it("footer has dofollow Founder INDUSIA.ai link (ID)", async () => {
    const h = await render();
    expect(h).toMatch(/<a href="https:\/\/indusia\.ai\/id\/" target="_blank">Founder INDUSIA\.ai<\/a>/);
  });

  // Ali 2026-11-09: social links are icons only and every external footer link opens a new tab.
  it("footer social links are icon-only, labelled, and open in a new tab; FAQ stays in the tab", async () => {
    const h = await render();
    const nav = h.match(/<footer[\s\S]*?<\/footer>/)![0];
    const socs = [...nav.matchAll(/<a class="soc"([^>]*)>([\s\S]*?)<\/a>/g)];
    expect(socs.length).toBeGreaterThanOrEqual(4);
    for (const [, attrs, inner] of socs) {
      expect(attrs).toContain('target="_blank"');
      expect(attrs).toMatch(/aria-label="[^"]+"/);
      expect(inner.replace(/<[^>]+>/g, "").trim()).toBe("");
      expect(inner).toContain("<svg");
    }
    expect(nav).toMatch(/<a href="\/id\/faq">/);
    // Ali 2026-11-09: GitHub joins the footer icons.
    expect(nav).toMatch(/<a class="soc"[^>]*href="https:\/\/github\.com\/alisadikinma"/);
  });

  it("EN footer points to unprefixed indusia.ai", async () => {
    const h = await render({ lang: "en", path: "/en/faq" });
    expect(h).toContain('href="https://indusia.ai/"');
    expect(h).not.toContain('href="https://indusia.ai/id/"');
  });

  it("preloads hero image when given", async () => {
    expect(await render({ preloadImage: "/images/hero.webp" })).toContain('rel="preload" as="image" href="/images/hero.webp"');
  });
});

describe("SeoHead noindex", () => {
  it("renders noindex, follow when noindex is set", async () => {
    const h = await head({ ...props, noindex: true });
    expect(h).toContain('<meta name="robots" content="noindex, follow"');
    expect(h).not.toContain("index,follow");
  });
  it("defaults to index,follow", async () => {
    expect(await head()).toContain('<meta name="robots" content="index,follow"');
  });
});

describe("availableLangs", () => {
  const blogProps = { ...props, path: "/id/blog/x", title: "Post" };

  it("only id exists: self alternate + x-default to id, no en alternate", async () => {
    const h = await head({ ...blogProps, availableLangs: ["id"] });
    expect(h).toContain('hreflang="id" href="https://alisadikinma.com/id/blog/x"');
    expect(h).toContain('hreflang="x-default" href="https://alisadikinma.com/id/blog/x"');
    expect(h).not.toContain('hreflang="en"');
    expect(h).toContain('rel="canonical" href="https://alisadikinma.com/id/blog/x"');
  });

  it("only en exists: x-default to en, no id alternate", async () => {
    const h = await head({ ...blogProps, path: "/en/blog/x", lang: "en", availableLangs: ["en"] });
    expect(h).toContain('hreflang="en" href="https://alisadikinma.com/en/blog/x"');
    expect(h).toContain('hreflang="x-default" href="https://alisadikinma.com/en/blog/x"');
    expect(h).not.toContain('hreflang="id"');
  });

  it("both exist (and default) keep all three", async () => {
    for (const a of [["id", "en"], undefined]) {
      const h = await head({ ...blogProps, availableLangs: a });
      expect(h).toContain('hreflang="id"');
      expect(h).toContain('hreflang="en" href="https://alisadikinma.com/en/blog/x"');
      expect(h).toContain('hreflang="x-default" href="https://alisadikinma.com/en/blog/x"');
    }
  });
});

describe("Header language switch with availableLangs", () => {
  beforeEach(() => {
    resetApiCache();
    vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: true, data: {} }), { status: 200 })));
  });
  afterEach(() => vi.unstubAllGlobals());
  const render = async (p: Record<string, unknown>) => {
    const c = await AstroContainer.create();
    return c.renderToString(BaseLayout, { props: { ...props, path: "/id/blog/x", ...p }, slots: { default: "<main>b</main>" } });
  };

  it("shows the switch link by default", async () => {
    expect(await render({})).toMatch(/<a class="lang flag" href="\/en\/blog\/x"/);
  });
  it("hides the switch link to a missing language", async () => {
    const h = await render({ availableLangs: ["id"] });
    expect(h).not.toContain('class="lang flag"');
    expect(h).not.toContain('href="/en/blog/x"');
  });
  it("keeps it when the other language exists", async () => {
    expect(await render({ availableLangs: ["id", "en"] })).toContain('class="lang flag"');
  });
});

// Ali 2026-11-09: favicon = the clay Ali head; every icon file it points to exists.
describe("favicon", () => {
  it("links favicon.ico, a 32px png, the apple touch icon and the manifest", async () => {
    const h = await head();
    expect(h).toContain('<link rel="icon" href="/favicon.ico" sizes="48x48">');
    expect(h).toContain('<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">');
    expect(h).toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png">');
    expect(h).toContain('<link rel="manifest" href="/site.webmanifest">');
    const { existsSync } = await import("node:fs");
    for (const f of ["favicon.ico", "favicon-32.png", "apple-touch-icon.png", "site.webmanifest", "icon-192.png", "icon-512.png"]) {
      expect(existsSync(new URL(`../../public/${f}`, import.meta.url)), f).toBe(true);
    }
  });
});
