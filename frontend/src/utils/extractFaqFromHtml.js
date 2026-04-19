// Fallback FAQ extractor for articles whose stored faq_schema JSON is null
// (older posts predating reliable plugin emission). Scans the body HTML for
// the FAQ h2 heading and pairs up questions with answers across two patterns
// the article-write plugin has emitted over time:
//
//   Pattern A (current):  <h3>Question?</h3><p>Answer.</p>
//   Pattern B (older):    <p><strong>Question?</strong></p><p>Answer.</p>
//
// Returns [{ question, answer }] with answer as trusted HTML (may contain
// <em>, <strong>, <a>). Pure — no DOM required, works in node tests.

const H2_RE = /<h2\b[^>]*>([\s\S]*?)<\/h2>/gi
const FAQ_TEXT_RE = /faq|frequently\s+asked|pertanyaan/i
const BLOCK_RE = /<(h3|h4|p)\b[^>]*>([\s\S]*?)<\/\1>/gi
const INNER_TAGS_RE = /<[^>]*>/g
const STRONG_OR_B_WRAPPED_RE = /^\s*<(?:strong|b)\b[^>]*>([\s\S]*?)<\/(?:strong|b)>\s*$/i

function textOnly(html) {
  return html.replace(INNER_TAGS_RE, '').replace(/\s+/g, ' ').trim()
}

function findFaqSectionBounds(html) {
  const h2Iter = new RegExp(H2_RE.source, H2_RE.flags)
  let faqMatch = null
  let m
  while ((m = h2Iter.exec(html)) !== null) {
    if (FAQ_TEXT_RE.test(textOnly(m[1]))) {
      faqMatch = m
      break
    }
  }
  if (!faqMatch) return null

  const sectionStart = faqMatch.index + faqMatch[0].length
  const afterHeading = html.slice(sectionStart)
  const nextH2Offset = afterHeading.search(/<h2\b/i)
  const sectionEnd =
    nextH2Offset === -1 ? html.length : sectionStart + nextH2Offset

  return { sectionStart, sectionEnd }
}

export function extractFaqFromHtml(html) {
  if (typeof html !== 'string' || html.length === 0) return []

  const bounds = findFaqSectionBounds(html)
  if (!bounds) return []

  const body = html.slice(bounds.sectionStart, bounds.sectionEnd)

  const blocks = []
  const blockIter = new RegExp(BLOCK_RE.source, BLOCK_RE.flags)
  let m
  while ((m = blockIter.exec(body)) !== null) {
    blocks.push({ tag: m[1].toLowerCase(), inner: m[2] })
  }
  if (blocks.length === 0) return []

  const items = []
  let pendingQuestion = null

  for (const block of blocks) {
    const isHeading = block.tag === 'h3' || block.tag === 'h4'
    const boldMatch = block.tag === 'p' ? block.inner.match(STRONG_OR_B_WRAPPED_RE) : null

    if (isHeading) {
      const q = textOnly(block.inner)
      if (q) pendingQuestion = q
    } else if (boldMatch) {
      const q = textOnly(boldMatch[1])
      if (q) pendingQuestion = q
    } else if (pendingQuestion && block.tag === 'p') {
      const answer = block.inner.trim()
      if (answer) {
        items.push({ question: pendingQuestion, answer })
        pendingQuestion = null
      }
    }
  }

  return items
}
