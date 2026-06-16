import { describe, it, expect } from 'vitest'
import { detailTarget } from './linkedinHelpers.js'

/**
 * A video_carousel anchor is now a first-class sosmed draft: its calendar card
 * opens the LinkedIn draft detail (/sosmed-drafts/{anchor}), which hosts the
 * video preview + IG/Threads caption editing + Approve/Schedule. The repurpose
 * (Social Studio) page stays for video production only.
 */
describe('detailTarget', () => {
  it('routes a video_carousel anchor to the LinkedIn draft detail', () => {
    const t = detailTarget({ id: 7, format: 'video_carousel', repurpose_job_id: 42 }, 'linkedin')
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
