import { describe, it, expect } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import PostCard from "./PostCard.astro";

// Ali 2026-11-09: every image carries alt text (Bing flagged empty alt on blog thumbnails).
describe("PostCard thumbnail", () => {
  it("uses the post title as alt text", async () => {
    const c = await AstroContainer.create();
    const html = await c.renderToString(PostCard, {
      props: {
        lang: "id",
        minRead: "menit baca",
        post: { slug: "a", title: 'AI "Agent" & Kamu', featured_image: "https://alisadikinma.com/storage/blog-images/a.jpg" },
      },
    });
    const img = html.match(/<img [^>]*>/)?.[0] ?? "";
    expect(img).toContain('alt="AI &quot;Agent&quot; &amp; Kamu"');
    expect(img).not.toContain('aria-hidden="true"');
  });
});
