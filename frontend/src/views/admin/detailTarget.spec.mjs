import { describe, it, expect } from 'vitest'
import { detailTarget } from './linkedinHelpers.js'

/**
 * Phase F — a video_carousel calendar/queue card deep-links to the repurpose detail
 * (where the Zernio IG+Threads publish UI lives), NOT the LinkedIn draft detail.
 * Everything else keeps its existing destination.
 */
describe('detailTarget', () => {
  it('routes a video_carousel anchor to the repurpose detail', () => {
    const t = detailTarget({ id: 7, format: 'video_carousel', repurpose_job_id: 42 }, 'linkedin')
    expect(t).toEqual({ name: 'admin-repurpose-detail', params: { id: 42 } })
  })

  it('falls back to the LinkedIn draft detail when a video row lacks a repurpose_job_id', () => {
    const t = detailTarget({ id: 7, format: 'video_carousel', repurpose_job_id: null }, 'linkedin')
    expect(t).toEqual({ name: 'admin-sosmed-draft-detail', params: { id: 7 } })
  })

  it('routes a normal linkedin draft to the draft detail', () => {
    const t = detailTarget({ id: 9, format: 'carousel' }, 'linkedin')
    expect(t).toEqual({ name: 'admin-sosmed-draft-detail', params: { id: 9 } })
  })

  it('routes a cross-post platform draft to the cross-post detail', () => {
    const t = detailTarget({ id: 5, format: 'carousel' }, 'instagram')
    expect(t).toEqual({ name: 'admin-cross-post-detail', params: { platform: 'instagram', id: 5 } })
  })
})
