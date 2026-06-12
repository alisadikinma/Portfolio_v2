import { test } from 'node:test'
import assert from 'node:assert/strict'

import {
  toCard,
  resolveBlogTitle,
  matchesFilter,
  mergeAndSort,
  countsBySource,
  blogDisplayStatus,
  igDisplayStatus,
  progressLabel,
} from './socialStudioHelpers.js'

// ---- fixtures -------------------------------------------------------------

const igCarouselJob = {
  id: 12,
  status: 'drafted',
  mode: 'carousel',
  title: '5 Cara AI Ubah Marketing',
  has_cover: true,
  slide_count: 7,
  linkedin_post_id: 99,
  updated_at: '2026-06-11T10:00:00+00:00',
}

const igBlogJob = {
  id: 13,
  status: 'rewritten',
  mode: 'blog',
  title: null,
  extracted: { caption: 'Topik dari caption asli\nbaris dua' },
  has_cover: false,
  slide_count: 0,
  content_idea_id: 5,
  updated_at: '2026-06-11T09:00:00+00:00',
}

const blogDraft = {
  id: 88,
  status: 'manual_review',
  format: 'carousel',
  post_id: 40,
  post: {
    id: 40,
    slug: 'ai-marketing',
    featured_image: 'https://alisadikinma.com/storage/posts/cover.jpg',
    translations: [
      { language: 'id', title: 'Judul ID' },
      { language: 'en', title: 'English Title' },
    ],
  },
  updated_at: '2026-06-11T11:00:00+00:00',
}

// ---- toCard: IG ----------------------------------------------------------

test('toCard(igJob) → ig card with backend title + repurpose-detail route', () => {
  const c = toCard(igCarouselJob)
  assert.equal(c.kind, 'ig')
  assert.equal(c.id, 12)
  assert.equal(c.title, '5 Cara AI Ubah Marketing')
  assert.equal(c.sourceBadge, 'ig')
  assert.equal(c.status, 'drafted')
  assert.equal(c.updatedAt, '2026-06-11T10:00:00+00:00')
  assert.deepEqual(c.route, { name: 'admin-repurpose-detail', params: { id: 12 } })
  assert.equal(c.hasCover, true)
  assert.equal(c.coverJobId, 12)
  assert.equal(c.coverUrl, null)
  // carousel mode → all four platform chips expected
  assert.deepEqual(c.platforms, ['li', 'ig', 'tt', 'th'])
})

test('toCard(igJob) maps backend cover_url → coverUrl (generated-carousel thumbnail fallback)', () => {
  const url = 'https://alisadikinma.com/storage/linkedin-carousel/creator-brand-li-149-slide-01-cover.png'
  const c = toCard({ ...igCarouselJob, has_cover: false, slide_count: 0, cover_url: url })
  assert.equal(c.coverUrl, url)
  assert.equal(c.hasCover, false) // source purged → no private blob fetch, public fallback used
})

test('toCard(igJob) title falls back to first caption line when backend title null', () => {
  const c = toCard(igBlogJob)
  assert.equal(c.title, 'Topik dari caption asli')
  // blog-mode repurpose → produces a ContentIdea, no carousel siblings yet
  assert.deepEqual(c.platforms, [])
})

// ---- toCard: Blog --------------------------------------------------------

test('toCard(blogDraft) → blog card, EN title, sosmed-draft-detail route, featured cover', () => {
  const c = toCard(blogDraft)
  assert.equal(c.kind, 'blog')
  assert.equal(c.id, 88)
  assert.equal(c.title, 'English Title')
  assert.equal(c.sourceBadge, 'blog')
  assert.equal(c.status, 'manual_review')
  assert.deepEqual(c.route, { name: 'admin-sosmed-draft-detail', params: { id: 88 } })
  assert.equal(c.coverUrl, 'https://alisadikinma.com/storage/posts/cover.jpg')
  assert.equal(c.coverJobId, null)
  assert.equal(c.hasCover, true)
  assert.deepEqual(c.platforms, ['li', 'ig', 'tt', 'th'])
})

test('toCard produces identical key sets for both kinds', () => {
  const a = Object.keys(toCard(igCarouselJob)).sort()
  const b = Object.keys(toCard(blogDraft)).sort()
  assert.deepEqual(a, b)
})

test('resolveBlogTitle prefers EN, falls back to first translation then slug', () => {
  assert.equal(resolveBlogTitle(blogDraft), 'English Title')
  assert.equal(
    resolveBlogTitle({ post: { slug: 's', translations: [{ language: 'id', title: 'Only ID' }] } }),
    'Only ID',
  )
  assert.equal(resolveBlogTitle({ post: { slug: 'just-slug', translations: [] } }), 'just-slug')
  assert.equal(resolveBlogTitle({ post_id: 7, post: null }), 'Post #7')
})

// ---- filter --------------------------------------------------------------

test('matchesFilter handles all/blog/ig/failed', () => {
  const ig = toCard(igCarouselJob)
  const blog = toCard(blogDraft)
  const failed = toCard({ ...igBlogJob, status: 'failed' })

  assert.equal(matchesFilter(ig, 'all'), true)
  assert.equal(matchesFilter(blog, 'all'), true)
  assert.equal(matchesFilter(ig, 'ig'), true)
  assert.equal(matchesFilter(blog, 'ig'), false)
  assert.equal(matchesFilter(blog, 'blog'), true)
  assert.equal(matchesFilter(ig, 'blog'), false)
  assert.equal(matchesFilter(failed, 'failed'), true)
  assert.equal(matchesFilter(ig, 'failed'), false)
})

// ---- merge / sort --------------------------------------------------------

test('mergeAndSort unions both lists sorted by updatedAt DESC', () => {
  const igCards = [toCard(igCarouselJob), toCard(igBlogJob)]
  const blogCards = [toCard(blogDraft)]
  const merged = mergeAndSort(igCards, blogCards)
  assert.equal(merged.length, 3)
  // blog 11:00 > ig 10:00 > ig 09:00
  assert.deepEqual(merged.map(c => c.id), [88, 12, 13])
})

test('mergeAndSort pushes invalid/missing dates to the end', () => {
  const merged = mergeAndSort(
    [toCard({ ...igCarouselJob, id: 1, updated_at: null })],
    [toCard(blogDraft)],
  )
  assert.deepEqual(merged.map(c => c.id), [88, 1])
})

// ---- displayStatus: carousel render reflection ---------------------------

const slidesWith = (statuses) =>
  statuses.map((s, i) => ({
    slide_number: i + 1,
    image_status: s,
    image_url: s === 'done' ? `https://x/${i}.png` : null,
  }))

test('blogDisplayStatus reflects reauthoring slides over manual_review', () => {
  const d = { format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['reauthoring', 'reauthoring']) }
  assert.equal(blogDisplayStatus(d), 'carousel_reauthoring')
  assert.equal(toCard(d).status, 'manual_review') // raw status preserved for filters
  assert.equal(toCard(d).displayStatus, 'carousel_reauthoring')
})

test('blogDisplayStatus reflects active/pending/partial/failed render', () => {
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['generating', 'pending']) }), 'carousel_render_active')
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['pending', 'pending']) }), 'carousel_render_pending')
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['done', 'failed']) }), 'carousel_render_partial')
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['failed', 'failed']) }), 'carousel_render_failed')
})

test('blogDisplayStatus falls back to FSM status when slides ready or non-carousel', () => {
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'manual_review', carousel_slides: slidesWith(['done', 'done']) }), 'manual_review')
  assert.equal(blogDisplayStatus({ format: 'text', status: 'manual_review' }), 'manual_review')
  assert.equal(blogDisplayStatus({ format: 'carousel', status: 'published', carousel_slides: slidesWith(['generating']) }), 'published')
})

test('igDisplayStatus reflects downstream render_state only while drafted', () => {
  assert.equal(igDisplayStatus({ status: 'drafted', render_state: 'generating' }), 'carousel_render_active')
  assert.equal(igDisplayStatus({ status: 'drafted', render_state: 'reauthoring' }), 'carousel_reauthoring')
  assert.equal(igDisplayStatus({ status: 'drafted', render_state: 'ready' }), 'drafted')
  assert.equal(igDisplayStatus({ status: 'drafted' }), 'drafted')
  assert.equal(igDisplayStatus({ status: 'rewriting', render_state: 'generating' }), 'rewriting')
})

// ---- progressLabel: % / N-of-M / elapsed ---------------------------------

test('progressLabel shows N/M · P% while rendering or partial', () => {
  assert.equal(progressLabel({ displayStatus: 'carousel_render_active', renderDone: 3, renderTotal: 7 }), '3/7 · 43%')
  assert.equal(progressLabel({ displayStatus: 'carousel_render_partial', renderDone: 5, renderTotal: 7 }), '5/7 · 71%')
})

test('progressLabel shows 0/M while awaiting render', () => {
  assert.equal(progressLabel({ displayStatus: 'carousel_render_pending', renderTotal: 7 }), '0/7')
  assert.equal(progressLabel({ displayStatus: 'carousel_render_pending', renderTotal: 0 }), '')
})

test('progressLabel shows ~elapsed while re-authoring (counts up via nowMs)', () => {
  const started = '2026-06-12T10:00:00.000Z'
  const now = Date.parse(started) + 90_000 // +90s
  assert.equal(progressLabel({ displayStatus: 'carousel_reauthoring', reauthorStartedAt: started }, now), '~1m')
  assert.equal(progressLabel({ displayStatus: 'carousel_reauthoring', reauthorStartedAt: started }, Date.parse(started) + 5_000), '~5s')
  assert.equal(progressLabel({ displayStatus: 'carousel_reauthoring', reauthorStartedAt: null }), '')
})

test('progressLabel is empty for ready / non-carousel states', () => {
  assert.equal(progressLabel({ displayStatus: 'manual_review', renderDone: 7, renderTotal: 7 }), '')
  assert.equal(progressLabel({ displayStatus: 'drafted' }), '')
  assert.equal(progressLabel({}), '')
})

test('toCard carries render progress fields for both kinds (keys still identical)', () => {
  const ig = toCard({ ...igCarouselJob, render_done: 2, render_total: 7, reauthor_started_at: '2026-06-12T10:00:00Z' })
  assert.equal(ig.renderDone, 2)
  assert.equal(ig.renderTotal, 7)
  assert.equal(ig.reauthorStartedAt, '2026-06-12T10:00:00Z')
  const blog = toCard({ ...blogDraft, render_done: 4, render_total: 7 })
  assert.equal(blog.renderDone, 4)
  assert.equal(blog.renderTotal, 7)
})

// ---- countsBySource (Phase D glue, asserted here) ------------------------

test('countsBySource counts all/blog/ig/failed', () => {
  const cards = mergeAndSort(
    [toCard(igCarouselJob), toCard({ ...igBlogJob, status: 'failed' })],
    [toCard(blogDraft)],
  )
  const counts = countsBySource(cards)
  assert.equal(counts.all, 3)
  assert.equal(counts.ig, 2)
  assert.equal(counts.blog, 1)
  assert.equal(counts.failed, 1)
})
