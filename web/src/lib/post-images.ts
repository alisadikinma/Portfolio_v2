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
