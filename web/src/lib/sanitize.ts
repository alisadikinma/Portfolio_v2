import sanitize from "sanitize-html";

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
    "*": ["id", "class"],
  },
  allowedSchemes: ["http", "https", "mailto", "tel"],
  allowedSchemesByTag: { img: ["http", "https"] },
  allowProtocolRelative: false,
  // Drop the content of these, not just the tag.
  nonTextTags: ["script", "style", "textarea", "option", "noscript", "iframe", "object", "embed", "form", "svg", "math"],
};

/** Sanitizes admin-authored article HTML before `set:html`. Allowlist based; see tests for the contract. */
export function sanitizeHtml(html: string): string {
  if (typeof html !== "string" || html === "") return "";
  return sanitize(html, OPTIONS);
}
