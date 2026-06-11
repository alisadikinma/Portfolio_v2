import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const routerSrc = readFileSync(join(here, 'index.js'), 'utf8')
const layoutSrc = readFileSync(join(here, '../layouts/AdminLayout.vue'), 'utf8')

test('router registers the admin-social-studio route → SocialStudio.vue', () => {
  assert.match(routerSrc, /name:\s*'admin-social-studio'/)
  assert.match(routerSrc, /SocialStudio\.vue/)
})

test('draft-posts + repurpose paths redirect to admin-social-studio', () => {
  // both legacy list paths now redirect into the merged menu
  const redirectCount = (routerSrc.match(/redirect:\s*\{\s*name:\s*'admin-social-studio'\s*\}/g) || []).length
  assert.ok(redirectCount >= 2, `expected >=2 social-studio redirects, got ${redirectCount}`)
  // the old RepurposeJobsList list route is retired (component no longer routed)
  assert.doesNotMatch(routerSrc, /RepurposeJobsList\.vue/)
})

test('detail routes survive (repurpose-detail + sosmed-draft-detail)', () => {
  assert.match(routerSrc, /name:\s*'admin-repurpose-detail'/)
  assert.match(routerSrc, /name:\s*'admin-sosmed-draft-detail'/)
})

test('sidebar shows Social Studio + Content Calendar, not the old labels', () => {
  assert.match(layoutSrc, /Social Studio/)
  assert.match(layoutSrc, /Content Calendar/)
  assert.doesNotMatch(layoutSrc, /IG Repurpose/)
  assert.doesNotMatch(layoutSrc, /Draft Posts/)
  assert.doesNotMatch(layoutSrc, /SOSMED Posts/)
  assert.match(layoutSrc, /\/admin\/social-studio/)
})
