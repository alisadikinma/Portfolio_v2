export const LANGS = ["id", "en"] as const;
export type Lang = (typeof LANGS)[number];

export type Alternates = Record<Lang, string> & { "x-default": string };

// First path segment (after the lang prefix) that differs per language.
const SEGMENT_PAIRS: ReadonlyArray<Record<Lang, string>> = [
  { id: "pelatihan-ai", en: "ai-training" },
  { id: "pembicara-ai", en: "ai-speaker" },
  { id: "penghargaan", en: "awards" },
];

const isLang = (s: string | undefined): s is Lang => s === "id" || s === "en";

function split(path: string): { lang: Lang | null; rest: string[]; suffix: string } {
  const m = path.match(/^([^?#]*)(.*)$/);
  const pure = m ? m[1] : path;
  const suffix = m ? m[2] : "";
  const parts = pure.split("/").filter(Boolean);
  if (isLang(parts[0])) return { lang: parts[0], rest: parts.slice(1), suffix };
  return { lang: null, rest: parts, suffix };
}

export function localizedPath(path: string, lang: Lang): string {
  const { lang: from, rest, suffix } = split(path);
  const segs = [...rest];
  if (segs.length > 0) {
    const first = segs[0];
    const pair = SEGMENT_PAIRS.find((p) => p.id === first || p.en === first);
    if (pair && (from === null || pair[from] === first)) segs[0] = pair[lang];
    else if (pair) segs[0] = pair[lang];
  }
  return "/" + [lang, ...segs].join("/") + suffix;
}

export function alternates(path: string): Alternates {
  const en = localizedPath(path, "en");
  return { id: localizedPath(path, "id"), en, "x-default": en };
}

/** Overrides the prefix-swapped alternates with explicit per-language paths; x-default follows EN. */
export function withAltPaths(alts: Alternates, over?: Partial<Record<Lang, string>>): Alternates {
  if (!over) return alts;
  const id = over.id ?? alts.id;
  const en = over.en ?? alts.en;
  return { id, en, "x-default": en };
}

export function pickLang(acceptLanguage: string | null | undefined): Lang {
  if (!acceptLanguage) return "en";
  const tags = acceptLanguage
    .split(",")
    .map((raw, index) => {
      const [tag, ...params] = raw.trim().split(";");
      const qParam = params.map((p) => p.trim()).find((p) => p.startsWith("q="));
      const q = qParam ? Number(qParam.slice(2)) : 1;
      return { tag: tag.trim().toLowerCase(), q: Number.isFinite(q) ? q : 0, index };
    })
    .filter((t) => t.tag.length > 0 && t.q > 0)
    .sort((a, b) => b.q - a.q || a.index - b.index);
  for (const { tag } of tags) {
    const primary = tag.split("-")[0];
    if (primary === "id" || primary === "in") return "id";
    if (primary === "en") return "en";
  }
  return "en";
}
