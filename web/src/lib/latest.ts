import { apiGet, type Lang } from "./api";
import { excerptAtSentence } from "./excerpt";
import { imageOrigin, largestUrl, srcsetOf, variantList } from "./post-images";

/** Raw article as returned in /homepage/featured `latest_articles`. */
interface RawArticle {
  slug?: string | null;
  title?: string | null;
  published_at?: string | null;
  featured_image?: string | null;
  image_variants?: Record<string, string> | null;
  reading_time?: number | null;
  excerpt?: string | null;
  content?: string | null;
  category?: { name?: string | null } | null;
}

export interface LatestArticle {
  href: string;
  title: string;
  /** ISO date or "" when the date is missing/invalid. */
  iso: string;
  dateLabel: string;
  category: string;
  /** Reading time in minutes (API value, else from the body), 0 = unknown. */
  minutes: number;
  excerpt: string;
  img: { src: string; srcset?: string } | null;
}

export const WORDS_PER_MINUTE = 200;
export const EXCERPT_MAX = 170;

function excerptOf(a: RawArticle): string {
  const src = a.excerpt?.trim() || plainText(a.content);
  return src ? excerptAtSentence(src, EXCERPT_MAX) : "";
}

/** Minutes to read an HTML body; 0 when there is no text. */
export function readingMinutes(html: string | null | undefined): number {
  const text = (html ?? "").replace(/<[^>]+>/g, " ").replace(/&[a-z#0-9]+;/gi, " ");
  const n = text.split(/\s+/).filter(Boolean).length;
  return n === 0 ? 0 : Math.max(1, Math.ceil(n / WORDS_PER_MINUTE));
}

const ENTITIES: Record<string, string> = { amp: "&", lt: "<", gt: ">", quot: '"', "#39": "'", apos: "'", nbsp: " ", rsquo: "’", lsquo: "‘", rdquo: "”", ldquo: "“", mdash: "—", ndash: "–", hellip: "…" };

/** Plain text of an HTML body without headings, entities decoded (for an excerpt fallback). */
export function plainText(html: string | null | undefined): string {
  return (html ?? "")
    .replace(/<(h[1-6]|figure|figcaption|script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, " ")
    .replace(/<[^>]+>/g, " ")
    .replace(/&([a-z]+|#\d+);/gi, (m, k: string) => ENTITIES[k.toLowerCase()] ?? (k.startsWith("#") ? String.fromCharCode(Number(k.slice(1))) : m))
    .replace(/\s+/g, " ")
    .trim();
}

/**
 * Latest published posts from /homepage/featured, mapped for cards. Never throws: [] on any failure.
 * The API client caches + de-duplicates, so several sections on one page share a single request.
 */
export async function loadLatestArticles(lang: Lang, limit = 3): Promise<LatestArticle[]> {
  let raw: RawArticle[] = [];
  try {
    const data = await apiGet<{ latest_articles?: RawArticle[] }>("/homepage/featured", { lang });
    if (data && Array.isArray(data.latest_articles)) raw = data.latest_articles;
  } catch (e) {
    console.warn("[home] latest articles unavailable", (e as Error).message);
    return [];
  }
  const fmt = new Intl.DateTimeFormat(lang === "id" ? "id-ID" : "en-US", { dateStyle: "medium", timeZone: "UTC" });
  return raw
    .filter((a) => a && a.slug && a.title)
    .slice(0, limit)
    .map((a) => {
      const d = a.published_at ? new Date(a.published_at) : null;
      const ok = d !== null && !Number.isNaN(d.getTime());
      const variants = variantList(a.image_variants, imageOrigin(a.featured_image));
      const src = a.featured_image || largestUrl(variants) || "";
      const minutes = typeof a.reading_time === "number" && a.reading_time > 0 ? Math.round(a.reading_time) : readingMinutes(a.content);
      return {
        href: `/${lang}/blog/${encodeURIComponent(a.slug as string)}`,
        title: a.title as string,
        iso: ok ? (d as Date).toISOString() : "",
        dateLabel: ok ? fmt.format(d as Date) : "",
        category: a.category?.name?.trim() ?? "",
        minutes,
        excerpt: excerptOf(a),
        img: src ? { src, srcset: srcsetOf(variants) } : null,
      };
    });
}
