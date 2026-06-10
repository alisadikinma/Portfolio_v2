// Phase C — scan-blog removal (2026-06-10).
// Source-assertion smoke test: the manual "Scan blog now" trigger is gone from
// LinkedInQueueList.vue. Publishing now auto-creates cross-post drafts
// (event-driven), so neither the endpoint path nor the handler should remain.
//
// Run: node --test src/views/admin/linkedinQueueNoScan.test.mjs

import { test } from 'node:test'
import assert from 'node:assert'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const source = readFileSync(join(__dirname, 'LinkedInQueueList.vue'), 'utf8')

test('LinkedInQueueList.vue no longer references the scan-blog-now endpoint', () => {
  assert.ok(
    !source.includes('scan-blog-now'),
    'LinkedInQueueList.vue should not reference the scan-blog-now HTTP endpoint',
  )
})

test('LinkedInQueueList.vue no longer references the scanBlogNow handler', () => {
  assert.ok(
    !source.includes('scanBlogNow'),
    'LinkedInQueueList.vue should not reference the scanBlogNow function',
  )
})
