import { apiGet, apiPath, type Lang } from "./api";
import { localizedPath } from "./i18n";

/** Galleries that back the career journey (vault facts). */
export const GALLERY_SINGAPORE = 14;
export const GALLERIES_MARLIN = [9, 11, 13, 10, 12] as const;
export const GALLERY_SATNUSA = 16;
/** Awards on the stages bento, in display order (first = main card). */
export const STAGE_AWARD_IDS = [13, 10, 11, 7, 8] as const;

export interface Photos {
  /** Gallery cover (API thumbnail), if any. */
  cover: string | null;
  /** Photo URLs in sequence order. */
  urls: string[];
}

interface RawItem {
  file_url?: unknown;
  image?: unknown;
  sequence?: unknown;
}
interface RawGallery {
  thumbnail?: unknown;
  items?: unknown;
}

const str = (v: unknown): string | null => (typeof v === "string" && v !== "" ? v : null);

function itemUrls(items: unknown): string[] {
  if (!Array.isArray(items)) return [];
  return (items as RawItem[])
    .map((it, idx) => ({ url: str(it?.file_url) ?? str(it?.image), seq: typeof it?.sequence === "number" ? it.sequence : idx, idx }))
    .filter((x): x is { url: string; seq: number; idx: number } => x.url !== null)
    .sort((a, b) => a.seq - b.seq || a.idx - b.idx)
    .map((x) => x.url);
}

/** GET /api/galleries/{id}. null = request failed or gallery missing. */
export async function loadGallery(id: number, lang: Lang): Promise<Photos | null> {
  try {
    const g = await apiGet<RawGallery>(apiPath`/galleries/${String(id)}`, { lang });
    if (!g) return null;
    return { cover: str(g.thumbnail), urls: itemUrls(g.items) };
  } catch {
    return null;
  }
}

/** GET /api/awards/{id}/galleries, all galleries of the award merged in order. */
export async function loadAwardPhotos(id: number, lang: Lang): Promise<Photos | null> {
  try {
    const d = await apiGet<{ galleries?: RawGallery[] }>(apiPath`/awards/${String(id)}/galleries`, { lang });
    if (!d || !Array.isArray(d.galleries)) return null;
    const urls = d.galleries.flatMap((g) => itemUrls(g?.items));
    const thumb = d.galleries.map((g) => str(g?.thumbnail)).find((t) => t !== null) ?? null;
    return { cover: urls[0] ?? thumb, urls };
  } catch {
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
  } catch {
    /* no slugs: cards fall back to lightbox-only */
  }
  return out;
}

/** Award page URL, or null while the award has no slug. */
export function awardHref(award: { slug?: unknown }, lang: Lang): string | null {
  const slug = str(award.slug);
  if (slug === null) return null;
  return localizedPath(`/${lang}/penghargaan/${encodeURIComponent(slug)}`, lang);
}
