import { test } from 'node:test'
import assert from 'node:assert/strict'

import {
  toCard,
  resolveBlogTitle,
  matchesFilter,
  mergeAndSort,
  countsBySource,
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
