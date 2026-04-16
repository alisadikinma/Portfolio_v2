// Minimal regex-based DOMParser shim for Node (test-only, not for production)
// Good enough for the 4 assertions in this smoke test.
function stripTags(s) {
  return String(s).replace(/<[^>]*>/g, '')
}

class ShimDOMParser {
  parseFromString(html, _type) {
    // Expect input like `<div>${userHtml}</div>`
    const match = html.match(/^<div>([\s\S]*)<\/div>$/)
    const inner = match ? match[1] : html

    const children = []
    const re = /<(\w+)[^>]*>([\s\S]*?)<\/\1>/g
    let m
    while ((m = re.exec(inner)) !== null) {
      children.push({
        tagName: m[1].toUpperCase(),
        textContent: stripTags(m[2]),
      })
    }

    return {
      body: {
        firstChild: {
          children,
        },
      },
    }
  }
}

globalThis.DOMParser = ShimDOMParser

// Import after shim is installed
const { parseBlockElements, resolveImagePosition } = await import('./imagePositioning.js')

function mkBlocks(n) {
  const arr = []
  for (let i = 0; i < n; i++) {
    arr.push({ tagName: 'P', textContent: '' })
  }
  return arr
}

let failed = 0

function assertEqual(label, actual, expected) {
  if (actual === expected) {
    console.log(`PASS: ${label} (got ${actual})`)
  } else {
    console.log(`FAIL: ${label} — expected ${expected}, got ${actual}`)
    failed++
  }
}

// 1. Cover image returns 0
assertEqual(
  'cover image returns 0',
  resolveImagePosition({ type: 'cover' }, 0, 1, []),
  0
)

// 2. Explicit suggested_position
assertEqual(
  'explicit suggested_position honored',
  resolveImagePosition({ suggested_position: 5 }, 0, 1, mkBlocks(10)),
  5
)

// 3. insert_after_heading match
const html = '<p>Intro</p><h2>Getting Started</h2><p>Body</p><h2>Conclusion</h2>'
const blocks = parseBlockElements(html)
assertEqual(
  'insert_after_heading matches h2 at index 1 → position 2',
  resolveImagePosition({ insert_after_heading: 'Getting Started' }, 0, 1, blocks),
  2
)

// 4. Even distribute
const expected4 = Math.max(1, Math.floor(12 / 5) * 2) // = max(1, 2*2) = 4
assertEqual(
  'even distribution across 12 blocks, 4 images, index 1',
  resolveImagePosition({}, 1, 4, mkBlocks(12)),
  expected4
)

if (failed > 0) {
  console.log(`\n${failed} assertion(s) failed.`)
  process.exit(1)
} else {
  console.log('\nAll assertions passed.')
  process.exit(0)
}
