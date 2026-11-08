import { apiGetText } from "./api";
import { AI_BOTS } from "./aiBots";
import { getConfig } from "./config";

/** AI crawlers explicitly welcomed: the ai_bots map plus the two opt-out tokens crawlers read. */
export const ROBOTS_ALLOWED_BOTS: readonly string[] = [...new Set([...Object.keys(AI_BOTS), "Google-Extended", "Applebot-Extended"])];

export const CHILD_SITEMAPS = [
  { file: "/sitemap-pages.xml", api: "/sitemap.xml" },
  { file: "/sitemap-posts.xml", api: "/sitemap-posts.xml" },
  { file: "/sitemap-projects.xml", api: "/sitemap-projects.xml" },
] as const;

const site = (): string => getConfig().siteUrl.replace(/\/+$/, "");

export function robotsTxt(): string {
  const lines = ["# robots.txt for alisadikinma.com", "", "User-agent: *", "Allow: /", "Disallow: /admin/", ""];
  lines.push("# AI crawlers: welcome");
  for (const bot of ROBOTS_ALLOWED_BOTS) lines.push(`User-agent: ${bot}`, "Allow: /", "Disallow: /admin/", "");
  lines.push("# LLM-readable profile: /llms.txt (concise), /api/llms-full.txt (full)", "", `Sitemap: ${site()}/sitemap.xml`, "");
  return lines.join("\n");
}

export function sitemapIndexXml(): string {
  const items = CHILD_SITEMAPS.map((s) => `  <sitemap>\n    <loc>${site()}${s.file}</loc>\n  </sitemap>`).join("\n");
  return `<?xml version="1.0" encoding="UTF-8"?>\n<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${items}\n</sitemapindex>\n`;
}

const XML_HEADERS = { "Content-Type": "application/xml; charset=utf-8", "Cache-Control": "public, max-age=3600" };

/** Proxy one Laravel sitemap. API down/404 = 502; an empty sitemap is never invented. */
export async function proxySitemap(apiPath: string): Promise<Response> {
  let body: string | null = null;
  try {
    body = await apiGetText(apiPath);
  } catch {
    body = null;
  }
  if (!body || !body.includes("<urlset")) return new Response("Sitemap temporarily unavailable", { status: 502, headers: { "Cache-Control": "no-store" } });
  return new Response(body, { status: 200, headers: XML_HEADERS });
}

export const xmlResponse = (body: string): Response => new Response(body, { status: 200, headers: XML_HEADERS });
