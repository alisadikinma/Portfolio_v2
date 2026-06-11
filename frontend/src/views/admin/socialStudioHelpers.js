/**
 * Pure adapters for the Social Studio union list — blog-origin LinkedIn drafts
 * + IG-repurpose jobs collapsed into one card list. Framework-free so they can
 * be unit-tested with `node --test` (socialStudioHelpers.test.mjs) and shared by
 * SocialStudio.vue.
 *
 * NOTE: `resolveBlogTitle` mirrors `postTitle()` in useLinkedInDrafts.js. We
 * re-derive it here (rather than importing) because that module pulls in `vue`
 * + the `@/services/api` alias, which a plain `.mjs` node test can't resolve —
 * keeping this file import-free is the only way to satisfy the tree-test gate.
 */

const ALL_PLATFORMS = ['li', 'ig', 'tt', 'th']

/**
 * Display title for a blog draft. The `posts` table has no `title` column —
 * titles live in `post_translations`. Prefer EN → first translation → slug →
 * `Post #id`. Pure mirror of useLinkedInDrafts.js::postTitle.
 */
export function resolveBlogTitle(draft) {
  const post = draft?.post
  if (!post) return `Post #${draft?.post_id ?? '?'}`
  const translations = Array.isArray(post.translations) ? post.translations : []
  const en = translations.find((t) => t && t.language === 'en')
  return (
    en?.title ||
    translations[0]?.title ||
    post.slug ||
    `Post #${post.id ?? draft?.post_id ?? '?'}`
  )
}

/** First non-empty line of an IG source caption, trimmed to 120 chars. */
function firstCaptionLine(caption) {
  if (!caption || typeof caption !== 'string') return null
  for (const line of caption.split(/\r\n|\r|\n/)) {
    const t = line.trim()
    if (t) return t.slice(0, 120)
  }
  return null
}

/** IG card title: backend-derived `title` → first caption line → fallback. */
function igTitle(job) {
  return (
    (typeof job.title === 'string' && job.title.trim()) ||
    firstCaptionLine(job.extracted?.caption) ||
    'Untitled repurpose'
  )
}

/**
 * Adapt one source row (IG `RepurposeJob` compact OR blog `LinkedInPost` list
 * row) into the unified Social Studio card. Both kinds yield IDENTICAL keys so
 * the view template is source-agnostic.
 *
 * Detection: an IG repurpose row carries `mode` (carousel|blog|null) and never
 * a `format`; a blog draft carries `format` (text|carousel). We branch on the
 * presence of `format`.
 */
export function toCard(item) {
  const isBlog = item && typeof item.format === 'string'
  return isBlog ? blogCard(item) : igCard(item)
}

function igCard(job) {
  // Only a carousel repurpose produces cross-post siblings; blog-mode produces
  // a ContentIdea (no draft/slides yet) so it carries no platform chips.
  const platforms = job.mode === 'carousel' ? [...ALL_PLATFORMS] : []
  return {
    kind: 'ig',
    id: job.id,
    title: igTitle(job),
    sourceBadge: 'ig',
    status: job.status,
    updatedAt: job.updated_at ?? null,
    route: { name: 'admin-repurpose-detail', params: { id: job.id } },
    platforms,
    // Source slide-0 is private → lazy blob-fetched in the view when has_cover.
    // `cover_url` is the public generated-carousel 1st slide, the thumbnail
    // fallback once the private source slides have been purged.
    coverUrl: job.cover_url || null,
    coverJobId: job.id,
    hasCover: !!job.has_cover,
  }
}

function blogCard(draft) {
  const coverUrl = draft.post?.featured_image || null
  // Carousel blog drafts fan out to all four platforms; text drafts ship to
  // LinkedIn (+ Threads) — keep the chip set minimal/honest for text.
  const platforms = draft.format === 'carousel' ? [...ALL_PLATFORMS] : ['li']
  return {
    kind: 'blog',
    id: draft.id,
    title: resolveBlogTitle(draft),
    sourceBadge: 'blog',
    status: draft.status,
    updatedAt: draft.updated_at ?? null,
    route: { name: 'admin-sosmed-draft-detail', params: { id: draft.id } },
    platforms,
    coverUrl,
    coverJobId: null,
    hasCover: !!coverUrl,
  }
}

/** Filter chip predicate: 'all' | 'blog' | 'ig' | 'failed'. */
export function matchesFilter(card, filter) {
  if (!card) return false
  switch (filter) {
    case 'blog':
      return card.kind === 'blog'
    case 'ig':
      return card.kind === 'ig'
    case 'failed':
      return card.status === 'failed'
    case 'all':
    default:
      return true
  }
}

/** ms-since-epoch for a card's updatedAt; invalid/missing → -Infinity (sinks). */
function ts(card) {
  const v = card?.updatedAt ? new Date(card.updatedAt).getTime() : NaN
  return Number.isNaN(v) ? -Infinity : v
}

/** Union both source lists into one array sorted by updatedAt DESC. */
export function mergeAndSort(igCards = [], blogCards = []) {
  return [...igCards, ...blogCards].sort((a, b) => ts(b) - ts(a))
}

/** Counts for the filter chips. `failed` counts across both sources. */
export function countsBySource(cards = []) {
  const counts = { all: cards.length, blog: 0, ig: 0, failed: 0 }
  for (const c of cards) {
    if (c.kind === 'blog') counts.blog++
    else if (c.kind === 'ig') counts.ig++
    if (c.status === 'failed') counts.failed++
  }
  return counts
}
