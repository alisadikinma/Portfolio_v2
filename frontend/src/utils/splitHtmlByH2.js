// Split a blog article's HTML string into an ordered array of
// { type: 'chunk' | 'h2', html?, text?, id? } slots, interleaved at every
// top-level H2 boundary.
//
// Consumed by BlogContentInjector.vue to render the article body with Vue
// components (promo card, newsletter, related posts) interleaved between
// H2 sections — impossible with plain v-html.
//
// Pure regex, no DOMParser — stays Node-safe for the .test.mjs smoke tests.
// Tradeoff: nested H2s inside <template>, comments, or CDATA would be split
// too, but blog posts don't contain those.

const H2_RE = /<h2\b[^>]*>([\s\S]*?)<\/h2>/gi

function slugify(text) {
  return String(text || '')
    .toLowerCase()
    // Decode a handful of common HTML entities that matter for readability.
    .replace(/&amp;/g, 'and')
    .replace(/&[a-z]+;/g, ' ')
    .replace(/&#\d+;/g, ' ')
    // Strip any remaining tags (inline <strong>, <em>, etc.)
    .replace(/<[^>]+>/g, '')
    // Collapse non-alphanumerics to dashes.
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function stripTags(html) {
  return String(html || '').replace(/<[^>]+>/g, '').trim()
}

export function splitHtmlByH2(html) {
  if (typeof html !== 'string' || html.length === 0) return []

  const result = []
  const usedIds = new Map()
  let lastIndex = 0
  let match

  H2_RE.lastIndex = 0
  while ((match = H2_RE.exec(html)) !== null) {
    const before = html.slice(lastIndex, match.index)
    if (before.length > 0) {
      result.push({ type: 'chunk', html: before })
    }

    const text = stripTags(match[1]).replace(/\s+/g, ' ').trim()
    let id = slugify(text) || 'section'
    const seen = usedIds.get(id) || 0
    if (seen > 0) id = `${id}-${seen + 1}`
    usedIds.set(slugify(text) || 'section', seen + 1)

    result.push({ type: 'h2', text, id, raw: match[0] })
    lastIndex = match.index + match[0].length
  }

  const trailing = html.slice(lastIndex)
  if (trailing.length > 0) {
    result.push({ type: 'chunk', html: trailing })
  }

  return result
}
