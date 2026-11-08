import { describe, it, expect } from "vitest";
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

  it("robots index,follow", async () => {
    expect(await head()).toContain('name="robots" content="index,follow"');
  });
});

describe("BaseLayout", () => {
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
    expect(h).toMatch(/<a href="https:\/\/indusia\.ai\/id\/">Founder INDUSIA\.ai<\/a>/);
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
