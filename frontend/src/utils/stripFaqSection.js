// Removes the FAQ section from an article's HTML body string so the page
// can render FAQ as an interactive accordion component instead of inline
// headings + paragraphs. The pipeline at article-write always emits FAQ
// as an h2 containing "FAQ" / "Frequently Asked" / "Pertanyaan" text
// followed by Q&A blocks until the next h2 or end of content.
//
// Pure function: safe in node (for tests) and the browser. No DOM required.

const H2_RE = /<h2\b[^>]*>([\s\S]*?)<\/h2>/gi
const FAQ_TEXT_RE = /faq|frequently\s+asked|pertanyaan/i
const NEXT_H2_RE = /<h2\b/i
const INNER_TAGS_RE = /<[^>]*>/g

export function stripFaqSection(html) {
  if (typeof html !== 'string' || html.length === 0) return html

  const h2Iter = new RegExp(H2_RE.source, H2_RE.flags)
  let faqMatch = null
  let m
  while ((m = h2Iter.exec(html)) !== null) {
    const textContent = m[1].replace(INNER_TAGS_RE, '')
    if (FAQ_TEXT_RE.test(textContent)) {
      faqMatch = m
      break
    }
  }
  if (!faqMatch) return html

  const headingStart = faqMatch.index
  const headingEnd = headingStart + faqMatch[0].length

  const afterHeading = html.slice(headingEnd)
  const nextH2Offset = afterHeading.search(NEXT_H2_RE)
  const sliceEnd =
    nextH2Offset === -1 ? html.length : headingEnd + nextH2Offset

  return html.slice(0, headingStart) + html.slice(sliceEnd)
}
