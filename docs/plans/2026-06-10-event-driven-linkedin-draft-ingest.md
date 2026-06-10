# Event-Driven LinkedIn Draft Ingest (replace manual "Scan Blog Now")

**Date:** 2026-06-10
**Status:** Design approved (brainstorm) — awaiting implementation plan
**Operator decisions (locked):**
1. Trigger = blog publish moment (Gate 2 "Approve & Publish" in Content Engine)
2. Virality gate ≥ `linkedin_virality_min_score` (default 60) TETAP berlaku
3. Tombol "Scan blog now" + cron harian 03:00 DIHAPUS (full event-driven). Artisan command tetap ada di code untuk catch-up manual via SSH.

## Design

### Problem

`/admin/draft-posts` ([LinkedInQueueList.vue](../../frontend/src/views/admin/LinkedInQueueList.vue)) hanya terisi lewat dua jalur: cron harian `linkedin:scan-blog` 03:00 WIB, atau operator menekan tombol "Scan blog now" (`POST /admin/linkedin-drafts/scan-blog-now` → `Artisan::queue('linkedin:scan-blog')`). Post yang publish siang hari tidak terlihat sampai operator scan manual — friction yang mau dihilangkan.

### Approach (chosen: A — targeted scan flag, queued from publish site)

Mirror persis preseden June 9: `social-cross-post:scan --draft-id={id}` yang di-queue dari event site (`LinkedInCarouselImageService::maybeDispatchCrossPostFanout`).

1. **`--post-id=` option** pada [ScanBlogForLinkedInConversion](../../backend/app/Console/Commands/ScanBlogForLinkedInConversion.php):
   - Targeted path: query `Post::where('id', $postId)` — **bypass lookback window** `published_at >= now()-hours` (post-nya baru publish detik itu, tapi flag harus tetap bekerja untuk post lama jika dipakai manual).
   - **TETAP enforce**: `published=true` + `whereDoesntHave('linkedinPosts')` (idempotency satu-live-draft-per-post) + virality gate via `contentIdea.virality_score >= linkedin_virality_min_score`.
   - Non-targeted path (tanpa flag) tidak berubah — tetap bisa dipakai manual via SSH untuk catch-up.

2. **Dispatch dari publish site** — [ContentIdeaController::approveAndPublish](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) (line ~1317): setelah Post tercipta + published dan transaksi commit (gunakan `DB::afterCommit` atau letakkan setelah blok transaksi), panggil:
   ```php
   Artisan::queue('linkedin:scan-blog', ['--post-id' => $post->id]);
   ```
   Dibungkus try/catch + `Log::warning` — kegagalan dispatch TIDAK BOLEH menggagalkan publish (mirror pola `maybeCascadeToPublisher` non-fatal).
   Catatan: translate-before-publish gate sudah jalan sebelum titik ini, jadi EN translation tersedia saat `GenerateLinkedInPost` membaca blog.

3. **Removals:**
   - Frontend: tombol "Scan blog now" + `scanBlogNow()` + `scanRunning`/`scanFlash` state + copy empty-state yang menyebut tombol, di [LinkedInQueueList.vue](../../frontend/src/views/admin/LinkedInQueueList.vue).
   - Backend: route `POST /admin/linkedin-drafts/scan-blog-now` ([routes/api.php](../../backend/routes/api.php) ~L1296) + method `LinkedInDraftController::scanBlogNow` (~L1522).
   - Scheduler: ghost-delete row `linkedin:scan-blog` di [ScheduledCommandSeeder](../../backend/database/seeders/ScheduledCommandSeeder.php) (pola PageSectionSeeder: seeder menghapus row obsolete saat seed sehingga `DynamicScheduleRegistrar` berhenti meregistrasi cron-nya). Cek juga `OPERATOR_SIGNATURES` di [schedulerTiers.js](../../frontend/src/views/admin/schedulerTiers.js) — drop `linkedin:scan-blog` jika terdaftar.
   - Command class + flag `--hours/--limit/--dry-run/--min-virality` TETAP — dipakai event hook + SSH manual.

### Flow (after)

```
Operator klik "Approve & Publish" (Gate 2 Content Engine)
  → Post created + published (+ EN translation via translate gate)
  → afterCommit: Artisan::queue('linkedin:scan-blog', ['--post-id' => N])
  → queue worker jalankan scan targeted:
      gate: published ✓ · belum punya live draft ✓ · virality ≥ 60 ✓
  → linkedin_posts row (pending_generation) + GenerateLinkedInPost::dispatch
  → draft muncul di /admin/draft-posts dalam hitungan detik,
    generation (~force-carousel path) jalan otomatis
  → downstream tidak berubah: carousel render → cross-post fan-out → slot publish
```

### Edge cases

| Case | Behavior |
|---|---|
| Virality < 60 | Scan skip (logged). Tidak ada draft — by design. Catch-up: SSH `linkedin:scan-blog --post-id=N --min-virality=0`. |
| Post tanpa ContentIdea (manual blog) | Tidak melewati jalur ini (trigger hanya dari Content Engine publish). Scan non-targeted juga skip — perilaku existing. |
| Re-publish / regenerate idea yang sudah punya draft | `whereDoesntHave('linkedinPosts')` → no-op, idempotent. |
| Queue worker mati saat publish | Job `Artisan::queue` tersimpan di tabel `jobs`, jalan saat worker hidup lagi. Kalau dispatch sendiri throw → log warning, publish tetap sukses; catch-up manual via SSH. |
| Dispatch double (retry publish) | Idempotency guard yang sama menahan duplikat. |

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|-----------|-------------|-----------|-------|
| Targeted scan (`--post-id`) | `posts` + `content_ideas.virality_score` + `linkedin_posts` | ✅ command exists | Tambah 1 option + branch query |
| Publish hook | `ContentIdeaController::approveAndPublish` | ✅ | +~10 LoC afterCommit dispatch |
| Virality threshold | `Setting linkedin_virality_min_score` | ✅ | Tidak berubah |
| Draft generation | `GenerateLinkedInPost` job → force-carousel path | ✅ | Tidak berubah |
| Cron removal | `ScheduledCommandSeeder` ghost-delete | ✅ pattern exists | PageSectionSeeder precedent |
| UI removal | `LinkedInQueueList.vue` | ✅ | Pure deletion |

Tidak ada migration, tidak ada model baru, tidak ada placeholder. Backend-first; frontend hanya deletion.

### Testing sketch (for plan phase)

- Feature: `--post-id` creates draft for published post with virality ≥ gate; skips below-gate; skips post with live draft; bypasses lookback window for old post.
- Feature: `approveAndPublish` queues `linkedin:scan-blog --post-id` (assert via `Artisan`/Bus fake or jobs table); dispatch failure does not fail publish.
- Seeder: re-seed deletes `linkedin:scan-blog` scheduled row.
- Frontend: smoke — no `scan-blog-now` reference remains.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Make every Content Engine publish automatically create its LinkedIn draft (virality-gated), then remove the manual "Scan blog now" button and the daily 03:00 cron. The artisan command stays as the engine + SSH catch-up path.

### Architecture Context (verified against code, 2026-06-10)

- **Publish site:** [`ContentIdeaController::approveAndPublish`](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) line ~1317. Delegates to `ContentPublishService::publish($idea)` (line ~1341); on success `$post = $result['post'];` at line ~1347 — the Post is created + published by that point. **Hook insertion point: immediately after `$post = $result['post'];`** (no surrounding `DB::transaction` in the controller — `afterCommit` semantics not needed at this level; the service has already returned).
- **Scan command:** [`ScanBlogForLinkedInConversion`](../../backend/app/Console/Commands/ScanBlogForLinkedInConversion.php). Options at L26-29 (`--hours/--dry-run/--limit/--min-virality`). Query L48-65: `published=true` + `whereNotNull(published_at)` + `published_at >= now()-hours` + `whereDoesntHave('linkedinPosts')` + `whereHas('contentIdea', virality_score >= gate)`. Draft create + `GenerateLinkedInPost::dispatch` ~L107.
- **Manual endpoint to remove:** route [`routes/api.php` ~L1296](../../backend/routes/api.php) (`POST /admin/linkedin-drafts/scan-blog-now`, registered BEFORE the `{id}` wildcard — note in comment at L1295) + [`LinkedInDraftController::scanBlogNow`](../../backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php) ~L1503-1560.
- **Cron row to remove:** [`ScheduledCommandSeeder`](../../backend/database/seeders/ScheduledCommandSeeder.php) — `linkedin:scan-blog` row at ~L116-124 (in `$rows`). Ghost-delete precedent already in the same file at L257: `ScheduledCommand::where('signature', 'linkedin:process-scheduled')->delete();`.
- **Frontend button:** [`LinkedInQueueList.vue`](../../frontend/src/views/admin/LinkedInQueueList.vue) — `scanBlogNow()`/`scanRunning`/`scanFlash` at L223-267, button markup ~L373-390, empty-state copy mentioning the button at L341.
- **Scheduler tier allowlist:** [`schedulerTiers.js`](../../frontend/src/views/admin/schedulerTiers.js) `OPERATOR_SIGNATURES` includes `'linkedin:scan-blog'` (L25); its test [`schedulerTiers.test.mjs`](../../frontend/src/views/admin/schedulerTiers.test.mjs) L38 asserts it.
- **Dev environment:** no PHP on the dev Mac — author backend tests + verify `php -l` on the VPS (`mcp ssh-prod-vps` or CI); full suite runs on CI (`php artisan test`, sqlite `:memory:`). Frontend `.mjs` smoke tests + `npm run build` run locally.
- **Non-fatal dispatch precedent:** `BaseSocialGenerationService::maybeCascadeToPublisher` (try/catch + `Log::warning`, never fails caller).

### Tech Stack

Laravel 12 (artisan command options, `Artisan::queue`, Queue::fake with `Illuminate\Foundation\Console\QueuedCommand`), PHPUnit feature tests, Vue 3 (deletion only), Node `.mjs` source-assertion smoke tests.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Targeted scan query | `posts` + `linkedin_posts` + `content_ideas.virality_score` | `ScanBlogForLinkedInConversion::handle` | Yes | Add `--post-id` branch, reuse gates |
| Virality threshold | `settings.linkedin_virality_min_score` | `Setting::get(...)` (already in command) | Yes | Use existing |
| Draft creation + generation | `LinkedInPost` + `GenerateLinkedInPost` job | existing block in command ~L107 | Yes | Use existing (no change) |
| Publish hook | `approveAndPublish` `$result['post']` | `Artisan::queue('linkedin:scan-blog', ['--post-id' => …])` | No (new ~10 LoC) | Create real dispatch, try/catch non-fatal |
| Cron removal | `scheduled_commands` table | `ScheduledCommandSeeder` ghost-delete | Yes (pattern L257) | Remove row from `$rows` + add delete |
| UI removal | — | `LinkedInQueueList.vue` | Yes | Pure deletion |
| Tier allowlist | — | `schedulerTiers.js` + test | Yes | Drop signature + update test |

No migrations. No new models. No placeholders anywhere.

### Phase A — Backend: `--post-id` targeted scan (TDD)

**Estimated time:** ~30 min

**Files:**
- Modify: `backend/app/Console/Commands/ScanBlogForLinkedInConversion.php`
- Test (create): `backend/tests/Feature/ScanBlogTargetedPostTest.php`

**Steps:**
1. Write failing test for targeted scan behavior (5 cases below). Expected error: `The "--post-id" option does not exist.` (Symfony Console `InvalidOptionException`).
   - `test_post_id_creates_draft_for_published_post_above_gate` — post published 10 days ago (proves lookback bypass), idea virality 75 → `linkedin_posts` row created (`pending_generation`) + `Queue`/`Bus` assertion that `GenerateLinkedInPost` dispatched.
   - `test_post_id_skips_below_virality_gate` — virality 40 → no row.
   - `test_post_id_skips_when_live_draft_exists` — existing live draft → no second row.
   - `test_post_id_skips_unpublished_post` — `published=false` → no row.
   - `test_non_targeted_scan_still_respects_hours_window` — post published 10 days ago, no `--post-id` → no row (regression guard).
   - Fixture pattern: mirror `ScanLinkedInForCrossPostTest` setup (factories for Post/PostTranslation/ContentIdea, `Bus::fake()`/`Queue::fake()`).
2. Run test file (CI or VPS), confirm all fail for the expected reason.
3. Implement: add `{--post-id= : Target a single post — bypasses the lookback window, keeps virality + idempotency gates}` to `$signature`. In `handle()`: when `--post-id` set, replace the `published_at >= now()-subHours` constraint with `->where('id', $postId)` (keep `published=true`, `whereNotNull(published_at)`, `whereDoesntHave('linkedinPosts')`, virality `whereHas`). Log a distinct line `[LinkedInScan] targeted post #N` for audit.
4. Run tests, confirm all pass.
5. Commit: `feat(linkedin): add --post-id targeted mode to linkedin:scan-blog`

**Verification:**
- [ ] `php -l` clean on the modified command (VPS or CI)
- [ ] 5 new tests green on CI; existing scan behavior unchanged (non-targeted test green)
- [ ] Virality gate + one-live-draft idempotency enforced on the targeted path (asserted by tests, not assumed)
- [ ] No placeholder/TODO comments in new code

### Phase B — Backend: publish-site dispatch hook (TDD)

**Estimated time:** ~25 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (after `$post = $result['post'];`, ~L1347)
- Test (create): `backend/tests/Feature/ApproveAndPublishQueuesLinkedInScanTest.php`

**Steps:**
1. Write failing test for the publish hook. Expected error: `Queue::assertPushed` failure — `Illuminate\Foundation\Console\QueuedCommand` was not pushed.
   - `test_publish_queues_targeted_linkedin_scan` — bind a mock `ContentPublishService` (`$this->instance(...)`) returning `['post' => $publishedPost, 'translation_pending' => false]`; `Queue::fake()`; hit `POST /api/admin/content-engine/ideas/{id}/publish` as Sanctum admin; assert pushed `QueuedCommand` whose payload contains `linkedin:scan-blog` + `--post-id`. (Note feature-test `APP_URL` subpath workaround from CLAUDE.md: override `app.url` + `URL::forceRootUrl` in `setUp` — copy from `GeminiGenRelayWebhookTest`.)
   - `test_publish_succeeds_when_scan_dispatch_throws` — `Artisan::shouldReceive('queue')->andThrow(new \RuntimeException('queue down'))` (facade partial mock); assert response still 200 `success=true`.
2. Run tests, confirm both fail for the expected reasons.
3. Implement after `$post = $result['post'];`:
   ```php
   // Event-driven draft ingest (June 10, 2026): replaces the daily
   // linkedin:scan-blog cron + manual "Scan blog now" button. Targeted
   // scan reuses the virality gate + one-live-draft idempotency.
   try {
       Artisan::queue('linkedin:scan-blog', ['--post-id' => $post->id]);
   } catch (\Throwable $e) {
       Log::warning('[ContentEngine] linkedin:scan-blog dispatch failed (non-fatal)', [
           'post_id' => $post->id, 'error' => $e->getMessage(),
       ]);
   }
   ```
   (Add `use Illuminate\Support\Facades\Artisan;` if absent.)
4. Run tests, confirm pass.
5. Commit: `feat(content-engine): auto-queue targeted LinkedIn scan on publish`

**Verification:**
- [ ] `php -l` clean; both tests green on CI
- [ ] Publish response shape unchanged (existing `approveAndPublish` tests / smoke still green)
- [ ] Dispatch failure provably non-fatal (test 2)
- [ ] No placeholder/TODO comments

### Phase C — Removal: button + endpoint + cron row + tier allowlist (TDD)

**Estimated time:** ~30 min

**Files:**
- Modify: `backend/routes/api.php` (drop `scan-blog-now` route ~L1295-1297)
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (drop `scanBlogNow` ~L1503-1560)
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php` (remove row from `$rows` + add ghost-delete)
- Test (create): `backend/tests/Feature/ScheduledCommandSeederScanBlogRemovalTest.php`
- Modify: `frontend/src/views/admin/LinkedInQueueList.vue` (drop button + state + flash + empty-state copy)
- Modify: `frontend/src/views/admin/schedulerTiers.js` (drop `'linkedin:scan-blog'` from `OPERATOR_SIGNATURES`)
- Modify: `frontend/src/views/admin/schedulerTiers.test.mjs` (L38: flip assertion — signature no longer operator-tier)
- Test (create): `frontend/src/views/admin/linkedinQueueNoScan.test.mjs` (source assertion: `LinkedInQueueList.vue` contains no `scan-blog-now` / `scanBlogNow`)

**Steps:**
1. Write failing seeder test. Expected error: assertion failure — `scheduled_commands` row with signature `linkedin:scan-blog` still exists after seeding.
   - `test_seeder_removes_scan_blog_row` — insert the row (simulating an existing deployed DB), run `ScheduledCommandSeeder`, assert `ScheduledCommand::where('signature','linkedin:scan-blog')->doesntExist()`. Mirror `ScheduledCommandSeederCrossPostTest` structure.
2. Write failing frontend smoke `linkedinQueueNoScan.test.mjs`. Expected error: assertion failure — source still contains `scan-blog-now`.
3. Run both, confirm they fail for the expected reasons.
4. Implement backend removals: delete route lines + `scanBlogNow` method; in seeder remove the `linkedin:scan-blog` entry from `$rows` and add `ScheduledCommand::where('signature', 'linkedin:scan-blog')->delete();` beside the existing L257 ghost-delete.
5. Implement frontend removals: button markup + `scanBlogNow`/`scanRunning`/`scanFlash` + flash banner + rewrite the `in_progress` empty-state copy (no longer mentions the button — e.g. "Drafts are created automatically when you publish from Content Engine."); drop signature from `OPERATOR_SIGNATURES`; update `schedulerTiers.test.mjs` L38 to assert it is NOT operator-tier (classified `system`… actually absent from scheduler entirely — assert `!OPERATOR_SIGNATURES.has('linkedin:scan-blog')`).
6. Run: seeder test (CI), both `.mjs` tests (`node --test frontend/src/views/admin/*.test.mjs`), `npm run build`.
7. Grep repo for stragglers: `grep -rn "scan-blog-now\|scanBlogNow" backend/ frontend/src/` → must return nothing.
8. Commit: `feat(linkedin)!: remove manual scan button + daily scan cron (event-driven ingest)`

**Verification:**
- [ ] Seeder test green on CI; re-seed idempotent (second run yields same state)
- [ ] Both `.mjs` smoke tests green locally; `npm run build` clean
- [ ] `grep -rn "scan-blog-now\|scanBlogNow"` over backend/ + frontend/src/ returns nothing
- [ ] Route removal verified: `php artisan route:list | grep scan-blog` empty (VPS/CI)
- [ ] No placeholder/TODO comments

### Phase D — Docs sync + ops notes

**Estimated time:** ~15 min

**Files:**
- Modify: `CLAUDE.md` (root — update `ScanBlogForLinkedInConversion` bullet, linkedin routes list, seeded-inventory count, "Last Updated" changelog entry)

**Steps:**
1. Update the `ScanBlogForLinkedInConversion` description: no longer cron-scheduled; event-driven via `approveAndPublish` targeted dispatch; `--post-id` flag documented; SSH catch-up note.
2. Remove `POST /admin/linkedin-drafts/scan-blog-now` from the route inventory; adjust ScheduledCommandSeeder row counts.
3. Add "Last Updated" changelog entry (operator post-deploy note: deploy.sh runs the seeder automatically — the cron row disappears from `/admin/settings?tab=scheduler` after first deploy; in-flight published posts without drafts can be caught up once via SSH `php artisan linkedin:scan-blog --hours=720`).
4. Commit: `docs(claude): event-driven LinkedIn draft ingest — scan button + cron retired`

**Verification:**
- [ ] CLAUDE.md reflects actual shipped behavior (no stale references to the button/cron)
- [ ] Operator post-deploy steps documented

### Phase ordering & parallelism

A → B sequential (B's test exercises the flag A introduces — keep one executor). C depends on A+B conceptually but touches disjoint files; can run after A+B or in parallel with B at executor's discretion (no file overlap with B). D last.

### Execution caveats

- **No PHP locally** — author tests in full fidelity, `php -l` via `mcp ssh-prod-vps`, suites run on CI. Do NOT claim tests pass without CI/VPS evidence (gaspol-verify).
- **Git policy:** commit per phase, **never push** — operator authorizes pushes (deploy.sh auto-deploys on push).
- **Security note (Phase B):** new dispatch is admin-auth'd route (existing `auth:sanctum`), no new input surface — `--post-id` comes from the server-side `$post->id`, never from request input.
