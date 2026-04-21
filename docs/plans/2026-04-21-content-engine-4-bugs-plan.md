# Content Engine — 4 Bugs Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** Anti-placeholder enforcement active. Every phase has a Verification gate.

**Companion to:** [2026-04-21-content-engine-4-bugs-design.md](2026-04-21-content-engine-4-bugs-design.md)
**Ship mode:** Single PR (user decision). All 4 phases commit-then-push together.
**Estimated total:** 2.5 hrs

## User decisions captured

1. **Regen keeps live Post visible with stale content.** On re-publish, `Post::updateOrCreate(['id' => result_post_id], ...)` rewrites in place. Slug stays stable — no 404 during regen window.
2. **Translation flag OFF → Completed fires immediately on publish.** The gate only applies when EN translation is expected.
3. **Telegram message: reuse existing template, just fix the URL.** Current shape (`"✅ *Published* — ... Article: _title_ [View on site]"`) kept — just swap the `/blog` listing link for the actual post URL.
4. **Single PR.** No split, one commit per phase, all pushed together.

## Architecture Context (from CLAUDE.md + explored code)

- **Stack:** Laravel 12 + PHP 8.2, Vue 3.5 + Vite. Tests: PHPUnit feature tests on SQLite :memory: (must set `config(['app.url' => 'http://localhost'])` in setUp).
- **FSM owner:** [`App\Enums\ContentIdeaStatus`](../../backend/app/Enums/ContentIdeaStatus.php) — `TRANSITIONS` const at line 17-42. `canTransitionTo()` at line 44. The only transition site to `Completed` today: [`ContentPublishService::publish:139-142`](../../backend/app/Services/ContentPublishService.php#L139-L142).
- **Translation callback:** `POST /api/automation/posts/{id}/translation-complete` already exists — handler lives in `AutomationController`. Need to verify which controller owns it.
- **Telegram job:** [`App\Jobs\DispatchTelegramNotification`](../../backend/app/Jobs/DispatchTelegramNotification.php) dispatched as `dispatch($ideaId, $type)`. `'publish_success'` branch exists at line 57 but is orphan.
- **Post-idea linkage:** `content_ideas.result_post_id` → `posts.id` (nullable column, migration `2026_04_12_100000`).
- **Deploy policy:** `git push origin main` auto-deploys via GitHub Actions + VPS. Seeders run every deploy — all must be idempotent via `firstOrCreate`. Do NOT push without user ask (except this plan explicitly asks for single-push at the end).

## Tech Stack

- Backend: Laravel 12 Controllers + Feature tests + model casts
- Frontend: Vue 3.5 `<script setup>`, Tailwind 4 tokens, minimal ad-hoc CSS
- No new dependencies

## Data Integration Map

| Data | Source | Hook / API | Exists? | Action |
|---|---|---|---|---|
| Blog post publish timestamp | `posts.published_at` via `content_ideas.result_post_id` | Eloquent `belongsTo` | Relation missing on model | **Add `post()` belongsTo to ContentIdea** |
| Published column display | Computed: `result_post.published_at ?? source_data.pub_date` | `ContentIdeaController::index` resource transform | No | **Add `result_post_published_at` to list response** |
| FSM: completed → researching | `ContentIdeaStatus::TRANSITIONS` | Enum const | No | **Add to transitions array** |
| FSM: completed → generating_images | `ContentIdeaStatus::TRANSITIONS` | Enum const | No | **Add to transitions array** |
| Regenerate from Completed | `regenerateArticle` + `regenerateImagePrompts` status guards | Controller whitelists | Both reject `completed` | **Extend whitelist + transitionTo call** |
| Post update-in-place | `Post::updateOrCreate(['id' => result_post_id], ...)` | Eloquent | No (current code does `Post::create` or upsert-by-slug) | **Audit line 106, switch to id-based updateOrCreate when result_post_id set** |
| Publish waits for translation | `translation-complete` callback handler | `AutomationController` | Handler exists, but does not transition FSM | **Move `transitionTo(Completed)` from publish service → translation-complete callback** |
| Immediate completion when translate OFF | `config('services.article_generation.use_translate_phase')` | Config | Yes | **Branch on flag in `publish()`: flag off → transition inline, flag on → hold at images_ready** |
| Auto-pipeline translate-exhausted fallback | `AutoPipelineOrchestrator::ensureTranslationBeforePublish:459` | Existing Telegram dispatch | Yes | **Must also flip FSM to Completed at exhaustion (already sets translation_ready_at but no FSM change)** |
| Publish-success Telegram dispatch | `DispatchTelegramNotification::dispatch($id, 'publish_success')` | Job | No caller exists | **Dispatch from the single Completed-transition site** |
| Blog URL in Telegram message | `Post.slug` + `PostTranslation.language` | `TelegramNotificationService::sendPublishSuccess` | Only uses static `/blog` | **Resolve `https://alisadikinma.com/{lang}/blog/{slug}`** |

## Phase overview

| # | Phase | Files | Tests | Est |
|---|---|---|---|---|
| 1.1 | Failing test for `ContentIdea::post()` belongsTo + resource field | Feature test | 1 new | 8 min |
| 1.2 | Add `post()` relation + `result_post_published_at` to list response | `ContentIdea.php`, `ContentIdeaController@index` | — | 10 min |
| 1.3 | Frontend: prefer `result_post_published_at` on Completed rows | `ContentEngine.vue` | — | 10 min |
| 2.1 | Failing test: FSM allows completed→researching + completed→generating_images | `ContentIdeaStatusTransitionsTest` | 2 new | 8 min |
| 2.2 | Add 2 transitions to enum | `ContentIdeaStatus.php` | — | 3 min |
| 2.3 | Failing test: regenerateArticle from completed succeeds + flips status | Feature test | 1 new | 10 min |
| 2.4 | Whitelist `completed` in regenerateArticle + regenerateImagePrompts + add transition call | `ContentIdeaController` | — | 15 min |
| 2.5 | Failing test: `Post::updateOrCreate(['id' => result_post_id])` rewrites existing | Feature test | 1 new | 10 min |
| 2.6 | Switch `ContentPublishService::publish` to use `updateOrCreate` keyed on `id` when result_post_id set | `ContentPublishService.php` | — | 15 min |
| 3.1 | Failing test: publish() does NOT transition to Completed when translate flag ON | Feature test | 1 new | 10 min |
| 3.2 | Failing test: translation-complete callback transitions Completed | Feature test | 1 new | 10 min |
| 3.3 | Failing test: translate flag OFF → publish() still flips Completed immediately | Feature test | 1 new | 8 min |
| 3.4 | Failing test: auto-pipeline exhausted attempts → flips Completed | Feature test | 1 new | 10 min |
| 3.5 | Refactor: move Completed transition out of `publish()`, into callback + exhausted paths | `ContentPublishService`, `AutomationController`, `AutoPipelineOrchestrator` | — | 25 min |
| 4.1 | Failing test: publish-success Telegram dispatched from Completed-transition site | Feature test | 1 new | 10 min |
| 4.2 | Failing test: message body contains full blog URL with language prefix | Unit test | 1 new | 8 min |
| 4.3 | Add dispatch + update message template | `TelegramNotificationService`, wire dispatch at transition site | — | 15 min |
| Final | npm run build + php artisan test + commit-push gate | — | — | 10 min |

---

## Phase 1: Published date fix (Bug 1)

### Phase 1.1 — Failing test for belongsTo + resource field

**Files:** Create `backend/tests/Feature/ContentIdeaPublishedDateTest.php`

**Steps:**
1. Write failing test asserting that `ContentIdea::find($id)->post` returns a `Post` instance when `result_post_id` is set. Expected error: `Call to undefined method App\Models\ContentIdea::post()`.
2. Second assertion: `GET /api/admin/content-engine/ideas` response includes `result_post_published_at` field for ideas with `result_post_id` set. Expected fail: field missing.
3. Mirror EntityRefsLookupEndpointTest's test setUp pattern (sanctum acting-as, `config(['app.url' => 'http://localhost'])`).
4. Run `php artisan test --filter=ContentIdeaPublishedDateTest`, confirm 2 failures.

**Verification:**
- [ ] 2 assertions fail with explicit error messages
- [ ] Test uses `config(['app.url' => 'http://localhost'])` + `url()->forceRootUrl(...)` (tests otherwise 404 — see BlogPromoSlotTest precedent)

### Phase 1.2 — Add `post()` relation + resource field

**Files:**
- Modify `backend/app/Models/ContentIdea.php` (add relation)
- Modify `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (index method)

**Steps:**
1. Add `public function post(): BelongsTo { return $this->belongsTo(Post::class, 'result_post_id'); }` to ContentIdea model.
2. In `ContentIdeaController::index`, eager-load `post:id,published_at` (select only needed cols). Use `with(['post:id,published_at'])`.
3. In the per-idea resource transform, add `'result_post_published_at' => $idea->post?->published_at?->toIso8601String()`.
4. Re-run Phase 1.1 test, confirm pass.

**Verification:**
- [ ] Eager loading uses column selection to avoid loading full post content
- [ ] Returns `null` when result_post_id is null (not an error)
- [ ] Test passes

### Phase 1.3 — Frontend Published column prefers post.published_at for Completed

**Files:** Modify `frontend/src/views/admin/ContentEngine.vue`

**Steps:**
1. In the template at [line 341](../../frontend/src/views/admin/ContentEngine.vue#L341), replace `idea.source_data?.pub_date` references with a computed helper `displayPubDate(idea)` that returns `idea.result_post_published_at || idea.source_data?.pub_date`.
2. Define the helper near `formatPubDateRelative` / `formatPubDateAbsolute` at line 1238.
3. Tooltip on hover changes label to "Blog published at {absolute}" when source is `result_post_published_at`, else keep current "Source published at {absolute}".
4. Sort comparator at line 1301 also needs to prefer the blog-live date for Completed rows (so Completed tab sorts by most-recently-live).

**Manual verify:**
- Open Content Engine → Completed tab → hover a row's Published column → tooltip says "Blog published at..." with correct timestamp
- Switch to Draft tab → Published column still shows source's pub_date

**Verification:**
- [ ] npm run build passes
- [ ] No placeholder/TODO comments
- [ ] Completed tab column reflects blog-live time, not news-source time

**PHASE 1 COMMIT:** `fix(content-engine): Completed tab shows blog-live date, not source pub_date`

---

## Phase 2: Regenerate from Completed (Bug 2)

### Phase 2.1 — Failing test for FSM transitions

**Files:** Modify `backend/tests/Feature/ContentIdeaStatusTransitionsTest.php`

**Steps:**
1. Write two new test methods:
   - `completed_can_transition_to_researching()` — asserts `Completed->canTransitionTo(Researching)` is true
   - `completed_can_transition_to_generating_images()` — asserts same for GeneratingImages
2. Run, confirm both FAIL with current FSM table.

**Verification:**
- [ ] Both tests fail with `canTransitionTo returned false, expected true`

### Phase 2.2 — Add transitions to enum

**Files:** Modify `backend/app/Enums/ContentIdeaStatus.php`

**Steps:**
1. Change line 39 from `'completed' => ['archived']` to `'completed' => ['archived', 'researching', 'generating_images']`
2. Add comment above: `// completed → researching | generating_images: admin-triggered Regenerate; Post stays live until user re-publishes (Post::updateOrCreate keyed on id rewrites in place).`
3. Re-run Phase 2.1 tests, confirm pass.

**Verification:**
- [ ] All existing transition tests still pass (no regression)
- [ ] New 2 tests pass

### Phase 2.3 — Failing test: regenerateArticle from Completed

**Files:** Create `backend/tests/Feature/RegenerateFromCompletedTest.php`

**Steps:**
1. Write failing test: create idea in `Completed` status → call `POST /api/admin/content-engine/ideas/{id}/regenerate-article` → assert 200 + status flipped to `researching`.
2. Mock `ArticleGenerationService::triggerGeneration` so we don't actually SSH — return `['success' => true, 'method' => 'mock']`.
3. Run, confirm fail (current code returns 422 "Can only regenerate from article_ready, images_ready, or failed state.").

**Verification:**
- [ ] Test fails with 422 response

### Phase 2.4 — Whitelist completed + add transition

**Files:** Modify `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php`

**Steps:**
1. In `regenerateArticle` at [line 590](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L590), extend `$allowedStatuses` to include `'completed'`.
2. Update error message text to match new allowed set.
3. Audit `regenerateImagePrompts` at line 1789 — it already whitelists completed but never transitions FSM when triggering. Add explicit `$idea->transitionTo(ContentIdeaStatus::GeneratingImages, 'admin_regenerate_from_completed', ...)` guard when starting from `completed`.
4. Re-run Phase 2.3 test, confirm pass.

**Verification:**
- [ ] Regen from completed returns 200
- [ ] Status flipped to researching
- [ ] regenerateImagePrompts from completed flips to generating_images
- [ ] Live Post (result_post_id) still exists and is unchanged

### Phase 2.5 — Failing test: Post::updateOrCreate on re-publish

**Files:** Create `backend/tests/Feature/PublishUpdatesExistingPostTest.php`

**Steps:**
1. Create idea with `result_post_id` pointing to existing Post (slug='old-title', title='Old Title')
2. Update idea's `generated_article` with new title, call `ContentPublishService::publish($idea)`
3. Assert NO new Post row created — count posts table before/after, same count
4. Assert Post with original id has updated title + content
5. Assert slug stays stable (not regenerated from new title)

**Verification:**
- [ ] Test fails — current code creates new Post or crashes on unique-slug constraint

### Phase 2.6 — Refactor publish() to use updateOrCreate

**Files:** Modify `backend/app/Services/ContentPublishService.php`

**Steps:**
1. In `publish()`, before Post creation block at around line 106:
   - If `$idea->result_post_id` is set AND `Post::find($idea->result_post_id)` exists → use `Post::updateOrCreate(['id' => $existingPost->id], [...attributes...])`
   - Else → current create path
2. Key detail: DO NOT regenerate slug on update (use existing). Spatie HasSlug already handles this via `doNotGenerateSlugsOnUpdate()` — verify this is active on Post model.
3. PostTranslation row: use `updateOrCreate(['post_id' => $post->id, 'language' => $lang], [...])` (already does this at line 106 — good).
4. Re-run Phase 2.5 test, confirm pass.

**Verification:**
- [ ] Regen + republish: same post.id, same slug, updated title/content
- [ ] No duplicate Post rows
- [ ] Existing publish tests (new idea → new Post) still pass

**PHASE 2 COMMIT:** `feat(content-engine): regenerate from Completed reverts FSM + rewrites Post in place`

---

## Phase 3: Completed gating via translation callback (Bug 3)

### Phase 3.1 — Failing test: publish() defers Completed transition when translate ON

**Files:** Create `backend/tests/Feature/PublishDefersCompletedWhenTranslateEnabledTest.php`

**Steps:**
1. Set `config(['services.article_generation.use_translate_phase' => true])`
2. Create idea in `images_ready` status, call `ContentPublishService::publish`
3. Assert status IS `images_ready` (NOT Completed) after publish returns
4. Assert Post row exists with correct data

**Verification:**
- [ ] Test fails — current code transitions to Completed inline

### Phase 3.2 — Failing test: translation-complete callback flips to Completed

**Files:** Create `backend/tests/Feature/TranslationCompleteCallbackTest.php`

**Steps:**
1. Setup: idea in `images_ready` status with Post created + EN translation NOT yet in post_translations
2. Create EN PostTranslation row manually (simulate translation landing)
3. Call `POST /api/automation/posts/{post_id}/translation-complete`
4. Assert idea status === `completed`
5. Assert response 200

**Verification:**
- [ ] Test fails — current callback handler doesn't touch idea FSM

### Phase 3.3 — Failing test: translate flag OFF → publish still flips Completed immediately

**Files:** Same file as 3.1, add test

**Steps:**
1. Set `config(['services.article_generation.use_translate_phase' => false])`
2. Create idea in `images_ready`, call publish
3. Assert status === `completed` immediately

**Verification:**
- [ ] Test fails initially (before 3.5 refactor), but must pass after 3.5 (branch on flag)

### Phase 3.4 — Failing test: auto-pipeline translate-exhausted path

**Files:** Modify `backend/tests/Feature/AutoPipelineTranslateGateTest.php` (existing)

**Steps:**
1. Add assertion: when `translation_attempts_auto >= 3`, after `ensureTranslationBeforePublish` returns true and publish runs, status transitions to `Completed`.
2. Current behavior: only sets `translation_ready_at` + dispatches Telegram. FSM isn't flipped.

**Verification:**
- [ ] Exhausted path currently fails new FSM assertion

### Phase 3.5 — Refactor Completed transition

**Files:**
- `backend/app/Services/ContentPublishService.php` — remove inline `transitionTo(Completed)`, branch on flag
- `backend/app/Http/Controllers/Api/AutomationController.php` — find `translationComplete` handler, add FSM transition
- `backend/app/Services/AutoPipelineOrchestrator.php` — in the `translation_exhausted` branch (line 459), add FSM transition

**Steps:**
1. In `ContentPublishService::publish` lines 139-145, replace with:
   ```php
   $translateEnabled = config('services.article_generation.use_translate_phase', false);
   if (!$translateEnabled) {
       if ($idea->status !== 'completed') {
           $idea->transitionTo(ContentIdeaStatus::Completed, 'publish_service', [
               'result_post_id' => $post->id,
           ]);
       } else {
           $idea->update(['result_post_id' => $post->id]);
       }
   } else {
       $idea->update(['result_post_id' => $post->id]);
   }
   ```
2. In `AutomationController::translationComplete` (or wherever the handler lives — GREP: `translation-complete`), after the existing `translation_pending=false` update, add:
   ```php
   $idea = ContentIdea::where('result_post_id', $post->id)->first();
   if ($idea && $idea->canTransitionTo(ContentIdeaStatus::Completed)) {
       $idea->transitionTo(ContentIdeaStatus::Completed, 'translation_complete', [
           'result_post_id' => $post->id,
       ]);
   }
   ```
3. In `AutoPipelineOrchestrator::ensureTranslationBeforePublish` line 456-462 (exhausted branch), add FSM transition to Completed before returning true.

**Verification:**
- [ ] All Phase 3 tests pass
- [ ] All existing publish tests still pass
- [ ] `publish()` no longer dual-purposes Completed transition

**PHASE 3 COMMIT:** `refactor(content-engine): gate Completed transition on translation completion`

---

## Phase 4: Telegram publish-success dispatch + URL (Bug 4)

### Phase 4.1 — Failing test: publish-success dispatched from Completed-transition site

**Files:** Create `backend/tests/Feature/PublishSuccessTelegramDispatchTest.php`

**Steps:**
1. Use `Queue::fake()` to intercept dispatches
2. Setup: translate flag OFF (immediate completion), idea in `images_ready`, enabled Telegram + `telegram_notify_publish_success=true`
3. Call publish → assert `Queue::assertPushed(DispatchTelegramNotification::class, fn($job) => $job->type === 'publish_success')`

**Verification:**
- [ ] Test fails — no dispatch currently

### Phase 4.2 — Failing test: message body contains full blog URL

**Files:** Create `backend/tests/Unit/TelegramPublishSuccessMessageTest.php` (or extend existing)

**Steps:**
1. Mock `Http::fake` to capture sendMessage payloads
2. Setup: idea with `result_post_id` → Post with slug='my-post' + primary ID language
3. Set `telegram_notify_publish_success=true`, seed bot token + chat_id
4. Call `TelegramNotificationService::sendPublishSuccess($idea)`
5. Assert the captured text contains `https://alisadikinma.com/id/blog/my-post`

**Verification:**
- [ ] Test fails — current message body uses static `/blog`

### Phase 4.3 — Wire dispatch + update message

**Files:**
- `backend/app/Services/TelegramNotificationService.php` — update `sendPublishSuccess` message body
- Wherever the Completed transition fires (3 sites after Phase 3.5: translate-off publish, translation-complete callback, exhausted fallback) — dispatch `DispatchTelegramNotification::dispatch($idea->id, 'publish_success')`

**Steps:**
1. `sendPublishSuccess` — replace static `/blog` link with URL built from `$idea->post` + primary language. Pseudocode:
   ```php
   $post = $idea->post; // belongsTo added in Phase 1.2
   $primaryLang = $idea->generated_article['language'] ?? 'id';
   $url = $post
       ? config('app.url') . "/{$primaryLang}/blog/{$post->slug}"
       : config('app.url') . "/blog";
   $text = "✅ *Published* — Content Engine\n\n"
       . 'Article: _' . $this->escapeMarkdown($idea->title ?? 'Untitled') . "_\n\n"
       . "[View on site]({$url})";
   ```
2. Add `DispatchTelegramNotification::dispatch($idea->id, 'publish_success')` at each of the 3 Completed-transition sites.
3. Guard against duplicate dispatches: rely on `isEnabledFor('publish_success')` gate inside the service (already there). Also: translate-complete callback fires at most once per post (callback is idempotent).
4. Re-run Phase 4.1 + 4.2 tests, confirm pass.

**Verification:**
- [ ] Queue::fake captures exactly 1 `publish_success` dispatch per publish event
- [ ] Message body URL shape: `https://alisadikinma.com/{lang}/blog/{slug}`
- [ ] No dispatch when `telegram_notify_publish_success=false`

**PHASE 4 COMMIT:** `feat(telegram): publish-success notification with blog URL`

---

## Final gate

### Run all verification:

```bash
# Backend — ALL tests
cd backend && D:/xampp/php/php.exe artisan test 2>&1 | tail -20

# Frontend — build + smoke tests
cd frontend && npm run build
node src/utils/blogCardDistributor.test.mjs
node src/utils/splitHtmlByH2.test.mjs
node src/utils/newsletterState.test.mjs
node src/utils/stripFaqSection.test.mjs
node src/utils/extractFaqFromHtml.test.mjs
node src/utils/imagePositioning.test.mjs
```

### Placeholder scan:
```bash
cd backend && grep -rn "TODO\|FIXME\|console.log\|dd(" app/Services/ContentPublishService.php app/Http/Controllers/Api/Admin/ContentIdeaController.php app/Services/TelegramNotificationService.php app/Models/ContentIdea.php app/Enums/ContentIdeaStatus.php app/Jobs/DispatchTelegramNotification.php
cd frontend && grep -n "TODO\|FIXME\|console.log\|debugger" src/views/admin/ContentEngine.vue
```

### Commit + push (user-approved single-push):
```bash
git push origin main
```

Production deploy runs via GitHub Actions → VPS → `deploy.sh`. Post-deploy smoke checks:
- [ ] Completed tab Published column shows correct blog-live timestamps
- [ ] Regenerate on a Completed idea → status flips to In-progress
- [ ] Publish on translate-enabled idea → status stays `images_ready` until EN translation lands
- [ ] Telegram bot receives "✅ Published" message with a working blog URL

---

## Architectural Decision Log

### ADR — Completed transition moves from `publish()` to translation callback

**Context:** User expects an idea marked Completed to mean "both languages live on blog". Today `publish()` flips it as soon as ID post is created — long before EN translation lands.

**Decision:** Move FSM `transitionTo(Completed)` out of `publish()`. Fire it only when:
- `use_translate_phase=false` (no EN expected → publish is the final state) → inline in publish()
- `use_translate_phase=true` AND translation callback fires → handler flips FSM
- `use_translate_phase=true` AND auto-pipeline hits 3-attempt cap → orchestrator flips FSM with `translation_exhausted` sentinel

**Consequences:**
- ✅ FSM semantically correct — Completed = ready for audience in both languages
- ✅ Manual admin-publish path unchanged when translate disabled (current behavior preserved)
- ⚠️ Three Completed-transition call sites instead of one. Mitigated by explicit test coverage + each site calling the same `canTransitionTo` guard.

### ADR — Post::updateOrCreate keyed on id, not slug

**Context:** Regen from Completed must NOT create a new Post. The original Post URL must stay stable (SEO backlinks, existing `/blog/{slug}` references).

**Decision:** When `$idea->result_post_id` is set and the Post exists, update it in place via `Post::updateOrCreate(['id' => $existingId], [...])`. Spatie HasSlug's `doNotGenerateSlugsOnUpdate()` prevents slug regeneration on update.

**Consequences:**
- ✅ URL stability across regens
- ✅ PostTranslation rows also update via their existing `['post_id', 'language']` composite key
- ⚠️ If the Post was soft-deleted between regens, updateOrCreate would resurrect it silently. Edge case — mitigated by checking `!$existingPost->trashed()` before calling updateOrCreate.

---

## Next step

Hand off to `/gaspol-dev:gaspol-execute` with this plan → auto-executes phase-by-phase with per-phase verification gates. On any phase fail → STOP, don't auto-advance.
