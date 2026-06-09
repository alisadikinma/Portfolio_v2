// Tests for personSchema.js (The Operator GEO levers G2 + G3).
// Run: node src/utils/personSchema.test.mjs
import assert from 'node:assert/strict'
import { buildPersonSchema, buildFaqSchema } from './personSchema.js' // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('buildPersonSchema is a function', () =>
  assert.equal(typeof buildPersonSchema, 'function'))

const person = buildPersonSchema()

ok('Person core identity', () => {
  assert.equal(person['@context'], 'https://schema.org')
  assert.equal(person['@type'], 'Person')
  assert.equal(person.name, 'Ali Sadikin Ma')
  assert.equal(person.jobTitle, 'AI Generalist')
})

ok('award[] includes Demo Day #1', () => {
  assert.ok(Array.isArray(person.award) && person.award.length >= 3, 'award[] non-empty')
  assert.ok(person.award.some((a) => /Demo Day 2026/.test(a)), 'must list Demo Day 2026')
})

ok('knowsAbout the 3 course disciplines', () => {
  const blob = JSON.stringify(person.knowsAbout)
  for (const k of ['Agent', 'Vibe Coding', 'Video'])
    assert.ok(new RegExp(k, 'i').test(blob), `knowsAbout missing: ${k}`)
})

ok('worksFor INDUSIA + alumniOf international programs', () => {
  assert.ok(/INDUSIA/i.test(JSON.stringify(person.worksFor)), 'worksFor INDUSIA')
  const alumni = JSON.stringify(person.alumniOf)
  assert.ok(/UNCTAD|Alibaba/i.test(alumni) && /Google/i.test(alumni), 'alumniOf incl UNCTAD + Google')
})

ok('sameAs[] entity graph (LinkedIn present)', () => {
  assert.ok(Array.isArray(person.sameAs) && person.sameAs.some((u) => /linkedin\.com/.test(u)),
    'sameAs must include LinkedIn')
})

ok('sameAs[] links the Wikidata entity (Q140135628)', () => {
  assert.ok(person.sameAs.some((u) => /wikidata\.org\/wiki\/Q140135628/.test(u)),
    'sameAs must include the Wikidata Q-ID for Knowledge Graph reconciliation')
})

ok('accepts overrides (image + sameAs)', () => {
  const o = buildPersonSchema({ image: 'https://x/y.jpg', sameAs: ['https://example.com/a'] })
  assert.equal(o.image, 'https://x/y.jpg')
  assert.ok(o.sameAs.includes('https://example.com/a'))
})

const faq = buildFaqSchema()

ok('FAQPage with 3 answer-shaped Q/A', () => {
  assert.equal(faq['@type'], 'FAQPage')
  assert.ok(Array.isArray(faq.mainEntity) && faq.mainEntity.length >= 3, '≥3 questions')
  const text = JSON.stringify(faq)
  assert.ok(/Who is Ali Sadikin Ma/i.test(text), 'asks who is Ali')
  assert.ok(/learn/i.test(text), 'covers learning AI from Ali')
})

ok('both schemas are valid serialisable JSON', () => {
  JSON.parse(JSON.stringify(person))
  JSON.parse(JSON.stringify(faq))
})

console.log(`\n${passed} checks passed.`)
