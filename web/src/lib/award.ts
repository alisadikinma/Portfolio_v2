import { clipWords, DESC_MAX, DESC_MIN, TITLE_MAX } from "./seo-text";

const NAME = "Ali Sadikin Ma";

/** Award title as a display/keyword name: trailing "(...)" dropped, dashes become a colon. */
export function awardShortName(title: string): string {
  return title
    .replace(/\s*\([^)]*\)\s*$/, "")
    .replace(/\s+[-–—]\s+/g, ": ")
    .replace(/\s+/g, " ")
    .trim();
}

/** <title>: "{short name} — Ali Sadikin Ma"; only the title part is cut (whole words, no "…"), the name always stays. */
export function awardTitleTag(title: string): string {
  const short = awardShortName(title);
  const suffix = ` — ${NAME}`;
  const full = short + suffix;
  if (full.length <= TITLE_MAX) return full;
  // Cut on a word boundary without "…": a mid-title ellipsis looks broken in search results.
  return clipWords(short, TITLE_MAX - suffix.length).replace(/…$/u, "").trimEnd() + suffix;
}

// Full EN/ID month names plus common abbreviations. A word is accepted only if it is one of
// these exactly, so "Marching" or "Mayday" never parse as a month.
const MONTH_NAMES: [number, string[]][] = [
  [1, ["jan", "january", "januari"]], [2, ["feb", "february", "februari"]], [3, ["mar", "march", "maret"]],
  [4, ["apr", "april"]], [5, ["may", "mei"]], [6, ["jun", "june", "juni"]], [7, ["jul", "july", "juli"]],
  [8, ["aug", "august", "agu", "agt", "agustus"]], [9, ["sep", "sept", "september"]],
  [10, ["oct", "october", "okt", "oktober"]], [11, ["nov", "november", "nopember"]], [12, ["dec", "december", "des", "desember"]],
];
const MONTHS: Record<string, number> = Object.fromEntries(MONTH_NAMES.flatMap(([n, names]) => names.map((w) => [w, n])));
const iso = (y: number, m: number, d: number): string | undefined => {
  const dt = new Date(Date.UTC(y, m - 1, d));
  if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== m - 1 || dt.getUTCDate() !== d) return undefined;
  return dt.toISOString().slice(0, 10);
};

/**
 * `received_at` is free text ("17 Jan 2026", "2019"). Returns an ISO date, a bare year, or undefined
 * when the text is not exact: a date is never guessed (Event.startDate is omitted instead).
 */
export function awardStartDate(raw: string | null | undefined): string | undefined {
  const s = (raw ?? "").trim();
  if (/^\d{4}$/.test(s)) return s;
  let m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ].*)?$/.exec(s);
  if (m) return iso(Number(m[1]), Number(m[2]), Number(m[3]));
  m = /^(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})$/.exec(s);
  if (m) {
    const month = MONTHS[m[2].toLowerCase()];
    return month ? iso(Number(m[3]), month, Number(m[1])) : undefined;
  }
  return undefined;
}

const PLACES: { re: RegExp; name: string }[] = [
  { re: /bengaluru|bangalore/i, name: "Bengaluru, India" },
  { re: /hangzhou/i, name: "Hangzhou, China" },
  { re: /silicon valley/i, name: "Silicon Valley, United States" },
];

/** A place only when the award's own title/description names it. Otherwise undefined (omitted, not guessed). */
export function awardLocation(title: string, description: string): string | undefined {
  const text = `${title} ${description}`;
  return PLACES.find((p) => p.re.test(text))?.name;
}

const COUNTRIES = new Set(["india", "china", "usa", "indonesia", "singapore"]);

/** "OUTSKILL - INDIA" -> "Outskill": the trailing country goes, shouting all-caps words get normal case. */
export function organizerName(raw: string): string {
  let parts = raw.split(/\s+-\s+/).map((p) => p.trim()).filter(Boolean);
  if (parts.length > 1 && COUNTRIES.has(parts[parts.length - 1].toLowerCase())) parts = parts.slice(0, -1);
  return parts.map((p) => (/^[A-Z]{4,}$/.test(p) ? p[0] + p.slice(1).toLowerCase() : p)).join(", ");
}

export type ResultKind = "first" | "finalist" | "fellow" | "attendee";

/** What the award honestly is, from its title. Startup Grind is attendance only. null = no result line. */
export function awardResultKind(title: string): ResultKind | null {
  if (/startup grind/i.test(title)) return "attendee";
  if (/^1st place/i.test(title)) return "first";
  if (/finalist/i.test(title)) return "finalist";
  if (/fellowship/i.test(title)) return "fellow";
  return null;
}

/**
 * Meta description (120-160 chars): the award name leads (keyword first), then the award text;
 * text that is too short for the gate is topped up with a fixed tail sentence, never invented facts.
 */
export function awardMetaDescription(shortName: string, text: string, tail: string): string {
  const body = text.replace(/\s+/g, " ").trim();
  const lead = body.toLowerCase().includes(shortName.toLowerCase()) ? body : `${shortName}. ${body}`.trim();
  const full = lead.length < DESC_MIN ? `${lead} ${tail}`.trim() : lead;
  return clipWords(full, DESC_MAX);
}
