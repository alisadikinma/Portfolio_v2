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
 * Inspect a carousel draft's slide image lifecycle from `carousel_slides[]`.
 * Pure mirror of linkedinHelpers.js::inspectCarouselRenderState — re-derived
 * here (not imported) to keep this module import-free for the `.mjs` node test.
 * Returns: 'reauthoring' | 'ready' | 'generating' | 'partial' | 'failed' | 'pending'.
 */
function carouselRenderState(slides) {
  const list = Array.isArray(slides) ? slides : []
  if (list.length === 0) return 'pending'
  let done = 0
  let inFlight = 0
  let failed = 0
  let reauthoring = 0
  for (const slide of list) {
    const s = slide?.image_status
    if (s === 'reauthoring') reauthoring++
    else if (s === 'done' && slide?.image_url) done++
    else if (s === 'generating') inFlight++
    else if (s === 'failed') failed++
  }
  if (reauthoring > 0) return 'reauthoring'
  if (done === list.length) return 'ready'
  if (inFlight > 0) return 'generating'
  if (failed === list.length) return 'failed'
  if (done > 0 || failed > 0) return 'partial'
  return 'pending'
}

/** Map a carousel render state → synthetic status key (or null when ready). */
function renderStatusKey(state) {
  switch (state) {
    case 'reauthoring': return 'carousel_reauthoring'
    case 'pending':     return 'carousel_render_pending'
    case 'generating':  return 'carousel_render_active'
    case 'failed':      return 'carousel_render_failed'
    case 'partial':     return 'carousel_render_partial'
    default:            return null // 'ready' → caller falls back to FSM status
  }
}

/**
 * Badge key for a blog draft: a carousel in `manual_review` whose slides are
 * still re-authoring / rendering shows the REAL in-flight state instead of a
 * misleading "Review". Mirrors linkedinHelpers.js::effectiveStatusMeta.
 */
export function blogDisplayStatus(draft) {
  if (draft?.format === 'carousel' && draft?.status === 'manual_review') {
    const key = renderStatusKey(carouselRenderState(draft.carousel_slides))
    if (key) return key
  }
  return draft?.status
}

/**
 * Badge key for an IG-repurpose card. Once the repurpose pipeline is `drafted`,
 * its linked carousel (a downstream LinkedInPost) may still be rendering — the
 * backend `compact` exposes `render_state` so the list reflects it instead of a
 * flat "Draft ready". Absent / 'ready' → plain FSM status.
 */
export function igDisplayStatus(job) {
  if (job?.status === 'drafted') {
    const key = renderStatusKey(job?.render_state)
    if (key) return key
  }
  return job?.status
}

/** ms elapsed since an ISO timestamp; null when missing/invalid/future. */
function elapsedMs(iso, nowMs) {
  if (!iso) return null
  const t = new Date(iso).getTime()
  if (Number.isNaN(t)) return null
  return Math.max(0, (nowMs ?? Date.now()) - t)
}

/** Compact elapsed: "45s" / "3m" / "1h 4m". */
function fmtElapsed(ms) {
  const s = Math.floor(ms / 1000)
  if (s < 60) return `${s}s`
  const m = Math.floor(s / 60)
  if (m < 60) return `${m}m`
  return `${Math.floor(m / 60)}h ${m % 60}m`
}

/**
 * Short progress text shown next to the synthetic carousel status:
 *   rendering / partial → "N/M · P%" (real slide render progress)
 *   re-authoring        → "~3m" elapsed (single /carousel-gen call, no sub-%)
 *   awaiting render     → "0/M"
 *   else                → '' (no progress chip)
 * `nowMs` is injected so the view's tick makes the elapsed count up live.
 */
export function progressLabel(card, nowMs = Date.now()) {
  const total = card?.renderTotal || 0
  switch (card?.displayStatus) {
    case 'carousel_render_active':
    case 'carousel_render_partial':
      return total > 0 ? `${card.renderDone || 0}/${total} · ${Math.round(((card.renderDone || 0) / total) * 100)}%` : ''
    case 'carousel_render_pending':
      return total > 0 ? `0/${total}` : ''
    case 'carousel_reauthoring': {
      const ms = elapsedMs(card?.reauthorStartedAt, nowMs)
      return ms == null ? '' : `~${fmtElapsed(ms)}`
    }
    default:
      return ''
  }
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
    displayStatus: igDisplayStatus(job),
    updatedAt: job.updated_at ?? null,
    route: { name: 'admin-repurpose-detail', params: { id: job.id } },
    platforms,
    // Source slide-0 is private → lazy blob-fetched in the view when has_cover.
    // `cover_url` is the public generated-carousel 1st slide, the thumbnail
    // fallback once the private source slides have been purged.
    coverUrl: job.cover_url || null,
    coverJobId: job.id,
    hasCover: !!job.has_cover,
    renderDone: job.render_done ?? 0,
    renderTotal: job.render_total ?? 0,
    reauthorStartedAt: job.reauthor_started_at ?? null,
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
    displayStatus: blogDisplayStatus(draft),
    updatedAt: draft.updated_at ?? null,
    route: { name: 'admin-sosmed-draft-detail', params: { id: draft.id } },
    platforms,
    coverUrl,
    coverJobId: null,
    hasCover: !!coverUrl,
    renderDone: draft.render_done ?? 0,
    renderTotal: draft.render_total ?? 0,
    reauthorStartedAt: draft.reauthor_started_at ?? null,
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
