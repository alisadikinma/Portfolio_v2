import { SITE } from "./schema";

/**
 * Origin for relative `image_variants` paths ("/storage/..."): the origin of an absolute featured_image,
 * else the public site origin (which serves /storage). Never empty, so a path is never left relative.
 */
export function imageOrigin(featuredImage?: string | null): string {
  if (featuredImage) {
    try {
      return new URL(featuredImage).origin;
    } catch {
      /* relative featured image: fall through */
    }
  }
  return SITE;
}

export interface Variant {
  w: number;
  url: string;
}

/** `image_variants` ({"320w": "/storage/..."}) -> absolute URLs, ascending by width; junk keys/values dropped. */
export function variantList(variants: Record<string, string> | null | undefined, origin: string): Variant[] {
  const abs = (u: string) => (/^https?:\/\//.test(u) ? u : origin + u);
  return Object.entries(variants ?? {})
    .map(([k, v]) => ({ w: /^(\d+)w$/.exec(k)?.[1], url: v }))
    .filter((x): x is { w: string; url: string } => !!x.w && typeof x.url === "string" && x.url !== "")
    .map((x) => ({ w: Number(x.w), url: abs(x.url) }))
    .sort((a, b) => a.w - b.w);
}

export const srcsetOf = (list: Variant[]): string | undefined =>
  list.length > 0 ? list.map((v) => `${v.url} ${v.w}w`).join(", ") : undefined;

export const largestUrl = (list: Variant[]): string | undefined => list[list.length - 1]?.url;

export const urlAtWidth = (list: Variant[], w: number): string | undefined => list.find((v) => v.w === w)?.url;
