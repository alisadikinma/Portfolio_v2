import { describe, it, expect } from 'vitest'
import { isTerminal, statusLabel, segmentDot, workerOnline, VIDEO_FULL_PLATFORMS } from './videoFullHelpers.js'

describe('videoFullHelpers', () => {
  it('VIDEO_FULL_PLATFORMS includes reddit, facebook, youtube', () => {
    expect(VIDEO_FULL_PLATFORMS).toEqual(
      expect.arrayContaining(['linkedin', 'instagram', 'tiktok', 'threads', 'reddit', 'facebook', 'youtube'])
    )
  })

  it('isTerminal only for drafted/failed', () => {
    expect(isTerminal('drafted')).toBe(true)
    expect(isTerminal('failed')).toBe(true)
    expect(isTerminal('processing_local')).toBe(false)
  })

  it('statusLabel maps to Indonesian, falls back to raw', () => {
    expect(statusLabel('queued_local')).toBe('Menunggu worker')
    expect(statusLabel('uploaded')).toBe('Siap direview')
    expect(statusLabel('weird')).toBe('weird')
  })

  it('segmentDot picks a class per status, default slate', () => {
    expect(segmentDot('done')).toContain('emerald')
    expect(segmentDot('processing')).toContain('animate-pulse')
    expect(segmentDot('???')).toBe('bg-slate-400')
  })

  it('workerOnline true within 3 minutes, false otherwise', () => {
    const now = Date.parse('2026-06-16T10:00:00Z')
    expect(workerOnline('2026-06-16T09:58:00Z', now)).toBe(true)
    expect(workerOnline('2026-06-16T09:55:00Z', now)).toBe(false)
    expect(workerOnline(null, now)).toBe(false)
  })
})
