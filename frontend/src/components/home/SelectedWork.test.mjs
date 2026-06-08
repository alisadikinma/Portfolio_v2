// Smoke test for SelectedWork.vue (The Operator §6). File-content assertions.
// Run: node src/components/home/SelectedWork.test.mjs
//
// §6 = metric-led project cards from LIVE /api/homepage/featured.featured_projects
// (real integration per the plan's Data Integration Map). Cards link to the
// project detail; a footer links to the full catalogue with the real count.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'SelectedWork.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('consumes useHomepageFeatured (live featured_projects)', () => {
  assert.ok(src.includes('useHomepageFeatured'), 'must use useHomepageFeatured')
  assert.ok(/featured_projects/.test(src), 'must read featured_projects from payload')
})

ok('cards deep-link to project detail', () =>
  assert.ok(/\/projects\/\$\{|`\/projects\/|to="\/projects\//.test(src),
    'must link to /projects/{slug}'))

ok('footer links to full catalogue with real count', () => {
  assert.ok(/projects_count/.test(src), 'must surface real projects_count')
})

ok('renders technologies/tech chips from real data', () =>
  assert.ok(/technolog/i.test(src), 'must render technologies chips'))

ok('handles loading + empty states', () => {
  assert.ok(/isLoading|loading/i.test(src), 'must reference loading state')
  assert.ok(/v-if|v-else/.test(src), 'must branch for empty/loaded')
})

ok('no placeholder / TODO markers', () =>
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum/i.test(src), 'no placeholder markers'))

console.log(`\n${passed} checks passed.`)
