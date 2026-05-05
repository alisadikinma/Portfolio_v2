# Newsletter System — Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

**Companion design doc:** [2026-05-05-newsletter-system.md](./2026-05-05-newsletter-system.md) — read it first.

## Goal

Ship a complete newsletter pipeline: lead capture (name + email + WhatsApp E.164) at 4 frontend touchpoints, admin panel for subscriber CRUD + send-now + send history, automated weekly digest cron (Friday 09:00 WIB, skip if zero new posts), branded Dark Cinema HTML email template via Resend, and token-based public unsubscribe. Operator gains a managed channel to surface fresh blog content to high-intent subscribers without manual effort.

## Architecture Context (from CLAUDE.md)

**Existing infrastructure to reuse — do NOT recreate:**
- `Newsletter` model + `newsletters` table (extend, don't recreate). Existing columns: `email`, `is_subscribed`, `subscribed_at`, `unsubscribed_at`. Existing `subscribe()`/`unsubscribe()` methods on model. `is_subscribed` flag is dead weight after this work — kept for backwards compat, never queried.
- `NewsletterController::subscribe/unsubscribe` at `backend/app/Http/Controllers/Api/NewsletterController.php` (widen, don't replace).
- `useNewsletter()` composable at `frontend/src/composables/useNewsletter.js` (widen signature).
- `newsletterState.js` localStorage helpers — keep as-is.
- 4 existing newsletter Vue components (already wired to composable):
  - `frontend/src/views/Blog.vue` lines 307-371 (inline section)
  - `frontend/src/components/blog/NewsletterInlineCard.vue`
  - `frontend/src/components/blog/NewsletterFloatingBanner.vue`
  - `frontend/src/components/blog/NewsletterFooterBar.vue`
- `App\Jobs\DispatchTelegramNotification` for cron failure alerts.
- `portfolio-queue.service` systemd worker — already running on VPS (CLAUDE.md "VPS Background Process Setup").
- `portfolio-scheduler.crontab` — already firing `php artisan schedule:run` per minute on VPS.
- Resend in package stack (`resend/resend-laravel` per CLAUDE.md packages list).
- Admin sidebar `frontend/src/layouts/AdminLayout.vue` — pattern between LinkedIn (lines 106-134) and Contact (156) for new Newsletter entry.
- `frontend/src/views/admin/ContactsList.vue` — closest analog for `NewsletterSubscribers.vue` (Pinia store + table + delete-confirm modal + CSV export pattern).
- `frontend/src/composables/useLinkedInDrafts.js` — TanStack Query pattern for new `useNewsletterAdmin.js`.
- `backend/resources/views/emails/contact-notification.blade.php` — reference for Blade email pattern.

**TanStack Query is the documented caching strategy** (CLAUDE.md "Performance & Caching"). New admin view uses TanStack, not Pinia (newer pattern matching LinkedIn admin views shipped Apr-May 2026).

**Hard delete on unsubscribe** is the chosen path per design doc anti-patterns section (GDPR right-to-erasure). Existing `is_subscribed=false` soft-pause is dead code.

## Tech Stack

- Backend: Laravel 12, PHP 8.2, MySQL 8, Sanctum 4
- Mail: `resend/resend-laravel` (driver: `MAIL_MAILER=resend`)
- Queue: `database` driver via systemd `portfolio-queue.service`
- Schedule: Laravel Scheduler via host crontab `* * * * * php artisan schedule:run`
- Frontend: Vue 3.5 (`<script setup>`), Tailwind 4, TanStack Query 5.90, Pinia 3, Vue Router 4.5, Axios
- Test: PHPUnit (project convention, NOT Pest), Vitest for `.test.mjs` files

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Subscribe form (3 fields) | `POST /api/newsletter/subscribe` | `useNewsletter().subscribe({name,email,whatsappNumber,source})` | Partial | Widen existing endpoint + composable signature |
| Subscriber list (admin) | `GET /api/admin/newsletter` | `useNewsletterAdmin().useSubscribers()` (TanStack) | No | Create new controller + composable |
| Subscriber CSV export | `GET /api/admin/newsletter/export` | `useNewsletterAdmin().exportCsv()` | No | Stream CSV from controller |
| Subscriber delete | `DELETE /api/admin/newsletter/{id}` | `useNewsletterAdmin().useDeleteSubscriber()` | No | Standard mutation |
| Digest preview HTML | `GET /api/admin/newsletter/digest-preview` | `useNewsletterAdmin().usePreview()` | No | Renders `WeeklyDigest` Mailable HTML to string |
| Send test email | `POST /api/admin/newsletter/send-test` | `useNewsletterAdmin().useSendTest()` | No | Sends Mailable to `auth()->user()->email` or specified |
| Send now (manual) | `POST /api/admin/newsletter/send-now` | `useNewsletterAdmin().useSendNow()` | No | Triggers `SendWeeklyNewsletter::handle(triggeredBy='manual')` |
| Send history list | `GET /api/admin/newsletter/sends` | `useNewsletterAdmin().useSendHistory()` | No | Paginated `newsletter_sends` rows |
| Weekly cron | `Schedule::command('newsletter:send-weekly')->fridays()->at('09:00')` | n/a (Laravel Scheduler) | No | New schedule entry in `routes/console.php` |
| Email render | `App\Mail\WeeklyDigest` Mailable + `weekly-digest.blade.php` | `Mail::to($sub)->queue(new WeeklyDigest(...))` | No | New Mailable + Blade template + text fallback |
| Posts to feature | `Post::published()->whereBetween('published_at', [now()->subWeek(), now()])->with(['category','translations'])->limit(5)->get()` | Existing `Post` model + `published` scope | Yes | Use existing scope from `app/Models/Post.php` |
| Telegram failure alert | `DispatchTelegramNotification::dispatch('newsletter_cron_failed', ...)` | Existing job | Yes | Reuse from `app/Jobs/DispatchTelegramNotification.php` |
| Public unsubscribe page | Vue route `/newsletter/unsubscribe?token=X` | New view + new endpoint | No | New `NewsletterUnsubscribe.vue` + extend `NewsletterController` to accept `{token}` |
| Admin sidebar entry | `frontend/src/layouts/AdminLayout.vue` | n/a (template edit) | Yes | Insert between LinkedIn section + Contact link |

**Anti-placeholder contract:** Every "No" row above MUST be implemented as real, working code during execute. If any blocked (e.g., Resend API key not configured in dev), STOP and ask operator before substituting `Mail::fake()` outside test contexts.

---

## Phases (in dependency order)

Tracks: **B** = backend, **F** = frontend, **Z** = final integration. Backend B1-B4 are prerequisites for everything else. F1-F2 can run in parallel with B5-B8 once B4 ships.

---

### Phase B1: Migration — add lead fields to `newsletters`

**Estimated time:** 4 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_05_000001_add_lead_fields_to_newsletters.php`
- Test: `backend/tests/Feature/Newsletter/AddLeadFieldsMigrationTest.php`

**Steps:**
1. Write failing test for migration: assert `name`, `whatsapp_number`, `unsubscribe_token`, `consent_given_at`, `source` columns exist on `newsletters` after migration runs. Expected error: `Failed asserting that table 'newsletters' has column 'name'`.
2. Run test, confirm fail.
3. Implement migration with `Schema::table('newsletters', fn(Blueprint $t) => $t->string('name', 120)->default('')->after('email'); ...)`. Add unique index on `unsubscribe_token`. Add unique index on `whatsapp_number` (defense vs duplicate-phone spam).
4. Run `php artisan migrate` (dev) then test, confirm pass.
5. Commit: `feat(newsletter): add lead fields migration (name, whatsapp, unsubscribe_token)`

**Verification:**
- [ ] `php artisan migrate:fresh` runs cleanly with no error
- [ ] `DESCRIBE newsletters` shows new columns + indexes
- [ ] No placeholder/TODO comments
- [ ] Test passes

---

### Phase B2: Migration — `newsletter_sends` history table

**Estimated time:** 4 minutes

**Files:**
- Create: `backend/database/migrations/2026_05_05_000002_create_newsletter_sends_table.php`
- Test: `backend/tests/Feature/Newsletter/CreateNewsletterSendsMigrationTest.php`

**Steps:**
1. Write failing test asserting `newsletter_sends` table exists with columns `sent_at`, `subscriber_count`, `posts_count`, `post_ids` (JSON), `status` (enum), `error_message`, `triggered_by` (enum), `created_by_user_id`, `test_recipient`, `duration_seconds`. Expected error: `Failed asserting that table 'newsletter_sends' exists`.
2. Run test, confirm fail.
3. Implement migration. `status` enum: `'sent','failed','skipped','partial'`. `triggered_by` enum: `'cron','manual','test'`. `created_by_user_id` nullable FK to users (nullOnDelete). Index on `sent_at`.
4. Run migration + test, confirm pass.
5. Commit: `feat(newsletter): create newsletter_sends audit log table`

**Verification:**
- [ ] Migration runs cleanly
- [ ] Indexes present
- [ ] FK cascade rule correct (nullOnDelete)
- [ ] Test passes

---

### Phase B3: Newsletter model — new fillables + token auto-generate

**Estimated time:** 5 minutes

**Files:**
- Modify: `backend/app/Models/Newsletter.php`
- Create: `backend/database/factories/NewsletterFactory.php` (if missing)
- Create: `backend/tests/Unit/Newsletter/NewsletterModelTest.php`

**Steps:**
1. Write failing test: create `Newsletter` with name+email+whatsapp_number → assert `unsubscribe_token` auto-populated as 32-char string. Expected error: `Failed asserting that '' has length 32`.
2. Run test, confirm fail.
3. Add `name`, `whatsapp_number`, `unsubscribe_token`, `consent_given_at`, `source` to `$fillable`. Add `consent_given_at` to `$casts` as `'datetime'`. Override `boot()` with `static::creating(fn($m) => $m->unsubscribe_token ??= Str::random(32))`.
4. Update `NewsletterFactory::definition()` to provide all 3 new fields with realistic fakes (`fake()->name()`, `fake()->safeEmail()`, `'+62' . fake()->numerify('##########')`).
5. Run test, confirm pass.
6. Commit: `feat(newsletter): widen Newsletter model fillables + auto-generate unsubscribe_token`

**Verification:**
- [ ] Token always 32 chars on fresh create
- [ ] Token is unique (DB constraint enforces)
- [ ] Existing `is_subscribed`/`subscribed_at`/`unsubscribed_at` columns unchanged (backwards compat preserved)
- [ ] Factory generates valid E.164 WA numbers
- [ ] Test passes

---

### Phase B4: NewsletterController — widen subscribe + token unsubscribe

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/NewsletterController.php`
- Create: `backend/tests/Feature/Newsletter/SubscribeWithLeadFieldsTest.php`
- Create: `backend/tests/Feature/Newsletter/UnsubscribeByTokenTest.php`

**Steps:**
1. Write failing test for `POST /api/newsletter/subscribe` with valid payload `{name:'Ali',email:'ali@test.com',whatsapp_number:'+628123456789',source:'blog_inline'}` → assert 201 + DB row has all 4 fields. Expected error: `Validator failed: name field is required` (current controller doesn't accept name).
2. Run test, confirm fail.
3. Update `subscribe()`: change validator rules to:
   ```php
   'name' => ['required', 'string', 'min:2', 'max:120'],
   'email' => ['required', 'email:rfc,dns', 'max:255'],
   'whatsapp_number' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
   'source' => ['nullable', 'string', 'in:blog_inline,inline_card,floating_banner,footer_bar'],
   ```
4. Dedup check: `Newsletter::where('email', $request->email)->orWhere('whatsapp_number', $request->whatsapp_number)->exists()` → return 409 DUPLICATE if either matches.
5. `Newsletter::create(['name'=>..., 'email'=>..., 'whatsapp_number'=>..., 'source'=>..., 'consent_given_at'=>now()])` (token auto-injected by model).
6. Write failing test for `POST /api/newsletter/unsubscribe-by-token {token:'X'}` → assert row deleted + 200. Expected error: `404 Not Found` (route doesn't exist).
7. Add new route `Route::post('/newsletter/unsubscribe-by-token', [NewsletterController::class, 'unsubscribeByToken'])` to `routes/api.php`. Implement controller method: validate `'token' => ['required','string','size:32']`, find by token, hard delete, return 200. 404 on invalid token.
8. Keep existing `DELETE /newsletter/unsubscribe` (by email) untouched for backwards compat.
9. Run all newsletter tests, confirm pass.
10. Commit: `feat(newsletter): widen subscribe with lead fields + token-based unsubscribe`

**Verification:**
- [ ] Subscribe with valid 3 fields → 201 + row created with token
- [ ] Subscribe with bad WA format → 422 validation error
- [ ] Subscribe with duplicate email → 409 DUPLICATE
- [ ] Subscribe with duplicate WA → 409 DUPLICATE
- [ ] Unsubscribe with valid token → 200 + row deleted
- [ ] Unsubscribe with invalid token → 404
- [ ] Throttle middleware (5/60min) still applied
- [ ] All tests pass

---

### Phase B5a: WeeklyDigest Mailable class

**Estimated time:** 4 minutes

**Files:**
- Create: `backend/app/Mail/WeeklyDigest.php`
- Create: `backend/tests/Unit/Mail/WeeklyDigestTest.php`

**Steps:**
1. Write failing test: instantiate `new WeeklyDigest(collect([$post1,$post2]), $subscriber)`, call `->build()` (or `->envelope()` for Laravel 11+). Assert subject contains "Friday Digest" + post count. Expected error: `Class App\Mail\WeeklyDigest not found`.
2. Run test, confirm fail.
3. Implement Mailable: `php artisan make:mail WeeklyDigest --markdown=emails.weekly-digest`. Then edit:
   - `implements ShouldQueue, use Queueable, SerializesModels`
   - Constructor: `public Collection $posts, public Newsletter $subscriber`
   - `envelope()`: subject = `"Friday Digest · " . $this->posts->count() . " reads from this week"`, from + replyTo from config
   - `content()`: `view: 'emails.weekly-digest'`, `text: 'emails.weekly-digest-text'`, `with: ['posts' => $posts, 'subscriber' => $subscriber, 'campaign' => 'weekly-' . now()->format('Y-W')]`
4. Run test, confirm pass.
5. Commit: `feat(newsletter): add WeeklyDigest Mailable class`

**Verification:**
- [ ] Mailable instantiable with posts + subscriber
- [ ] Subject string format correct
- [ ] Implements `ShouldQueue` (queueable)
- [ ] Test passes

---

### Phase B5b: Email Blade template — Dark Cinema HTML

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/resources/views/emails/weekly-digest.blade.php`
- Create: `backend/resources/views/emails/weekly-digest-text.blade.php`
- Modify: `backend/tests/Unit/Mail/WeeklyDigestTest.php` (add render assertions)

**Steps:**
1. Write failing test: render Mailable to string via `$mailable->render()`, assert contains: subscriber name, every post title, unsubscribe link with token, UTM `?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-`. Expected error: `View [emails.weekly-digest] not found`.
2. Run test, confirm fail.
3. Implement HTML template — table-based 600px max-width, inline CSS only, no `<style>` block. Structure (per design doc ASCII):
   - Outer wrapper: bg `#050506`, padding 32px
   - Inner container: `<table>` width 600 align center, bg `#0C0C0F`
   - Header row: brand mark + "FRIDAY DIGEST" eyebrow uppercase mono `#D4A843`
   - Greeting: `Hi {{ $subscriber->name }},` color `#EDEDEF` 18px
   - Intro line: `"Here's what landed this week —"` color `#8A8F98` 14px
   - For each `$post`: card with `<img src="{{ $post->featured_image }}" width="544" alt="{{ $post->translations->first()->title }}">`, category eyebrow gold, title 22px white bold, excerpt 14px muted, gold "Read this essay →" button linking to `https://alisadikinma.com/blog/{{ $post->slug }}?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-{{ now()->format('Y-W') }}`
   - Footer divider line
   - Personal touch: `"Reply to this email — I read every one."`
   - Sign-off: `Ali Sadikin / alisadikinma.com`
   - Outer footer: `Unsubscribe` link to `https://alisadikinma.com/newsletter/unsubscribe?token={{ $subscriber->unsubscribe_token }}` + LinkedIn + Portfolio links
4. Implement text fallback (`weekly-digest-text.blade.php`): plain-text version, post titles + URLs only, unsubscribe URL at bottom.
5. Run test, confirm pass.
6. Manual visual smoke: `php artisan tinker` → `Mail::to('test@example.com')->send(new App\Mail\WeeklyDigest(\App\Models\Post::published()->limit(3)->get(), \App\Models\Newsletter::factory()->make()))` → check `storage/logs/laravel.log` (since dev `MAIL_MAILER=log`) for rendered HTML.
7. Commit: `feat(newsletter): WeeklyDigest HTML + text Blade templates`

**Verification:**
- [ ] HTML renders without errors
- [ ] All inline CSS — no `<style>` block (Resend-safe)
- [ ] Max-width 600px confirmed visually in browser preview
- [ ] Unsubscribe link contains real token from `$subscriber`
- [ ] UTM params present on every blog link
- [ ] Text fallback renders cleanly (no HTML tags leak)
- [ ] Test passes

---

### Phase B6: SendWeeklyNewsletter command + schedule entry

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/app/Console/Commands/SendWeeklyNewsletter.php`
- Modify: `backend/routes/console.php`
- Create: `backend/tests/Feature/Newsletter/SendWeeklyNewsletterCommandTest.php`

**Steps:**
1. Write failing test: seed 3 published posts in last 7 days + 5 subscribers, run `Artisan::call('newsletter:send-weekly')`. Assert `Mail::queue` called 5 times with `WeeklyDigest`, `newsletter_sends` row inserted with `status='sent', subscriber_count=5, posts_count=3`. Expected error: `Command newsletter:send-weekly not defined`.
2. Run test, confirm fail.
3. Implement `SendWeeklyNewsletter`:
   - Signature: `newsletter:send-weekly {--dry-run} {--force} {--limit=}`
   - Use `Mail::fake()` aware: in tests, `Mail::queue` is mockable; in prod, queues to systemd worker.
   - Step 1: query posts (`Post::published()->whereBetween('published_at', [now()->subWeek(), now()])->with(['category','translations'])->orderBy('published_at','desc')->limit($this->option('limit') ?? 5)->get()`)
   - Step 2: if empty AND NOT `--force`: insert `NewsletterSend{status:'skipped', subscriber_count:0, posts_count:0, triggered_by:'cron'}`, log info, return 0.
   - Step 3: if `--dry-run`: render first subscriber's mailable to stdout, return 0 without insert.
   - Step 4: `Newsletter::chunk(100, function($subs) use ($posts) { foreach($subs as $sub) Mail::to($sub->email)->queue(new WeeklyDigest($posts, $sub)); })`.
   - Step 5: insert `NewsletterSend{status:'sent', subscriber_count:Newsletter::count(), posts_count:$posts->count(), post_ids:$posts->pluck('id'), triggered_by:'cron', duration_seconds:elapsed}`.
4. Add schedule entry to `routes/console.php`:
   ```php
   // Newsletter: weekly digest every Friday 09:00 WIB.
   // Skip-if-empty handled inside command (logs to newsletter_sends.status='skipped').
   // Reuses systemd portfolio-queue.service for actual sends + portfolio-scheduler crontab for trigger.
   Schedule::command('newsletter:send-weekly')
       ->fridays()
       ->at('09:00')
       ->timezone('Asia/Jakarta')
       ->withoutOverlapping(60);
   ```
5. Add another test for `--dry-run`: assert `Mail::queue` NOT called, no `newsletter_sends` row inserted.
6. Add test for zero-posts-no-force path: assert skipped row inserted, no Mail calls.
7. Run all command tests, confirm pass.
8. Commit: `feat(newsletter): SendWeeklyNewsletter command + Friday 09:00 WIB schedule`

**Verification:**
- [ ] `php artisan newsletter:send-weekly --dry-run` runs without sending
- [ ] `php artisan schedule:list` shows new entry with Friday 09:00 Asia/Jakarta
- [ ] Skipped path inserts audit row
- [ ] Manual force path: `--force` sends even with 0 posts (uses fallback empty array — note in command help text)
- [ ] All tests pass

---

### Phase B7: Backfill command for legacy email-only rows

**Estimated time:** 4 minutes

**Files:**
- Create: `backend/app/Console/Commands/BackfillNewsletterTokens.php`
- Create: `backend/tests/Feature/Newsletter/BackfillNewsletterTokensTest.php`

**Steps:**
1. Write failing test: seed 3 newsletter rows directly via DB (bypassing model creating hook) with `unsubscribe_token=null`, run `Artisan::call('newsletter:backfill-tokens')`, assert all 3 rows now have non-null 32-char tokens. Expected error: `Command newsletter:backfill-tokens not defined`.
2. Run test, confirm fail.
3. Implement command: `Newsletter::whereNull('unsubscribe_token')->chunkById(500, fn($subs) => $subs->each(fn($s) => $s->update(['unsubscribe_token' => Str::random(32)])))`. Idempotent.
4. Run test, confirm pass.
5. Commit: `feat(newsletter): one-shot backfill command for legacy unsubscribe tokens`

**Verification:**
- [ ] Re-running command on already-tokenized DB is no-op (idempotent)
- [ ] Test passes

---

### Phase B8: NewsletterAdminController — 8 endpoints

**Estimated time:** 18 minutes (split mentally into 8 sub-steps)

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/NewsletterAdminController.php`
- Create: `backend/app/Models/NewsletterSend.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/Newsletter/Admin/NewsletterAdminListTest.php`
- Create: `backend/tests/Feature/Newsletter/Admin/NewsletterAdminExportTest.php`
- Create: `backend/tests/Feature/Newsletter/Admin/NewsletterAdminSendTest.php`

**Steps:**
1. Write failing test: as auth user, `GET /api/admin/newsletter` → expects 200 + JSON with `data` (subscribers paginated), `meta`. Expected error: route not defined.
2. Run test, confirm fail.
3. Create `NewsletterSend` model (`$fillable`, `$casts['post_ids' => 'array']`, BelongsTo `createdBy(User)`).
4. Add 8 admin routes inside `Route::middleware('auth:sanctum')->prefix('admin')->group(function () { ... })` block in `routes/api.php`:
   ```php
   Route::get('/newsletter', [NewsletterAdminController::class, 'index']);
   Route::delete('/newsletter/{id}', [NewsletterAdminController::class, 'destroy']);
   Route::get('/newsletter/export', [NewsletterAdminController::class, 'export']);
   Route::get('/newsletter/digest-preview', [NewsletterAdminController::class, 'preview']);
   Route::post('/newsletter/send-test', [NewsletterAdminController::class, 'sendTest']);
   Route::post('/newsletter/send-now', [NewsletterAdminController::class, 'sendNow']);
   Route::get('/newsletter/sends', [NewsletterAdminController::class, 'sends']);
   Route::get('/newsletter/sends/{id}', [NewsletterAdminController::class, 'showSend']);
   ```
5. Implement `index()`: paginated `Newsletter::query()` with optional filters `?search=` (LIKE on name + email), `?source=`, `?per_page=` (max 100). Standard `data` + `meta` shape.
6. Implement `destroy($id)`: `Newsletter::findOrFail($id)->delete()`. Return `{success:true}`.
7. Implement `export()`: stream CSV via `response()->streamDownload(function() {...}, 'newsletter-subscribers-' . now()->format('Y-m-d') . '.csv')`. Columns: name, email, whatsapp_number, source, created_at.
8. Implement `preview()`: same query as `SendWeeklyNewsletter` for posts in last 7 days, render `WeeklyDigest` with a fake throwaway `Newsletter::factory()->make()` subscriber (so token shown is dummy). Return rendered HTML as string under `{html: '...', posts_count: N, subscriber_count: $live_count}`.
9. Implement `sendTest()`: validate `{recipient: 'optional|email'}`. If no recipient, use `auth()->user()->email`. Send `WeeklyDigest` synchronously (not queued — operator wants immediate feedback). Insert `newsletter_sends` row `triggered_by='test', test_recipient=$email`.
10. Implement `sendNow()`: dispatch the same logic as cron but `triggered_by='manual', created_by_user_id=auth()->id()`. Should NOT block — call `SendWeeklyNewsletter` programmatically: `Artisan::queue('newsletter:send-weekly')` to push to queue. Return 202 Accepted.
11. Implement `sends()`: paginated `NewsletterSend::orderByDesc('sent_at')` with optional `?status=` filter.
12. Implement `showSend($id)`: `NewsletterSend::with('createdBy')->findOrFail($id)`. Resolve `post_ids[]` to titles via `Post::whereIn('id', $row->post_ids)->with('translations')->get()`. Return resource.
13. Write 3 test files (list/export/send) covering happy path + auth gate + validation. Each uses `RefreshDatabase` + `actingAs($user, 'sanctum')`.
14. Run all admin tests, confirm pass.
15. Commit: `feat(newsletter): NewsletterAdminController with 8 endpoints (CRUD + export + preview + send)`

**Verification:**
- [ ] All 8 routes appear in `php artisan route:list --path=admin/newsletter`
- [ ] Unauth request → 401
- [ ] Auth user can list, export CSV, delete, preview
- [ ] `send-test` actually triggers Resend send (verify via `storage/logs/laravel.log` in dev)
- [ ] `send-now` returns 202, queues the job (`jobs` table grows by 1)
- [ ] CSV export has correct headers + at least 1 data row
- [ ] All tests pass

---

### Phase F1: Widen `useNewsletter()` composable signature

**Estimated time:** 4 minutes

**Files:**
- Modify: `frontend/src/composables/useNewsletter.js`
- Create: `frontend/src/composables/useNewsletter.test.mjs`

**Steps:**
1. Write failing Vitest test: `const {subscribe} = useNewsletter(); await subscribe({name:'X',email:'x@y.com',whatsappNumber:'+62812',source:'inline_card'})` — assert axios POST called with shape `{name, email, whatsapp_number, source}` (snake_case body). Expected error: `axios called with {email}` (current 1-arg behavior).
2. Run test, confirm fail.
3. Update `subscribe(payload)`: accept object `{name, email, whatsappNumber, source}`. Send POST body `{name, email, whatsapp_number: payload.whatsappNumber, source: payload.source}`. Backwards compat: if `typeof payload === 'string'`, treat as `email` (warn dev with `console.warn('[useNewsletter] subscribe(email) deprecated; pass {name,email,whatsappNumber,source}')`).
4. Markdown success/duplicate as before.
5. Run test, confirm pass.
6. Commit: `feat(newsletter): widen useNewsletter().subscribe signature to accept lead fields`

**Verification:**
- [ ] Object-form call works
- [ ] Legacy string-form call works with deprecation warning
- [ ] `markSubscribed()` still called on success (touchpoints still hide on this device)
- [ ] Test passes

---

### Phase F2: Redesign 4 touchpoint forms — 3 fields each

**Estimated time:** 16 minutes (4 components × ~4 min each)

**Files:**
- Modify: `frontend/src/components/blog/NewsletterInlineCard.vue`
- Modify: `frontend/src/views/Blog.vue` (lines 307-371 inline section)
- Modify: `frontend/src/components/blog/NewsletterFloatingBanner.vue`
- Modify: `frontend/src/components/blog/NewsletterFooterBar.vue` (special — see Phase F3 for modal redesign)

**Steps for each component:**
1. Write failing Vitest test: mount component, fill name + email + WA inputs, submit form, assert `useNewsletter().subscribe` called with object form. Expected error: `subscribe called with email only` (current behavior).
2. Run test, confirm fail.
3. Add 2 new `<input>` elements above/below existing email field:
   - Name: `<input v-model="name" type="text" required minlength="2" maxlength="120" placeholder="Your name">`
   - WhatsApp: `<input v-model="whatsappNumber" type="tel" required pattern="^\+[1-9]\d{6,14}$" placeholder="+628123456789">` + small help text below: `Format internasional, mulai dengan +`
4. Update `handleSubmit()` to pass `{name: name.value, email: email.value, whatsappNumber: whatsappNumber.value, source: '<component_name>'}`. Source values: `'inline_card'`, `'blog_inline'`, `'floating_banner'`, `'footer_bar'`.
5. Add client-side regex check for WA on blur — sets local `errorMsg` if invalid, prevents submit.
6. Reset all 3 fields on success.
7. Run test, confirm pass.
8. Commit per component: `feat(newsletter): NewsletterInlineCard accepts name + WhatsApp fields` (then InlineCard, FloatingBanner, Blog.vue inline, FooterBar).

**Verification per component:**
- [ ] All 3 fields visible + required
- [ ] WA input rejects bad format on blur (red border + error text)
- [ ] Submit calls composable with correct object shape + correct source label
- [ ] Existing dismiss flow unchanged (banner/footer-bar still close on dismiss)
- [ ] Existing success/duplicate/error states still render
- [ ] Layout stays clean on mobile (vertical stack, no overflow)
- [ ] Tests pass

---

### Phase F3: Footer bar — modal-on-click pattern (UX redesign)

**Estimated time:** 8 minutes

**Files:**
- Modify: `frontend/src/components/blog/NewsletterFooterBar.vue`
- Create: `frontend/src/components/blog/NewsletterModal.vue` (extracted shared 3-field form)

**Steps:**
1. Write failing test: mount FooterBar, click "Subscribe →" button, assert modal opens with 3-field form. Expected error: `Modal not in DOM`.
2. Run test, confirm fail.
3. Extract 3-field form from `NewsletterInlineCard.vue` into shared `NewsletterModal.vue` (Teleport-to-body, dismiss-on-backdrop-click, ESC-to-close, focus-trap on first input). Props: `:show`, `:source`. Emits: `dismiss`, `subscribed`.
4. Refactor `NewsletterFooterBar.vue` template: bar shows `"Liked what you read? Get a new essay every Friday."` + `[Subscribe →]` button + `[×]` dismiss. Remove inline email input. Click on Subscribe button toggles `showModal=true` → `<NewsletterModal :show="showModal" source="footer_bar" @dismiss="..." @subscribed="..."/>`.
5. On `@subscribed`, `markSubscribed` triggers, fold the bar with success state ("You're in!") for 2.5s before dismiss.
6. Refactor `NewsletterInlineCard.vue` to use the same `NewsletterModal` component if F2 hasn't already inlined the form (skip if already done).
7. Run test, confirm pass.
8. Commit: `feat(newsletter): footer bar uses modal pattern for 3-field form`

**Verification:**
- [ ] Footer bar still appears on Blog list / blog detail (existing show logic preserved)
- [ ] Clicking Subscribe opens modal
- [ ] ESC closes modal
- [ ] Backdrop click closes modal
- [ ] Submit inside modal triggers subscribe + closes modal + folds bar to success state
- [ ] `markSubscribed` still hides all 4 touchpoints on subsequent loads
- [ ] Test passes

---

### Phase F4: Public unsubscribe page

**Estimated time:** 6 minutes

**Files:**
- Create: `frontend/src/views/NewsletterUnsubscribe.vue`
- Modify: `frontend/src/router/index.js` (add route)
- Modify: `frontend/src/composables/useNewsletter.js` (add `unsubscribeByToken(token)` method)

**Steps:**
1. Write failing component test: mount with `?token=abcd1234` query, assert "Confirm unsubscribe" button visible. Click → assert `axios.post('/newsletter/unsubscribe-by-token', {token})` called. Expected error: route/component doesn't exist.
2. Run test, confirm fail.
3. Add public route to `router/index.js`:
   ```js
   { path: '/newsletter/unsubscribe', name: 'newsletter-unsubscribe',
     component: () => import('@/views/NewsletterUnsubscribe.vue'),
     meta: { title: 'Unsubscribe — Ali Sadikin', requiresAuth: false } }
   ```
4. Implement view: read `route.query.token`, three states: idle (Confirm button), success ("You've been unsubscribed. Re-subscribe →" link to /blog), error ("Invalid or expired link"). Match Dark Cinema theme — bg-bg-deep, gold CTA.
5. Add `unsubscribeByToken(token)` to `useNewsletter.js` composable: `await api.post('/newsletter/unsubscribe-by-token', {token})`, returns `{success, message}`.
6. Run test, confirm pass.
7. Commit: `feat(newsletter): public unsubscribe page with token-based confirm flow`

**Verification:**
- [ ] Visit `/newsletter/unsubscribe?token=X` → renders confirm UI
- [ ] Confirm click hits backend, success state shown
- [ ] Invalid token shows error state (no PHP errors leaked)
- [ ] No auth required (public route)
- [ ] `clearNewsletterState()` called on success (re-enables subscribe forms on this device)
- [ ] Test passes

---

### Phase F5: Admin view — `NewsletterSubscribers.vue` with TanStack composable

**Estimated time:** 18 minutes

**Files:**
- Create: `frontend/src/composables/useNewsletterAdmin.js`
- Create: `frontend/src/views/admin/NewsletterSubscribers.vue`
- Modify: `frontend/src/router/index.js` (add admin route)

**Steps:**
1. Write failing component test: mount with mocked TanStack returning 3 subscribers, assert table renders 3 rows with name + email + WA + source columns. Expected error: component doesn't exist.
2. Run test, confirm fail.
3. Implement `useNewsletterAdmin.js` composable using TanStack Query 5.90 pattern (mirror `useLinkedInDrafts.js` shape):
   - `useSubscribers(filters)` — `useQuery({queryKey: ['newsletter','subscribers', filters], queryFn: () => api.get('/admin/newsletter', {params:filters}).then(r=>r.data)})`
   - `useDeleteSubscriber()` — `useMutation({mutationFn: id => api.delete('/admin/newsletter/'+id), onSuccess: () => qc.invalidateQueries({queryKey:['newsletter','subscribers']})})`
   - `useDigestPreview()` — `useQuery({queryKey:['newsletter','preview'], queryFn: () => api.get('/admin/newsletter/digest-preview').then(r=>r.data), staleTime: 60_000})`
   - `useSendTest()` — `useMutation({mutationFn: ({recipient}) => api.post('/admin/newsletter/send-test', {recipient})})`
   - `useSendNow()` — `useMutation({mutationFn: () => api.post('/admin/newsletter/send-now')})`
   - `useSendHistory(filters)` — `useQuery(...)`
   - `exportCsv()` — direct download via `window.open(api.defaults.baseURL+'/admin/newsletter/export?token=...')` OR axios blob download.
4. Implement `NewsletterSubscribers.vue` (template structure per design doc ASCII):
   - Header with "Newsletter" title + Export CSV button
   - Tab switcher: Subscribers / Send History
   - Subscribers tab: search input + source filter dropdown + paginated table (Name, Email, WhatsApp, Source, Subscribed at, Actions: Delete)
   - Compose Digest panel (always visible at bottom of Subscribers tab):
     - Preview button → opens modal with rendered email HTML inside iframe
     - "Send test to my email" button → text input + Send button → toast on success
     - "Send NOW to all N subscribers" button → opens confirm modal with checkbox "I confirm I want to send to all N people right now" → calls `useSendNow()`
   - Send History tab: paginated table with columns: Sent at, Status (color chip), Subscriber count, Posts count, Triggered by (cron/manual/test), Created by user
5. Add admin route:
   ```js
   { path: '/admin/newsletter', name: 'admin-newsletter',
     component: () => import('@/views/admin/NewsletterSubscribers.vue'),
     meta: { title: 'Newsletter — Admin', requiresAuth: true, layout: 'admin' } }
   ```
6. Style with same patterns as `ContactsList.vue` (light/dark neutral-X classes for consistency with current admin shell).
7. Run test, confirm pass.
8. Commit: `feat(newsletter): admin NewsletterSubscribers view with TanStack composable`

**Verification:**
- [ ] Visit `/admin/newsletter` (logged in) → renders empty state OR table
- [ ] Search filters live (debounced 300ms)
- [ ] Source dropdown filters correctly
- [ ] Delete shows confirm modal then mutates + invalidates query
- [ ] Export CSV downloads correctly named file
- [ ] Preview modal renders HTML email inside iframe
- [ ] Send-test toast confirms delivery
- [ ] Send-now requires explicit checkbox confirm
- [ ] Send History tab shows past runs with status chips
- [ ] All tests pass

---

### Phase F6: Admin sidebar nav entry

**Estimated time:** 3 minutes

**Files:**
- Modify: `frontend/src/layouts/AdminLayout.vue`

**Steps:**
1. No new test — visual sidebar nav. Existing AdminLayout already uses `<router-link>` pattern.
2. Insert a new `<router-link to="/admin/newsletter">` block between LinkedIn section (closes line 134) and Contact section (line 156). Use envelope/inbox SVG icon. Label: "Newsletter".
3. Manual smoke: log in to admin, hover sidebar, confirm Newsletter link visible + clicking navigates to `/admin/newsletter`.
4. Commit: `feat(newsletter): admin sidebar entry between LinkedIn and Contact`

**Verification:**
- [ ] Sidebar shows new entry
- [ ] Active state highlights when on `/admin/newsletter`
- [ ] Mobile sidebar collapses correctly (existing pattern preserved)

---

### Phase F7: Cleanup — remove dead `handleSubscribe` placeholder

**Estimated time:** 2 minutes

**Files:**
- Modify: `frontend/src/components/home/CTASection.vue`

**Steps:**
1. No new test — pure cleanup of dead code.
2. Delete lines 45-56 in `CTASection.vue`:
   - Remove `email`, `subscribeMessage`, `subscribeSuccess` refs
   - Remove `handleSubscribe()` function
   - The template doesn't render the form, so no template change needed
3. Verify file still imports `ref, onMounted, onUnmounted` (used for `isVisible` + observer).
4. Manual smoke: visit Home page, scroll to "Let's Build Something Amazing" section, confirm WhatsApp + Get in Touch buttons still work (regression check).
5. Commit: `chore(home): remove dead newsletter placeholder from CTASection`

**Verification:**
- [ ] Home page CTA section still renders correctly
- [ ] No console errors
- [ ] WhatsApp + Get in Touch buttons functional
- [ ] No remaining references to `handleSubscribe` in repo (`Grep` confirms zero hits)

---

### Phase Z1: End-to-end smoke test + production readiness

**Estimated time:** 10 minutes

**Files:**
- Create: `docs/runbooks/newsletter-deploy.md` (operator runbook)

**Steps:**

**Backend smoke (XAMPP local):**
1. `php artisan migrate:fresh --seed` (full clean state)
2. `php artisan db:seed --class=NewsletterSeeder` (if you add one for testing — optional, can use factory in tinker)
3. Subscribe via curl with valid 3-field payload → expect 201 + DB row.
4. Subscribe duplicate → expect 409.
5. Subscribe bad WA → expect 422.
6. `php artisan newsletter:send-weekly --dry-run` → confirms preview HTML output.
7. `php artisan newsletter:send-weekly --force` (when 0 posts in last 7d) → confirms send dispatches + `newsletter_sends` row inserted.
8. `php artisan schedule:list` → confirms Friday 09:00 Asia/Jakarta entry visible.
9. Visit `/admin/newsletter` (logged in) → confirm UI loads, list paginates, send-test works.
10. Click public unsubscribe link from one of the test emails → confirm row deleted.

**Frontend smoke:**
11. Visit `/blog` (logged out) → fill 3-field form on inline section → submit → expect "You're in!" → verify DB row.
12. Reload `/blog` → confirm all 4 touchpoints hidden (localStorage marks subscribed).
13. Manually clear `nl_subscribed_email` from devtools → confirm forms reappear.
14. Visit `/blog/{any-slug}` → scroll → confirm `NewsletterInlineCard` renders with 3 fields.
15. Visit `/blog/{slug}` again → wait for `NewsletterFloatingBanner` to appear (50% scroll trigger if existing logic) → confirm 3-field form.
16. Visit `/blog` → wait for `NewsletterFooterBar` → click Subscribe → modal opens with 3 fields.

**Production readiness checklist (write to runbook):**
- [ ] `MAIL_MAILER=resend` in production `.env`
- [ ] `RESEND_API_KEY=re_...` set
- [ ] `MAIL_FROM_ADDRESS=newsletter@alisadikinma.com` (operator must verify domain SPF + DKIM in Resend dashboard)
- [ ] `MAIL_FROM_NAME="Ali Sadikin"`
- [ ] `MAIL_REPLY_TO_ADDRESS=ali.sadikincom85@gmail.com` (or ensure config supports replyTo)
- [ ] `php artisan migrate --force` runs in deploy step (already wired via deploy.sh)
- [ ] `php artisan newsletter:backfill-tokens` runs ONCE post-deploy on VPS (operator action)
- [ ] Verify `portfolio-queue.service` is `active (running)` on VPS via `systemctl status`
- [ ] Verify crontab entry `* * * * * php artisan schedule:run` exists for the right user
- [ ] Test send: log in to admin, click "Send test to my email" — confirms Resend connection works in production
- [ ] First Friday 09:00 WIB after deploy → check VPS: `tail -f storage/logs/laravel.log` for the cron firing
- [ ] Optional: subscribe from prod with own email + WA, verify entire roundtrip

**Verification:**
- [ ] Local smoke 1-16 all pass
- [ ] Runbook written + committed

---

### Phase Z2: Commit + push gate (manual)

**Estimated time:** N/A (waits for operator)

**Steps:**
1. After all phases complete, present per-phase commits to operator for review.
2. Per CLAUDE.md "Git Push Policy STRICT": **commit only, never push autonomously.** Wait for operator to say "push" / "deploy" / "naikin ke prod" / etc.
3. When operator approves push: single `git push origin main` triggers VPS auto-deploy via GitHub Actions.
4. Operator runs the production readiness checklist above on the VPS.

**Verification:**
- [ ] All phase commits in `git log` follow conventional commit format
- [ ] No `git push` until operator approves
- [ ] CLAUDE.md updated with newsletter section (per "After Changes (MANDATORY)" rule)

---

## Phase Dependency Graph

```
B1 (lead fields migration)
  ├─→ B3 (Newsletter model widening)
  │    ├─→ B4 (NewsletterController widening)
  │    │    ├─→ F1 (useNewsletter composable widening) ──┐
  │    │    │    └─→ F2 (4 touchpoint redesigns) ────────┤
  │    │    │         └─→ F3 (footer modal redesign) ────┤
  │    │    └─→ F4 (public unsubscribe page) ────────────┤
  │    └─→ B5a (Mailable class)
  │         └─→ B5b (Blade templates)
  │              └─→ B6 (cron command + schedule)
  └─→ B7 (backfill command — independent of model widening, can run anytime)

B2 (newsletter_sends migration)
  └─→ B8 (admin controller — needs both B4 and B6 done)
        └─→ F5 (admin view) ──┐
              └─→ F6 (sidebar entry)

F7 (cleanup CTASection) — independent, anytime

All → Z1 (smoke test) → Z2 (push gate)
```

**Parallel-friendly groups for `gaspol-parallel`:**
- Group 1 (after B4): F1 + F2 + F3 (frontend touchpoint work) || B5a + B5b + B6 (email pipeline)
- Group 2 (after B8): F5 + F6 (admin view) — F7 can run anytime after F2

---

## Anti-Placeholder Enforcement

These items MUST NOT be stubbed during execution:

- **Resend API call** in `WeeklyDigest` Mailable — must use real `Mail::to()->queue()`, not `Mail::fake()` outside test files.
- **Database queries** for posts/subscribers — must use real `Post::published()` scope + `Newsletter` model, no hardcoded arrays.
- **Token generation** — must use `Str::random(32)`, not a constant string.
- **WhatsApp validation regex** — must use `^\+[1-9]\d{6,14}$` strict E.164, not `'.*'` or skip.
- **Schedule entry timezone** — must specify `'Asia/Jakarta'`, not server-local.
- **CSV export** — must stream actual subscriber data from DB, not return a hardcoded sample.

If any item above can't be implemented immediately, STOP and ask operator. Do NOT silently substitute a placeholder — that would defeat the entire point of this plan.

---

## Anti-Pattern Watchlist (from design doc, repeated for executor)

- ❌ Don't reactivate `is_subscribed=false` soft-pause behavior. Hard-delete on unsubscribe per design.
- ❌ Don't add Resend webhook for open/click tracking — out of scope for v1.
- ❌ Don't send digests synchronously in a loop — always queue via systemd worker.
- ❌ Don't store WhatsApp without unique constraint (already covered in B1 migration).
- ❌ Don't use Pinia for the new admin view — use TanStack Query (matches LinkedIn admin pattern).

---

## Execution Handoff

**Option 1 (recommended): Execute in this session sequentially**
> Ready to start Phase B1. Use `gaspol-execute` with per-phase TDD checkpoints.

**Option 2: Execute parallel groups via `gaspol-parallel`**
> After B4 ships, dispatch [F1+F2+F3] and [B5a+B5b+B6] as 2 parallel agents. Saves ~30% wallclock vs sequential. Mode: `plan-phases`.

**Option 3: Save plan, execute in fresh session**
> All context is here. Open a new session, point at `docs/plans/2026-05-05-newsletter-system-plan.md`, run `gaspol-execute`.

---

**Estimated total time (sequential):** ~110 minutes pure execution + ~30 minutes test debugging buffer = ~2.5 hours.
**Estimated with parallel groups:** ~75 minutes execution + 20 minutes buffer = ~1.5 hours.
