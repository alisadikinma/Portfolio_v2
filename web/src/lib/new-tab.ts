/**
 * Site rule (Ali 2026-11-09): every link opens in a new tab, except in-page section jumps
 * (href with "#"), the language switch (hreflang) and non-page links (mailto, tel, download).
 * Applied once to the rendered HTML in middleware, so no component can forget it.
 * <script> blocks (incl. JSON-LD) are skipped: their "<a" text is code, not markup.
 */
const SCRIPT = /<script\b[\s\S]*?<\/script>/gi;
const A_TAG = /<a(\s[^>]*)>/gi;
const attr = (attrs: string, name: string): string | null => {
  const m = attrs.match(new RegExp(`\\s${name}(?:\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+)))?(?=[\\s/]|$)`, "i"));
  return m ? (m[1] ?? m[2] ?? m[3] ?? "") : null;
};

function rewriteTag(tag: string, attrs: string): string {
  const href = attr(attrs, "href");
  if (href === null || href === "") return tag;
  if (attr(attrs, "target") !== null || attr(attrs, "hreflang") !== null || attr(attrs, "download") !== null) return tag;
  if (href.includes("#") || /^(?:mailto|tel|javascript):/i.test(href)) return tag;
  return `<a${attrs} target="_blank">`;
}

export function newTabLinks(html: string): string {
  let out = "";
  let last = 0;
  for (const m of html.matchAll(SCRIPT)) {
    out += html.slice(last, m.index).replace(A_TAG, rewriteTag) + m[0];
    last = m.index + m[0].length;
  }
  return out + html.slice(last).replace(A_TAG, rewriteTag);
}
