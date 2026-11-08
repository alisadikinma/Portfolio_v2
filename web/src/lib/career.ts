import { apiGet, apiPath, type Lang } from "./api";
import { getConfig } from "./config";
import { localizedPath } from "./i18n";
import { SITE } from "./schema";
import { largestUrl, srcsetOf, urlAtWidth, variantList } from "./post-images";

/** Galleries behind the career journey (vault facts). */
export const GALLERY_SINGAPORE = 14;
export const GALLERIES_MARLIN = [9, 11, 13, 10, 12] as const;
export const GALLERY_SATNUSA = 16;
/** Awards on the stages bento. Insertion order = display order (first = main card). */
export const STAGE_AWARDS = { demoDay: 13, unctad: 10, grind: 11, nextdev: 7, fenox: 8 } as const;
export const STAGE_AWARD_IDS = Object.values(STAGE_AWARDS);

/** Width of the variant used inside the lightbox (else the largest one, else the original). */
export const LIGHTBOX_WIDTH = 1920;

export interface Photo {
  /** Original (or best available) URL, used as <img src>. */
  src: string;
  /** Responsive candidates from image_variants, when generated. */
  srcset?: string;
  /** What the lightbox loads: 1920w variant, else largest variant, else the original. */
  full: string;
}
export interface Photos {
  /** Card image: first photo that has variants, else the gallery thumbnail, else the first photo. */
  cover: { src: string; srcset?: string } | null;
  photos: Photo[];
}

interface RawItem {
  type?: unknown;
  file_url?: unknown;
  image?: unknown;
  sequence?: unknown;
  image_variants?: unknown;
}
interface RawGallery {
  thumbnail?: unknown;
  items?: unknown;
}

const str = (v: unknown): string | null => (typeof v === "string" && v !== "" ? v : null);

function allowedHosts(): Set<string> {
  const hosts = new Set<string>();
  const cfg = getConfig();
  // SITE = canonical prod origin; cfg.siteUrl = where this server is actually served
  // (same in prod, localhost:8000 on the local review stack).
  for (const base of [SITE, cfg.siteUrl, cfg.apiInternalUrl]) {
    try {
      const h = new URL(base).hostname;
      hosts.add(h);
      if (h.startsWith("www.")) hosts.add(h.slice(4));
      else if (h.split(".").length === 2) hosts.add(`www.${h}`);
    } catch {
      /* ignore malformed config */
    }
  }
  return hosts;
}

/** Only our own media: http(s) on the site or API origin. Placeholder/foreign hosts are rejected. */
export function ownMediaUrl(raw: unknown): string | null {
  const s = str(raw);
  if (s === null) return null;
  try {
    const u = new URL(s, SITE);
    if (u.protocol !== "https:" && u.protocol !== "http:") return null;
    return allowedHosts().has(u.hostname) ? u.href : null;
  } catch {
    return null;
  }
}

function toVariants(v: unknown): Record<string, string> | null {
  return v !== null && typeof v === "object" && !Array.isArray(v) ? (v as Record<string, string>) : null;
}

function photosOf(items: unknown): Photo[] {
  if (!Array.isArray(items)) return [];
  const out: { photo: Photo; seq: number; idx: number }[] = [];
  (items as RawItem[]).forEach((it, idx) => {
    if (it?.type !== "image") return;
    const src = ownMediaUrl(it.file_url ?? it.image);
    if (src === null) return;
    const list = variantList(toVariants(it.image_variants), new URL(src).origin);
    // null sequence ranks after numbered ones
    const seq = typeof it.sequence === "number" ? it.sequence : Infinity;
    out.push({ photo: { src, srcset: srcsetOf(list), full: urlAtWidth(list, LIGHTBOX_WIDTH) ?? largestUrl(list) ?? src }, seq, idx });
  });
  return out.sort((a, b) => a.seq - b.seq || a.idx - b.idx).map((x) => x.photo);
}

function build(thumbnail: unknown, items: unknown): Photos {
  const photos = photosOf(items);
  const thumb = ownMediaUrl(thumbnail);
  const withVariants = photos.find((p) => p.srcset !== undefined);
  const cover: { src: string; srcset?: string } | null = withVariants ?? (thumb ? { src: thumb } : (photos[0] ?? null));
  return { cover: cover ? { src: cover.src, srcset: cover.srcset } : null, photos };
}

function warn(what: string, id: number, why: unknown) {
  console.warn("[career]", what, id, why instanceof Error ? why.message : why);
}

/** GET /api/galleries/{id}. null = request failed or gallery missing. */
export async function loadGallery(id: number, lang: Lang): Promise<Photos | null> {
  try {
    const g = await apiGet<RawGallery>(apiPath`/galleries/${String(id)}`, { lang });
    if (!g) return warn("gallery", id, "not found"), null;
    return build(g.thumbnail, g.items);
  } catch (e) {
    warn("gallery", id, e);
    return null;
  }
}

/** All galleries of an award payload merged in order (items + first usable thumbnail). */
export function photosFromGalleries(galleries: unknown): Photos {
  const list = (Array.isArray(galleries) ? galleries : []) as RawGallery[];
  const items = list.flatMap((g) => (Array.isArray(g?.items) ? g.items : []));
  const thumb = list.map((g) => g?.thumbnail).find((t) => ownMediaUrl(t) !== null);
  return build(thumb, items);
}

/** GET /api/awards/{id}/galleries, all galleries of the award merged in order. */
export async function loadAwardPhotos(id: number, lang: Lang): Promise<Photos | null> {
  try {
    const d = await apiGet<{ galleries?: RawGallery[] }>(apiPath`/awards/${String(id)}/galleries`, { lang });
    if (!d || !Array.isArray(d.galleries)) return warn("award", id, "not found"), null;
    return photosFromGalleries(d.galleries);
  } catch (e) {
    warn("award", id, e);
    return null;
  }
}

/** GET /api/awards: id -> slug for awards that already have one (field arrives with phase 3G). */
export async function loadAwardSlugs(lang: Lang): Promise<Map<number, string>> {
  const out = new Map<number, string>();
  try {
    const list = await apiGet<{ id?: unknown; slug?: unknown }[]>("/awards", { lang });
    if (Array.isArray(list)) {
      for (const a of list) if (typeof a?.id === "number" && str(a.slug)) out.set(a.id, a.slug as string);
    }
  } catch (e) {
    warn("awards", 0, e);
  }
  return out;
}

const SLUG = /^[a-z0-9-]+$/;

/** Award page URL, or null while the award has no (valid) slug. */
export function awardHref(award: { slug?: unknown }, lang: Lang): string | null {
  const slug = str(award.slug);
  if (slug === null || !SLUG.test(slug)) return null;
  return localizedPath(`/${lang}/penghargaan/${slug}`, lang);
}
