# Newsletter System — Full-stack Design

**Date:** 2026-05-05
**Status:** Brainstorm complete, awaiting plan
**Owner:** Ali Sadikin

## Context

Existing `newsletters` table + `NewsletterController::subscribe/unsubscribe` work end-to-end (verified via curl: returns `{"success":true}`). 4 frontend touchpoints already wired:

1. `Blog.vue` inline section ("Get the latest articles")
2. `components/blog/NewsletterInlineCard.vue` ("Enjoying this?")
3. `components/blog/NewsletterFloatingBanner.vue` ("Before you go —")
4. `components/blog/NewsletterFooterBar.vue` ("Liked what you read?")

**Gap:** No admin panel. No automated weekly digest. No branded email. Subscribers only give email — operator now wants name + WhatsApp captured upfront for high-quality lead pool.

## Design

### Decisions locked

| Decision | Choice | Why |
|---|---|---|
| Field strategy | **All 3 required upfront** (name + email + WhatsApp) | Operator prioritizes lead quality over conversion rate |
| WhatsApp format | **International E.164 strict** (`+[1-9]\d{6,14}`) | Enables future WA broadcast without dirty data |
| Send schedule | **Friday 09:00 WIB, skip if zero new posts** | Matches "One email, every Friday" copy; never spams subscribers |
| Admin scope | **CRUD + Export + Send-Now + Preview** | Full operator control without overkill open/click tracking |
| Unsubscribe | **Token-based (no email re-typing)** | Friction-free, secure (32-char random, per-subscriber) |

### Schema changes

**Migration: `add_lead_fields_to_newsletters`**
```sql
ALTER TABLE newsletters ADD:
  name VARCHAR(120) NOT NULL DEFAULT '',          -- required, up to 120 chars
  whatsapp_number VARCHAR(20) NOT NULL DEFAULT '',-- E.164 normalized, +<7-15 digits>
  unsubscribe_token CHAR(32) NULL UNIQUE,         -- nullable for backfill, populated on next save
  consent_given_at TIMESTAMP NULL,                -- track GDPR-style explicit consent moment
  source VARCHAR(40) NULL                         -- 'blog_inline' | 'inline_card' | 'floating_banner' | 'footer_bar'
INDEX idx_newsletters_unsubscribe_token (unsubscribe_token)
```

DEFAULT '' on NOT NULL columns is for safe backfill — existing rows (currently email-only) get empty strings, then a one-shot artisan command nudges operator to handle legacy or hard-delete them.

**New table: `newsletter_sends`** (send history audit log)
```sql
CREATE TABLE newsletter_sends:
  id, sent_at TIMESTAMP, subscriber_count INT,
  posts_count INT, post_ids JSON,
  status ENUM('sent','failed','skipped','partial'),
  error_message TEXT NULL,
  triggered_by ENUM('cron','manual','test'),
  created_by_user_id BIGINT NULL,                 -- admin user when triggered_by='manual'
  test_recipient VARCHAR(255) NULL,                -- when triggered_by='test'
  duration_seconds INT NULL,
  created_at, updated_at
INDEX idx_newsletter_sends_sent_at (sent_at)
```

### Backend additions

**Model changes** (`app/Models/Newsletter.php`):
- Add `name`, `whatsapp_number`, `unsubscribe_token`, `consent_given_at`, `source` to `$fillable`
- `creating` hook: auto-generate `unsubscribe_token = Str::random(32)` if null
- New scope `scopeActive()` — for future `is_active` flag if we add soft-pause

**Validation rules** (shared between public + automation endpoints):
```php
'name' => ['required', 'string', 'min:2', 'max:120'],
'email' => ['required', 'email:rfc,dns', 'max:255'],
'whatsapp_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
'source' => ['nullable', 'string', 'in:blog_inline,inline_card,floating_banner,footer_bar']
```

**Public route changes** — `NewsletterController::subscribe` widened payload, dedup by email AND whatsapp_number (return DUPLICATE if either matches existing row).

**New unsubscribe flow** — `GET /newsletter/unsubscribe?token={token}` → frontend page → `POST /newsletter/unsubscribe` with `{token}` body. The DELETE endpoint stays for backwards compat but the public page uses POST + token.

**New: `app/Console/Commands/SendWeeklyNewsletter.php`**
```
Signature: newsletter:send-weekly {--dry-run} {--force} {--limit=}
Flow:
  1. Query Post::published()->whereBetween('published_at', [now()->subWeek(), now()])
     ->orderBy('published_at','desc')->with(['category','translations'])->limit(5)->get()
  2. If empty AND NOT --force: log skip + insert newsletter_sends row status=skipped, exit 0
  3. Query Newsletter::all() (paginate chunk(100) for large lists)
  4. For each subscriber: dispatch WeeklyDigestMail::queue() — Resend handles batching
  5. Insert newsletter_sends row status=sent with stats
  --dry-run: print preview HTML to stdout, no sends
  --force: send even if 0 posts (uses fallback "from the archive")
```

**New: `app/Mail/WeeklyDigest.php`** — Queueable Mailable, accepts `Collection<Post> $posts` + `Newsletter $subscriber`. Renders `resources/views/emails/weekly-digest.blade.php` (HTML) + `weekly-digest-text.blade.php` (plain fallback). Subject auto-generated: `"Friday Digest · {N} reads from this week"`.

**New: `app/Http/Controllers/Api/Admin/NewsletterAdminController.php`**
```
GET    /api/admin/newsletter                    list (search by name/email, filter by source/date, paginate)
DELETE /api/admin/newsletter/{id}               remove subscriber
GET    /api/admin/newsletter/export             CSV download (name, email, whatsapp, subscribed_at, source)
GET    /api/admin/newsletter/digest-preview     HTML preview of next digest (current week's posts)
POST   /api/admin/newsletter/send-test          send preview to admin's own email (or specified email)
POST   /api/admin/newsletter/send-now           manual trigger (with confirm modal in UI)
GET    /api/admin/newsletter/sends              send history list (paginate, filter by status)
GET    /api/admin/newsletter/sends/{id}         single send detail (recipient_count, post_ids resolved to titles)
```

All under `auth:sanctum` middleware.

**Schedule entry** — `routes/console.php`:
```php
Schedule::command('newsletter:send-weekly')
    ->fridays()
    ->at('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)
    ->onFailure(fn() => DispatchTelegramNotification::dispatch('newsletter_cron_failed', ...));
```

### Email template (Dark Cinema HTML)

600px max-width, table-based layout, inline CSS (Resend doesn't transform `<style>` for some clients). Tested for Gmail / Outlook / Apple Mail compatibility.

```
┌─────────────────────────────────────────────────┐
│ [≡] alisadikinma                FRIDAY DIGEST │ ← header bar #050506 bg
├─────────────────────────────────────────────────┤
│                                                 │
│  Hi {{name}},                                   │ ← greeting in #EDEDEF
│                                                 │
│  Here's what landed on the blog this week —     │
│  3 essays you might've missed.                  │
│                                                 │
│  ┌─────────────────────────────────────────┐    │
│  │ [featured_image 600x300]                │    │
│  │ ESSAY · 8 min read                      │ ← category eyebrow gold
│  │ The Hidden Cost of Vibe Coding          │ ← title
│  │ 2-line excerpt that makes you click...  │
│  │ [Read this essay →]                     │ ← gold button
│  └─────────────────────────────────────────┘    │
│                                                 │
│  [card 2]                                       │
│  [card 3]                                       │
│                                                 │
│  ─────────────────────────────────────────      │
│  Reply to this email — I read every one.        │ ← personal touch
│                                                 │
│  Ali Sadikin                                    │
│  alisadikinma.com                               │
└─────────────────────────────────────────────────┘
│ Unsubscribe · LinkedIn · Portfolio               │ ← footer with token-based link
└─────────────────────────────────────────────────┘
```

**UTM tracking on every link**: `/blog/{slug}?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-{YYYY-WW}` — feeds into existing analytics.

**Brand colors** (must inline):
- bg-deep `#050506` outer, bg-elevated `#0C0C0F` cards
- accent-gold `#D4A843` CTAs + eyebrows
- accent-cyan `#06B6D4` links
- fg-primary `#EDEDEF` body, fg-muted `#8A8F98` secondary

### Frontend changes

**Composable** (`useNewsletter.js`) signature widened:
```js
async function subscribe({ name, email, whatsappNumber, source })
```
Backwards compat shim: if called with bare string, treat as `email` + throw warn (will be removed once all 4 touchpoints migrated).

**Touchpoint redesign** — all 4 forms expand to 3 fields:

1. **`NewsletterInlineCard.vue`** ("Enjoying this?") — vertical stack: name → email → WhatsApp → Subscribe button. Already has roomy card layout, accommodates fine.

2. **`Blog.vue` inline section** ("Get the latest articles") — same vertical 3-field stack inside the bezel-shell card. Existing layout already centered + max-w-sm.

3. **`NewsletterFloatingBanner.vue`** ("Before you go —") — vertical 3-field stack. Banner is already 380px wide, fits.

4. **`NewsletterFooterBar.vue`** ("Liked what you read?") — **breaking UX change**: compact 1-input bar can't gracefully fit 3 fields. New design: bar shows brand line + "Subscribe →" button → click expands a inline-modal with the 3-field form (or anchor scrolls to inline section). Picking modal-on-click pattern.

**WhatsApp input UX**:
- `<input type="tel">` with placeholder `+628123456789`
- Inline help text: "Format internasional, mulai dengan +"
- Client-side regex check on blur with red-border feedback
- Backend re-validates with strict regex (defense-in-depth)

**New admin view** — `frontend/src/views/admin/NewsletterSubscribers.vue`
```
┌──────────────────────────────────────────────────┐
│ Newsletter                       [Export CSV]    │
│                                                  │
│ [Tab: Subscribers] [Tab: Send History]           │
│                                                  │
│ ── Subscribers ──                                │
│ Search: [____________]  Source: [All ▼]          │
│                                                  │
│ ┌─────────────────────────────────────────────┐ │
│ │ Name        Email          WA       Source ⓘ│ │
│ │ Ali S.      ali@...        +62...   blog    │ │
│ │ Joe D.      joe@...        +1...    inline  │ │
│ │ ...                                          │ │
│ └─────────────────────────────────────────────┘ │
│ [< Prev] Page 1 of 12 [Next >]                   │
│                                                  │
│ ── Compose Digest ──                             │
│ [📧 Preview next Friday's digest]                │
│ [📤 Send test to my email]                       │
│ [🚀 Send NOW to all 247 subscribers] (confirm)   │
└──────────────────────────────────────────────────┘
```

Sidebar nav entry under Content section: "Newsletter".

**Public unsubscribe page** — `frontend/src/views/NewsletterUnsubscribe.vue`
- Route: `/newsletter/unsubscribe?token={token}`
- Reads token from URL, shows "Confirm unsubscribe" button
- POST `/newsletter/unsubscribe` with token → success state with "Resubscribe" link back to blog
- 404 / invalid token → polite error page

**Cleanup** — remove `handleSubscribe()` placeholder + dead `email`/`subscribeMessage`/`subscribeSuccess` state in `components/home/CTASection.vue` (lines 45-56). Template doesn't render the form anyway.

### Data flow (full subscribe → digest cycle)

```
User fills 3-field form → POST /api/newsletter/subscribe (throttle 5/60min)
  ↓
NewsletterController validates (name + email RFC+DNS + WA E.164 regex)
  ↓
Newsletter::create({name, email, whatsapp_number, source, consent_given_at, unsubscribe_token=auto})
  ↓
Frontend: localStorage marks subscribed → all 4 touchpoints hide on this device
  ↓
Friday 09:00 WIB → portfolio-scheduler crontab fires schedule:run
  ↓
Schedule::command('newsletter:send-weekly') → SendWeeklyNewsletter handle()
  ↓
Query 5 latest published posts in last 7d → if 0, skip + log
  ↓
Newsletter::chunk(100) → for each: Mail::to($subscriber)->queue(new WeeklyDigest($posts, $subscriber))
  ↓
portfolio-queue.service systemd worker picks jobs → Resend HTTP API
  ↓
Insert newsletter_sends row status=sent
  ↓
Subscriber clicks Unsubscribe link → /newsletter/unsubscribe?token={X}
  ↓
Frontend confirm → POST /api/newsletter/unsubscribe {token}
  ↓
Newsletter where token=X → delete() (soft? no, hard delete + log to newsletter_sends.notes? — DECISION: hard delete, GDPR right-to-erasure)
```

### Anti-patterns to avoid

- ❌ Don't add a `is_active` boolean and "deactivate" subscribers — hard-delete on unsubscribe satisfies GDPR right-to-erasure cleanly. Future: add explicit `Newsletter::onlyTrashed()` if business need arises.
- ❌ Don't track open/click via Resend webhook in v1 — adds infra without operator clearly needing it. Adopt later if subscriber count >500.
- ❌ Don't send digests one-at-a-time synchronously — always queue. Resend's rate limit is 100/sec but Mail::queue gives us free retry on transient failures via worker.
- ❌ Don't store WhatsApp numbers without `unique` constraint check — duplicate phone = same person spam.

### Testing strategy

**Backend (PHPUnit/Pest):**
- `NewsletterControllerTest` — validate all 3 required, WA regex, dedup by email+WA, throttle
- `SendWeeklyNewsletterTest` — covers: zero-posts skip, with-posts send, dry-run no-side-effects, --force when zero posts, chunk handling for >100 subs
- `NewsletterAdminControllerTest` — auth gate, list filter, CSV export shape, send-test isolation
- `WeeklyDigestMailTest` — Mailable renders without errors, contains all post titles, unsubscribe link has correct token
- `NewsletterUnsubscribeTokenTest` — invalid token 404, valid token deletes, token regenerated each subscribe

**Frontend (Vitest):**
- `useNewsletter.test.mjs` — extends existing `newsletterState.test.mjs`. New shape `subscribe({name, email, whatsappNumber, source})`.
- `WhatsAppValidation.test.mjs` — E.164 regex matches valid, rejects invalid

**Manual smoke test checklist:**
- [ ] Subscribe via Blog inline form → DB row has all 3 fields + token
- [ ] Subscribe again with same email → 409 DUPLICATE
- [ ] Subscribe with bad WA format → 422 validation error
- [ ] Run `php artisan newsletter:send-weekly --dry-run` → preview HTML printed, no sends
- [ ] Click admin "Send test to me" → email arrives, render OK in Gmail + Outlook
- [ ] Click unsubscribe link → frontend confirm page → DB row deleted
- [ ] After Friday cron fires (or manual `--force`), check `newsletter_sends` row inserted

### Operational considerations

**Resend setup:**
- Confirm `MAIL_MAILER=resend` + `RESEND_API_KEY=` in production .env
- Domain `alisadikinma.com` must be verified in Resend dashboard (SPF + DKIM DNS records)
- Default From: `newsletter@alisadikinma.com` (configurable via `NEWSLETTER_FROM_ADDRESS` env)
- Reply-To: `ali.sadikincom85@gmail.com` so replies land in operator's inbox

**Rate limit safety:**
- Resend free tier: 100 emails/day, 3000/month — paid tier needed once subscribers > 100
- Queue worker already throttles via `--sleep=3 --tries=3 --backoff=60,300,900` per CLAUDE.md systemd unit

**Telegram alert** on cron failure — reuse existing `DispatchTelegramNotification` job (per CLAUDE.md telegram settings group).

**Backfill plan for existing email-only rows:**
- Run `php artisan newsletter:backfill-tokens` after migration deploys (one-shot, idempotent — generates token for any row where token IS NULL)
- Existing rows with empty `name`/`whatsapp_number` flagged in admin UI with amber row + "Incomplete profile" badge — operator decides keep-or-delete

### Out of scope (deferred)

- Open/click tracking via Resend webhook
- Multi-language email templates (default English only; ID added if subscribers indicate locale)
- Subscriber segmentation (e.g., "tech-only" vs "all topics") — needs `categories[]` JSON column + UI
- WhatsApp broadcast to subscribers — separate skill, requires WA Business API integration
- Welcome email on subscribe (only weekly digest in v1)
- "Resend last week's digest" admin action

---

**Next step:** invoke `gaspol-plan` to produce step-by-step Implementation Plan (will be appended below this Design section as `## Implementation Plan`).
