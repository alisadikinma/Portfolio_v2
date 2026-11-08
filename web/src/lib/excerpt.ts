const ABBREV = new Set(["e.g", "i.e", "vs", "etc", "mr", "mrs", "ms", "dr", "prof", "inc", "ltd", "co", "st", "no", "approx", "cf"]);

/** Index just after each real sentence end. Skips abbreviations ("e.g."), initials ("J.") and decimals ("4.0"). */
function sentenceEnds(text: string): number[] {
  const ends: number[] = [];
  const re = /[.!?]+["”’')\]]*(?=\s|$)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    const end = m.index + m[0].length;
    if (m[0].startsWith(".")) {
      const before = text.slice(0, m.index);
      const word = (/([A-Za-z.]+)$/.exec(before)?.[1] ?? "").toLowerCase();
      if (m[0] === "." && (ABBREV.has(word) || /^[a-z]$/.test(word))) continue;
      // lowercase continuation after a period ("approx. five") is not a sentence start
      const next = text.slice(end).trimStart()[0];
      if (m[0] === "." && next !== undefined && /[a-z0-9]/.test(next) && word.length <= 3) continue;
    }
    ends.push(end);
  }
  return ends;
}

/** First sentence(s) of `text` that fit within `max` chars; cut at a sentence end, else at a word + "…". */
export function excerptAtSentence(text: string, max = 220): string {
  const t = text.replace(/\s+/g, " ").trim();
  if (t.length <= max) return t;
  const fit = sentenceEnds(t).filter((e) => e <= max);
  if (fit.length > 0) return t.slice(0, fit[fit.length - 1]).trim();
  const cut = t.slice(0, max);
  const sp = cut.lastIndexOf(" ");
  return (sp > 0 ? cut.slice(0, sp) : cut).replace(/[\s,;:]+$/, "") + "…";
}
