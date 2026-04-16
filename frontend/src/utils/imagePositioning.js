/** Parse HTML into child elements (lightweight, for position calc only) */
export function parseBlockElements(html) {
  if (!html) return []
  const parser = new DOMParser()
  const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html')
  return Array.from(doc.body.firstChild?.children || [])
}

/** Determine image position in content blocks */
export function resolveImagePosition(img, index, totalImages, blocks) {
  // Cover image always goes at the top
  if (img.type === 'cover') return 0

  // Priority 1: explicit suggested_position from plugin
  if (typeof img.suggested_position === 'number') return img.suggested_position

  // Priority 2: match insert_after_heading to a heading in content
  if (img.insert_after_heading && blocks.length > 0) {
    const target = img.insert_after_heading.toLowerCase().trim()
    for (let i = 0; i < blocks.length; i++) {
      if (/^H[1-6]$/i.test(blocks[i].tagName)) {
        const text = blocks[i].textContent.toLowerCase().trim()
        if (text === target || text.includes(target) || target.includes(text)) {
          return i + 1 // place after the matched heading
        }
      }
    }
  }

  // Priority 3: distribute evenly across content
  if (blocks.length > 0 && totalImages > 0) {
    const step = Math.floor(blocks.length / (totalImages + 1))
    return Math.max(1, step * (index + 1))
  }

  return index
}
