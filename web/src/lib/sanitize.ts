import sanitize from "sanitize-html";

const ID_PREFIX = "uc-";

// CMS ids are namespaced: scrollspy (#layanan, #tentang) and aria-labelledby ids belong to the site.
function transform(tagName: string, attribs: sanitize.Attributes): sanitize.Tag {
  const a = { ...attribs };
  if (typeof a.id === "string") {
    if (a.id.trim() === "") delete a.id;
    else a.id = ID_PREFIX + a.id;
  }
  if (tagName === "a" && (a.target ?? "").toLowerCase() === "_blank") {
    const rel = new Set((a.rel ?? "").toLowerCase().split(/\s+/).filter(Boolean));
    rel.add("noopener");
    rel.add("noreferrer");
    a.rel = [...rel].join(" ");
  }
  if (tagName === "img") {
    if (a.alt === undefined) a.alt = "";
    if (a.loading === undefined) a.loading = "lazy";
    if (a.decoding === undefined) a.decoding = "async";
  }
  return { tagName, attribs: a };
}

// Allowlist for admin / Content-Engine article HTML. Anything not listed is dropped.
const OPTIONS: sanitize.IOptions = {
  allowedTags: [
    "p", "br", "hr", "h2", "h3", "h4", "h5", "h6", "ul", "ol", "li", "a", "img", "figure", "figcaption",
    "blockquote", "pre", "code", "strong", "b", "em", "i", "u", "s", "sub", "sup", "span", "div",
    "table", "thead", "tbody", "tfoot", "tr", "th", "td", "caption", "colgroup", "col",
  ],
  allowedAttributes: {
    a: ["href", "title", "rel", "target"],
    img: ["src", "alt", "title", "width", "height", "srcset", "sizes", "loading", "decoding"],
    th: ["colspan", "rowspan", "scope"],
    td: ["colspan", "rowspan"],
    code: ["class"],
    pre: ["class"],
    "*": ["id"],
  },
  // Only syntax-highlight classes survive; CMS classes could collide with site CSS.
  allowedClasses: { code: ["language-*"], pre: ["language-*"] },
  allowedSchemes: ["http", "https", "mailto", "tel"],
  allowedSchemesByTag: { img: ["http", "https"] },
  allowProtocolRelative: false,
  // Drop the content of these, not just the tag.
  transformTags: { "*": transform },
  nonTextTags: ["script", "style", "textarea", "option", "noscript", "iframe", "object", "embed", "form", "svg", "math"],
};

/** Sanitizes admin-authored article HTML before `set:html`. Allowlist based; see tests for the contract. */
export function sanitizeHtml(html: string): string {
  if (typeof html !== "string" || html === "") return "";
  return sanitize(html, OPTIONS);
}
