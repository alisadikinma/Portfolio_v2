// Smoke test for JoinTheBuild.vue (The Operator §9 — closing CTA).
// Run: node src/components/home/JoinTheBuild.test.mjs
//
// §9 = follow @alisadikinma (IG · TikTok · LinkedIn · YT) + newsletter signup
// (live /newsletter/subscribe — name + email + WhatsApp E.164) + soft secondary
// "Got an AI problem? WhatsApp".
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(here, 'JoinTheBuild.vue'), 'utf8') // throws if missing → RED

let passed = 0
const ok = (n, fn) => { fn(); passed++; console.log(`  ✓ ${n}`) }

ok('newsletter subscribe wired to live API', () => {
  assert.ok(src.includes('useNewsletter'), 'must use useNewsletter composable')
  assert.ok(/subscribe\s*\(/.test(src), 'must call subscribe()')
})

ok('collects name + email + WhatsApp (E.164)', () => {
  assert.ok(/v-model="?name|\bname\b/.test(src), 'must bind name')
  assert.ok(/email/i.test(src), 'must bind email')
  assert.ok(/whatsapp/i.test(src), 'must bind whatsapp number')
  assert.ok(/\\\+\[1-9\]|type="tel"/.test(src), 'WhatsApp field should be tel / E.164 hinted')
})

ok('follow handles across 4 platforms', () => {
  for (const p of ['linkedin', 'instagram', 'tiktok', 'youtube'])
    assert.ok(new RegExp(p, 'i').test(src), `missing social platform: ${p}`)
  assert.ok(/@alisadikinma/.test(src), 'must surface the @alisadikinma handle')
})

ok('soft WhatsApp secondary CTA', () =>
  assert.ok(/wa\.me|whatsapp/i.test(src), 'must offer a WhatsApp contact path'))

ok('handles success + error feedback', () => {
  assert.ok(/success/i.test(src), 'must surface success state')
  assert.ok(/error/i.test(src), 'must surface error state')
})

ok('no debt markers (HTML placeholder attr is fine)', () => {
  // Exclude the legit `placeholder=` / `::placeholder` from the scan; only the
  // uppercase PLACEHOLDER debt convention + TODO/FIXME/lorem ipsum should fail.
  assert.ok(!/TODO|FIXME|PLACEHOLDER|lorem ipsum|\bdummy\b/.test(src),
    'no code-debt markers')
})

console.log(`\n${passed} checks passed.`)
