import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { resetImageDimsCache } from "../lib/image-dims";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { basicSpec } from "../../scripts/seo-gate.config.mjs";
import ProjectDetail from "../pages/[lang]/projects/[slug].astro";

const PROJECT: Record<string, unknown> = {
  id: 7,
  slug: "fleet-vision",
  title: "Fleet Vision <b>",
  description:
    "Fleet Vision <b> is an AI platform that tracks trucks, fuel and drivers in real time for a mining logistics operator, cutting idle time and fuel loss across the whole fleet.",
  content: "<p>Case study body.</p><script>window.__pwned = 1</script><h2>Result</h2><p>Idle time down.</p>",
  category: "Fleet",
  featured_image: "https://alisadikinma.com/storage/fleet.jpg",
  images: ["https://alisadikinma.com/storage/fleet-2.jpg"],
  technologies: ["Python", "Laravel"],
  meta_title: null,
  meta_description: null,
  og_image: "https://alisadikinma.com/storage/fleet-og.jpg",
  created_at: "2026-01-01T00:00:00.000000Z",
  updated_at: "2026-02-01T00:00:00.000000Z",
  related_projects: [
    { id: 8, title: "Vision QC", slug: "vision-qc", featured_image: null, description: "Visual quality control." },
    { id: 9, title: "Cashier Hub", slug: "cashier-hub", featured_image: "https://alisadikinma.com/storage/c.jpg", description: "Multi-branch POS." },
  ],
};

let handler: (url: string) => Response;
const calls: string[] = [];

const PNG = (() => {
  const b = new Uint8Array(33);
  b.set([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a], 0);
  const v = new DataView(b.buffer);
  v.setUint32(8, 13);
  b.set([0x49, 0x48, 0x44, 0x52], 12);
  v.setUint32(16, 640);
  v.setUint32(20, 360);
  return b;
})();
const withProject = (over: Record<string, unknown>) => {
  handler = () => new Response(JSON.stringify({ success: true, data: { ...PROJECT, ...over } }), { status: 200 });
};
const titleOf = (html: string) => /<title>([^<]*)<\/title>/.exec(html)![1];
const descOf = (html: string) => /<meta name="description" content="([^"]*)"/.exec(html)![1];

beforeEach(() => {
  resetApiCache();
  resetImageDimsCache();
  calls.length = 0;
  handler = () => new Response(JSON.stringify({ success: true, data: PROJECT }), { status: 200 });
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      calls.push(String(url));
      if (String(url).includes("/storage/blog-images/")) return new Response(PNG, { status: 206 });
      return handler(String(url));
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, slug = "fleet-vision") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(ProjectDetail, {
    params: { lang, slug },
    request: new Request(`https://alisadikinma.com/${lang}/projects/${slug}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

const specFor = (lang: "id" | "en", slug = "fleet-vision") =>
  basicSpec(
    lang,
    `/${lang}/projects/${slug}`,
    { id: `/id/projects/${slug}`, en: `/en/projects/${slug}` },
    ["Fleet Vision"],
    "CreativeWork",
  );

describe("project <title> (blog rule: meta_title as-is)", () => {
  it("uses meta_title as-is when set, without forcing the name", async () => {
    PROJECT.meta_title = "SMT Store Room Using IoT for Auto Humidity Control and Monitoring";
    const { html } = await load("en");
    PROJECT.meta_title = null;
    expect(titleOf(html)).toBe("SMT Store Room Using IoT for Auto Humidity Control and…");
  });
  it("without meta_title: title + name when it fits", async () => {
    const { html } = await load("en");
    expect(titleOf(html)).toContain("Ali Sadikin Ma");
  });
});

describe("/{lang}/projects/{slug}", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    expect(checkHtml(html, specFor(lang), headers)).toEqual([]);
  });

  it("requests /projects/{slug}?lang=", async () => {
    await load("id");
    expect(calls[0]).toContain("/projects/fleet-vision");
    expect(calls[0]).toContain("lang=id");
  });

  it("H1 is the project title (escaped)", async () => {
    const { html } = await load("en");
    expect(html).toMatch(/<h1[^>]*>\s*Fleet Vision &lt;b&gt;\s*<\/h1>/);
  });

  it("emits CreativeWork with creator -> #person, plus BreadcrumbList Home > Projects > Project", async () => {
    const { html } = await load("en");
    const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map((m) => JSON.parse(m[1]));
    const nodes = blocks.flatMap((b) => b["@graph"] ?? [b]);
    const cw = nodes.find((n: any) => n["@type"] === "CreativeWork");
    expect(cw.creator).toEqual({ "@id": "https://alisadikinma.com/#person" });
    expect(cw.url).toBe("https://alisadikinma.com/en/projects/fleet-vision");
    const bc = nodes.find((n: any) => n["@type"] === "BreadcrumbList");
    expect(bc.itemListElement.map((i: any) => i.item)).toEqual([
      "https://alisadikinma.com/en",
      "https://alisadikinma.com/en/projects",
      "https://alisadikinma.com/en/projects/fleet-vision",
    ]);
  });

  it("renders related projects as localized cards", async () => {
    const { html } = await load("id");
    expect(html).toContain('href="/id/projects/vision-qc"');
    expect(html).toContain('href="/id/projects/cashier-hub"');
  });

  it("sanitizes HTML content: script does not reach output, safe markup stays", async () => {
    const { html } = await load("en");
    expect(html).not.toContain("__pwned");
    expect(html).toContain("Case study body.");
    expect(html).toContain("<h2>Result</h2>");
  });

  it("unknown slug (API 404) -> 404", async () => {
    handler = () => new Response(JSON.stringify({ success: false }), { status: 404 });
    expect((await load("id", "nope")).status).toBe(404);
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });

  it("API down -> 503 plain message", async () => {
    handler = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Proyek ini sedang tidak bisa dimuat");
  });
});

describe("project detail: SEO fixes", () => {
  it("title is '{title} — Ali Sadikin Ma' when it fits in 60 chars", async () => {
    withProject({ title: "Asset Audit" });
    expect(titleOf((await load("id")).html)).toBe("Asset Audit — Ali Sadikin Ma");
  });

  it("long title: clipped title alone, single ellipsis, name not appended", async () => {
    withProject({ title: "Platform audit aset pabrik berbasis computer vision untuk industri manufaktur Batam" });
    const t = titleOf((await load("id")).html);
    expect(t.length).toBeLessThanOrEqual(60);
    expect(t.endsWith("…")).toBe(true);
    expect(t).not.toContain("Ali Sadikin Ma");
    expect(t).not.toContain("...");
  });

  it("short description yields to the content first paragraph, clipped to 160", async () => {
    withProject({
      description: "Audit aset pabrik.",
      meta_description: null,
      content: `<p>${"Sistem ini memeriksa kondisi aset secara otomatis dari foto lapangan. ".repeat(4)}</p>`,
    });
    const d = descOf((await load("id")).html);
    expect(d.startsWith("Sistem ini memeriksa")).toBe(true);
    expect(d.length).toBeGreaterThanOrEqual(120);
    expect(d.length).toBeLessThanOrEqual(160);
  });

  it("short description without content stays as is (never invented)", async () => {
    withProject({ description: "Audit aset pabrik.", meta_description: null, content: "" });
    expect(descOf((await load("id")).html)).toBe("Audit aset pabrik.");
  });

  it.each(["id", "en"] as const)("no related projects: still >= 2 body links and passes the gate (%s)", async (lang) => {
    withProject({ related_projects: [] });
    const { html, headers } = await load(lang);
    expect(html).toContain(`<a href="/${lang}/projects"`);
    expect(html).toContain(`href="/${lang}/about"`);
    expect(checkHtml(html, specFor(lang), headers)).toEqual([]);
  });

  it.each([".", "..", "a/b"])("slug %j -> 404 without calling the API", async (slug) => {
    expect((await load("id", slug)).status).toBe(404);
    expect(calls).toHaveLength(0);
  });

  it("503 carries Retry-After: 120", async () => {
    handler = () => new Response("boom", { status: 500 });
    const { status, headers } = await load("id");
    expect(status).toBe(503);
    expect(headers.get("retry-after")).toBe("120");
  });

  it("inline CMS images get width/height injected and ids are namespaced", async () => {
    withProject({
      content: '<h2 id="layanan">x</h2><p>t</p><img src="https://alisadikinma.com/storage/blog-images/a.jpg" alt="Diagram">',
    });
    const { html } = await load("id");
    expect(html).toMatch(/<img [^>]*blog-images\/a\.jpg"[^>]*width="640"[^>]*height="360"/);
    expect(html).toContain('id="uc-layanan"');
  });
});
