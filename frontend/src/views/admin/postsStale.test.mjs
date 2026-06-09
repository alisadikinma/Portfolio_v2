// Structural smoke test for useStalePosts.js (Node --test runner).
// Pattern: read the module source as text and assert exports + endpoint
// strings match the backend routes. A full ESM import isn't possible here —
// the composable pulls in @tanstack/vue-query + the @/services/api alias which
// don't resolve under plain Node. This guards the contract without a bundler.
// Run: node --test frontend/src/views/admin/postsStale.test.mjs

import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(
  resolve(here, '../../composables/useStalePosts.js'),
  'utf8'
)

let failed = 0
let passed = 0

function assert(label, cond) {
  if (cond) {
    console.log(`PASS: ${label}`)
    passed++
  } else {
    console.error(`FAIL: ${label}`)
    failed++
  }
}

assert('exports useStalePosts', /export function useStalePosts\b/.test(src))
assert('exports useMarkReviewed', /export function useMarkReviewed\b/.test(src))

// Endpoint strings must match backend routes (routes/api.php content-engine group).
assert(
  'stale-posts GET endpoint matches backend route',
  src.includes('/admin/content-engine/stale-posts')
)
assert(
  'mark-reviewed POST endpoint matches backend route',
  /\/admin\/content-engine\/posts\/\$\{[^}]+\}\/mark-reviewed/.test(src)
)

// Admin freshness convention: 30s staleTime + always refetch on mount.
assert('uses 30s staleTime', /staleTime:\s*30_000/.test(src))
assert("uses refetchOnMount: 'always'", /refetchOnMount:\s*'always'/.test(src))

// Mutation must invalidate the stale query so the badge updates.
assert(
  'mark-reviewed invalidates the stale-posts query',
  /invalidateQueries/.test(src) && /stale-posts/.test(src)
)

console.log(`\n${passed} passed, ${failed} failed`)
if (failed > 0) process.exit(1)
