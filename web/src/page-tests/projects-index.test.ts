import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { resetApiCache } from "../lib/api";
import { checkHtml } from "../../scripts/seo-gate.mjs";
import { ROUTES } from "../../scripts/seo-gate.config.mjs";
import ProjectsIndex from "../pages/[lang]/projects/index.astro";

const P = (n: number, category: string, extra: Record<string, unknown> = {}) => ({
  id: n,
  slug: `proj-${n}`,
  title: `Project ${n} <b>`,
  description: `Description ${n}`,
  category,
  featured_image: n === 1 ? "https://alisadikinma.com/storage/p1.jpg" : null,
  technologies: ["Python"],
  ...extra,
});
const PROJECTS = [P(1, "AI Vision"), P(2, "AI Vision"), P(3, "Fleet")];

let handler: (url: string) => Response;
const calls: string[] = [];

beforeEach(() => {
  resetApiCache();
  calls.length = 0;
  handler = () =>
    new Response(
      JSON.stringify({ success: true, data: PROJECTS, meta: { current_page: 1, last_page: 1, per_page: 50, total: 3 } }),
      { status: 200 },
    );
  vi.stubGlobal(
    "fetch",
    vi.fn(async (url: string) => {
      calls.push(String(url));
      return handler(String(url));
    }),
  );
});
afterEach(() => vi.unstubAllGlobals());

async function load(lang: string, query = "") {
  const c = await AstroContainer.create();
  const res = await c.renderToResponse(ProjectsIndex, {
    params: { lang },
    request: new Request(`https://alisadikinma.com/${lang}/projects${query}`),
  });
  return { status: res.status, html: await res.text(), headers: res.headers };
}

const spec = (lang: "id" | "en") =>
  (ROUTES as unknown as { projects: { lang: string }[] }).projects.find((s) => s.lang === lang);

describe("/{lang}/projects", () => {
  it.each(["id", "en"] as const)("passes the SEO gate (%s)", async (lang) => {
    const { status, html, headers } = await load(lang);
    expect(status).toBe(200);
    expect(checkHtml(html, spec(lang), headers)).toEqual([]);
  });

  it("renders every project as a localized card link (escaped) and requests /projects?lang=", async () => {
    const { html } = await load("id");
    expect(calls[0]).toContain("/projects?");
    expect(calls[0]).toContain("lang=id");
    expect(html).toContain('href="/id/projects/proj-1"');
    expect(html).toContain('href="/id/projects/proj-3"');
    expect(html).toContain("Project 1 &lt;b&gt;");
    expect(html).not.toContain("<h2>Project 1 <b>");
  });

  it("emits CollectionPage + BreadcrumbList + Person JSON-LD", async () => {
    const { html } = await load("en");
    expect(html).toContain("CollectionPage");
    expect(html).toContain('"@type":"BreadcrumbList"');
    expect(html).toContain("https://alisadikinma.com/#person");
  });

  it("category filter via query shows only matches, with crawlable filter links and unfiltered canonical", async () => {
    const { status, html } = await load("id", "?category=Fleet");
    expect(status).toBe(200);
    expect(html).toContain('href="/id/projects/proj-3"');
    expect(html).not.toContain('href="/id/projects/proj-1"');
    expect(html).toContain('href="/id/projects?category=AI%20Vision"');
    expect(html).toContain('<link rel="canonical" href="https://alisadikinma.com/id/projects"');
  });

  it("filter with no match -> 200 empty state, not an error", async () => {
    const { status, html } = await load("en", "?category=Nope");
    expect(status).toBe(200);
    expect(html).toContain("No projects in this category");
    expect(html).not.toContain('href="/en/projects/proj-');
  });

  it("empty API result -> 200 empty state", async () => {
    handler = () => new Response(JSON.stringify({ success: true, data: [], meta: { last_page: 1 } }), { status: 200 });
    const { status, html } = await load("id");
    expect(status).toBe(200);
    expect(html).toContain("Belum ada proyek");
  });

  it("API down -> 503 plain message", async () => {
    handler = () => new Response("boom", { status: 500 });
    const { status, html } = await load("id");
    expect(status).toBe(503);
    expect(html).toContain("Daftar proyek sedang tidak bisa dimuat");
  });

  it("unknown lang -> 404", async () => {
    expect((await load("fr")).status).toBe(404);
  });

  it("follows pagination (last_page 2 fetches page 2)", async () => {
    handler = (url) =>
      new Response(
        JSON.stringify({
          success: true,
          data: url.includes("page=2") ? [P(9, "Fleet")] : PROJECTS,
          meta: { last_page: 2 },
        }),
        { status: 200 },
      );
    const { html } = await load("id");
    expect(html).toContain('href="/id/projects/proj-9"');
  });
});

// SEO audit 2026-11-09: 56 cards pulled 512px PNGs (450-690KB each). Phones (96px thumbs) get the 320w WebP.
describe("project card images", () => {
  it("uses the 320w WebP variant in srcset with phone-sized sizes", async () => {
    (PROJECTS[0] as Record<string, unknown>).image_variants = { "320w": "/storage/p1-320w.webp", lqip: "data:image/jpeg;base64,AA" };
    const html = (await load("id")).html;
    expect(html).toContain('srcset="https://alisadikinma.com/storage/p1-320w.webp 320w, https://alisadikinma.com/storage/p1.jpg 512w"');
    expect(html).toContain('sizes="(max-width: 760px) 96px, 400px"');
    delete (PROJECTS[0] as Record<string, unknown>).image_variants;
  });
});
