// Phase B — admin-settings-simplify plan (2026-06-09).
// The Sosmed→LinkedIn form rendered 3 dead/superseded field groups
// (cancel window, format-mix governor ×3, depth threshold — all inert under
// the June-9 force-carousel ship). They must be removed from the RENDER, but
// their keys MUST stay in linkedinFormData + the save payload so the payload
// is byte-identical and the change is reversible (backend untouched).
//
// Source-assertion test (mirrors the project's regex-against-source pattern,
// e.g. ReceiptsBento.test.mjs). Run: node src/views/admin/settingsFormSosmed.test.mjs

import assert from 'node:assert'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const src = readFileSync(join(__dirname, 'SettingsForm.vue'), 'utf8')

let passed = 0
const check = (label, fn) => { fn(); passed++; console.log(`  ✓ ${label}`) }

const DEAD_KEYS = [
  'linkedin_depth_score_threshold',
  'linkedin_cancel_window_minutes',
  'linkedin_format_carousel_target_ratio',
  'linkedin_format_lookback_window',
  'linkedin_format_governor_enabled',
]

check('dead-field input bindings (v-model) are no longer rendered', () => {
  for (const key of DEAD_KEYS) {
    assert.ok(
      !src.includes(`v-model="linkedinFormData.${key}"`),
      `${key} v-model input still rendered`,
    )
  }
})

check('dead-field <input id="..."> blocks are gone', () => {
  for (const key of DEAD_KEYS) {
    assert.ok(!src.includes(`id="${key}"`), `${key} input element still present`)
  }
})

check('format governor toggle binding is gone', () => {
  assert.ok(
    !src.includes("linkedinFormData.linkedin_format_governor_enabled === 'true'"),
    'format governor toggle still rendered',
  )
})

check('all 5 keys RETAINED in formData defaults + save payload (reversible, backend untouched)', () => {
  for (const key of DEAD_KEYS) {
    // appears in the ref default block AND the save payload block → at least 2 refs
    const occurrences = src.split(key).length - 1
    assert.ok(
      occurrences >= 2,
      `${key} should remain in formData defaults + payload (found ${occurrences} refs)`,
    )
  }
})

check('surviving LinkedIn fields are grouped under 3 section headers', () => {
  for (const header of ['Connection', 'Publishing', 'First comment']) {
    // h3 headers are multiline in the SFC → match text between tags, whitespace-tolerant
    const re = new RegExp(`>\\s*${header}\\s*<`)
    assert.ok(re.test(src), `missing section header "${header}"`)
  }
})

console.log(`\n${passed} checks passed`)
