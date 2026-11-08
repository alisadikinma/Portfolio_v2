import assert from 'node:assert/strict'
import {
  fromWibInput, toWibInput, wibInputToMs, isFutureWibInput, wibTomorrowAt, formatWib,
} from './wibTime.js'

// Run under a non-WIB zone on purpose: results must not depend on TZ.
process.env.TZ = 'America/Los_Angeles'

// fromWibInput
assert.equal(fromWibInput('2026-05-13T12:00'), '2026-05-13T12:00:00+07:00')
assert.equal(fromWibInput('2026-05-13T12:00:30'), '2026-05-13T12:00:30+07:00')
assert.equal(fromWibInput('2026-05-13T05:00:00Z'), '2026-05-13T05:00:00Z')
assert.equal(fromWibInput('2026-05-13T12:00:00+07:00'), '2026-05-13T12:00:00+07:00')
assert.equal(fromWibInput(''), '')
assert.equal(fromWibInput(null), '')
assert.equal(fromWibInput('garbage'), '')
assert.equal(fromWibInput('2026-13-45T99:00'), '')
assert.equal(new Date(fromWibInput('2026-05-13T12:00')).toISOString(), '2026-05-13T05:00:00.000Z')

// toWibInput
assert.equal(toWibInput('2026-05-13T05:00:00Z'), '2026-05-13T12:00')
assert.equal(toWibInput('2026-05-12T22:00:00Z'), '2026-05-13T05:00') // day rolls over in WIB
assert.equal(toWibInput('2026-05-13T12:00:00+07:00'), '2026-05-13T12:00')
assert.equal(toWibInput(''), '')
assert.equal(toWibInput('nope'), '')

// round trip
assert.equal(toWibInput(fromWibInput('2026-12-31T23:30')), '2026-12-31T23:30')

// future checks
const now = Date.parse('2026-05-13T05:00:00Z') // 12:00 WIB
assert.equal(wibInputToMs('2026-05-13T12:00'), now)
assert.equal(isFutureWibInput('2026-05-13T11:59', now), false)
assert.equal(isFutureWibInput('2026-05-13T12:01', now), true)
assert.equal(isFutureWibInput('', now), false)

// tomorrow seed is WIB-based (22:00Z is already the next day in WIB)
assert.equal(wibTomorrowAt(9, Date.parse('2026-05-12T22:00:00Z')), '2026-05-14T09:00')

// display
assert.equal(formatWib('2026-05-13T05:00:00Z'), '13 May 2026, 12:00 WIB')
assert.equal(formatWib('2026-05-13T05:00:00Z', { withYear: false }), '13 May, 12:00 WIB')
assert.equal(formatWib(null), '—')
assert.equal(formatWib('bad'), '—')

console.log('wibTime tests passed')
