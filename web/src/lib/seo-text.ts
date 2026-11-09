import sanitize from "sanitize-html";

export const TITLE_MAX = 60;
export const DESC_MIN = 120;
export const DESC_MAX = 160;
const NAME = "Ali Sadikin Ma";

/** Clips at a word boundary with a single "…" (never "..."); result is at most `max` chars. */
export function clipWords(s: string, max: number): string {
  const t = s.replace(/\s+/g, " ").trim();
  if (t.length <= max) return t;
  const cut = t.slice(0, max - 1);
  // The cut already ends on a whole word when the next char is not a letter/digit.
  const atBoundary = !/[\p{L}\p{N}]/u.test(t[max - 1]);
  const sp = cut.lastIndexOf(" ");
  const base = atBoundary ? cut : sp > max / 2 ? cut.slice(0, sp) : cut;
  return base.replace(/[\s,.;:!?\-–—|/(\[]+$/u, "") + "…";
}

const ENTITIES: Record<string, string> = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: " " };
const decodeEntities = (s: string) =>
  s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, e: string) => {
    if (e[0] === "#") {
      const n = e[1].toLowerCase() === "x" ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10);
      return Number.isFinite(n) && n > 0 && n < 0x110000 ? String.fromCodePoint(n) : m;
    }
    return ENTITIES[e.toLowerCase()] ?? m;
  });

/** Plain text of an HTML fragment (tags dropped, entities decoded, whitespace collapsed). */
export function htmlToText(html: string): string {
  const stripped = sanitize(html, { allowedTags: [], allowedAttributes: {}, nonTextTags: ["script", "style", "textarea", "noscript"] });
  return decodeEntities(stripped).replace(/\s+/g, " ").trim();
}

/** Text of the first non-empty <p> in an HTML fragment, or "". */
export function firstParagraphText(html: string): string {
  for (const m of (html ?? "").matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi)) {
    const t = htmlToText(m[1]);
    if (t !== "") return t;
  }
  return "";
}

export const wordCount = (s: string): number => s.split(/\s+/).filter(Boolean).length;

/**
 * Blog <title>: meta_title wins as-is (clipped only past 60). Without it: "{title} | name" when it fits,
 * else the clipped title alone. The author name never appears twice.
 */
/** The old pipeline cut some meta fields mid-word and appended "..." / "…": not usable as-is. */
export const isCut = (s: string) => /(\.\.\.|…)\s*$/.test(s);

export function postTitle(title: string, metaTitle?: string | null): string {
  const meta = (metaTitle ?? "").trim();
  if (meta !== "" && !isCut(meta)) return clipWords(meta, TITLE_MAX);
  const t = title.trim();
  if (t.includes(NAME)) return clipWords(t, TITLE_MAX);
  const withName = `${t} | ${NAME}`;
  return withName.length <= TITLE_MAX ? withName : clipWords(t, TITLE_MAX);
}

/** meta_description -> excerpt -> first <p>; a source under 120 chars yields to the next; never empty. */
export function postDescription(o: { meta?: string | null; excerpt?: string | null; content?: string | null; fallback?: string }): string {
  const meta = isCut(o.meta ?? "") ? "" : o.meta ?? "";
  const sources = [meta, o.excerpt ?? "", firstParagraphText(o.content ?? "")].map((s) => htmlToText(s));
  const pick = sources.find((s) => s.length >= DESC_MIN) ?? sources.find((s) => s !== "") ?? (o.fallback ?? "").trim();
  return clipWords(pick, DESC_MAX);
}

/**
 * Answer-first lede (gate: first paragraph after the H1 has >= 25 words). Content Engine posts open
 * with short hook lines, so the lede comes from the summary fields: first source with enough words.
 */
export function pickLede(sources: (string | null | undefined)[], minWords = 25): string {
  for (const s of sources) {
    const t = htmlToText(s ?? "");
    if (wordCount(t) >= minWords) return t;
  }
  return "";
}
