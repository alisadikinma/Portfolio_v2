> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Skip `/linkedin-gen` under force-carousel + inline blog content to `/carousel-gen`

## Goal

When `linkedin_force_carousel` is ON (the default since June 9, 2026), the LinkedIn generation pipeline runs the full `/linkedin-gen` orchestrator (~10–13 min: brief→convert→validate over the ~10KB article + ~100KB×3 ref files) and then **throws away ~90% of its output** — `forceCarouselEnvelope()` overrides the format decision, the LinkedIn text post is rebuilt backend-side by `buildCarouselCaption()` from the carousel slides, and only a thin `brief` survives (observed `brief_hook: null`). The actual carousel storyline + slide copy + image prompts are authored entirely by `/carousel-gen`, which the backend dispatches *after* `/linkedin-gen`.

This plan **skips `/linkedin-gen` entirely when force-carousel is ON** and dispatches `/carousel-gen` directly, cutting ~10–13 min off every carousel generation. As an addition, it **embeds the full blog article content inline in the `/carousel-gen` prompt** (today `/carousel-gen` only `curl`s the `--blog-source` URL for OG metadata + caption — thin, and dependent on a live fetch), so the storyline is built from the real article instead of an OG blurb.

Backend-only. Zero migrations. Commit-only (no push — operator authorizes deploys).

## Architecture Context (from CLAUDE.md + code read 2026-06-09)

- Entry: [`LinkedInGenerationService::generate()`](../../backend/app/Services/LinkedInGenerationService.php) (line 80). Current flow: guard → advance `Generating` → `buildBlogPayload($draft)` → `invokePlugin($blog)` (SSH `/linkedin-gen`, ~10–13 min) → `parseOrchestratorOutput` → `forceCarouselEnvelope($parsed,$forceEnabled)` → Step 4.5 governor → Step 5 plugin-`failed` check → Step 5.5 `applyCarouselGenAdapter($parsed, $blog['url'], $draft->id)` → `persistAndRoute`.
- `forceCarouselEnvelope($parsed, $forceEnabled)` (line 846) rewrites a non-failed, non-carousel envelope to `{format:'carousel', status:'route_to_carousel_gen'}`.
- `applyCarouselGenAdapter($parsed, $blogUrl, $draftId)` (line 895) — STRICT: requires `format='carousel'` + `status='route_to_carousel_gen'`, calls `dispatchCarouselGenEngine($brief, $blogUrl, $draftId)` (line 975), maps via `CarouselGenOutputAdapter`, throws `CarouselGenAdapterException` on null/failed.
- `dispatchCarouselGenEngine` builds prompt `"/carousel-gen --pipeline --blog-source=<url> --bilingual=id,en --narrative=5act --target-slides=N"` and SSH-runs it. `inferTargetSlides($brief)` reads `$brief['hook_framework']` (default 7).
- `persistAndRoute` carousel branch (line 657): caption/hashtags/link_comment are **backend-built** (`buildCarouselCaption('', $carousel, $parsed['brief'] ?? [], $draft)`, `resolveHashtags(null, $parsed['brief']['hashtags'] ?? null, $draft)`, `resolveLinkComment('', $draft)`) — all have fallbacks from carousel slides + blog when `brief` is empty.
- `buildBlogPayload($draft)` (line 343) returns `['url'=>..., 'title'=>..., 'content'=>...]` (EN-preferred translation, ≥100 chars required). It is the source of the inline content.
- `Setting::get('linkedin_force_carousel', 'true')` is the toggle (group `linkedin`).
- Cross-post captions (IG/TikTok/Threads) are authored independently by their own plugins from blog + `carousel_slides` — **not** affected by this change.

## Tech Stack

Laravel 12 / PHP 8.2. Tests: PHPUnit (project convention, NOT Pest). **No PHP/vendor on the dev Mac** — tests are authored to full fidelity and run on CI (`php artisan test`). Existing test precedent: [`LinkedInGenerationServiceCarouselGenRouterTest`](../../backend/tests/Feature/LinkedInGenerationServiceCarouselGenRouterTest.php) (mocks `dispatchCarouselGenEngine`), [`LinkedInGenerationServiceParseTest`](../../backend/tests/Unit/LinkedInGenerationServiceParseTest.php), [`LinkedInForceCarouselEnvelopeTest`](../../backend/tests/Unit/LinkedInForceCarouselEnvelopeTest.php) (pure-method unit tests).

## Data Integration Map

| Concern | Data Source | Hook/Method | Exists? | Action |
|---|---|---|---|---|
| Force-carousel toggle | `settings` group `linkedin` | `Setting::get('linkedin_force_carousel','true')` | Yes | Read earlier in `generate()` (before invoke) |
| Blog url + content | `posts` + `post_translations` | `buildBlogPayload($draft)` → `['url','title','content']` | Yes | Reuse; pass `content` to carousel-gen |
| Synthetic brief pillar | `content_ideas.pillar` via `$draft->post->contentIdea` | new helper, nullable fallback `'ai_generalist'` | Yes (relation may be null) | Read with null-safe fallback |
| Carousel slides | `/carousel-gen` plugin | `dispatchCarouselGenEngine` (+ inline content) | Yes | Add optional `$blogContent` param |
| Caption/hashtags/link | carousel slides + blog | `buildCarouselCaption`/`resolveHashtags`/`resolveLinkComment` | Yes | Unchanged — fallbacks cover empty brief |

**Contract:** the synthetic envelope path must produce a `$parsed` shape byte-identical (for the keys `applyCarouselGenAdapter` + `persistAndRoute` consume) to what `forceCarouselEnvelope` produces today, so all downstream code runs unchanged. The ONLY behavioral delta is: `invokePlugin` is not called, and `/carousel-gen` receives inline content.

## Phases

### Phase A — Pure synthetic-envelope builder

**Files:**
- Modify: `backend/app/Services/LinkedInGenerationService.php` (add `private function buildForcedCarouselEnvelope(LinkedInPost $draft): array`)
- Test: `backend/tests/Unit/LinkedInForcedCarouselEnvelopeBuilderTest.php` (new)

**Steps:**
1. Write failing test for `buildForcedCarouselEnvelope`. Expected error: `Error: Call to undefined method ...::buildForcedCarouselEnvelope()`. Assert returned array has `format==='carousel'`, `status==='route_to_carousel_gen'`, `carousel===null`, `post===null`, `validation===null`, and `brief['pillar']` resolves from `$draft->post->contentIdea->pillar` (set a relation) and falls back to `'ai_generalist'` when the idea/pillar is absent.
2. Run test, confirm it fails for the expected reason.
3. Implement `buildForcedCarouselEnvelope` as a pure method (make it `public` for unit-test access, per `buildBlogPayload`/`parseOrchestratorOutput` precedent). Null-safe relation read (no `loadMissing` so the vanilla-`TestCase` unit test stays DB-free, mirroring `buildBlogPayload`).
4. Run tests, confirm pass.
5. Commit: `feat(linkedin): synthetic route_to_carousel_gen envelope builder for force-carousel skip`

**Verification:**
- [ ] Returns the exact key shape `applyCarouselGenAdapter` requires (`format`,`status`) + `persistAndRoute` reads (`brief`, `carousel`, `validation`).
- [ ] Pillar fallback works with null `post`/`contentIdea`/`pillar`.
- [ ] No placeholder/TODO comments.
- [ ] Unit test green on CI.

### Phase B — Thread inline blog content into `/carousel-gen`

**Files:**
- Modify: `backend/app/Services/LinkedInGenerationService.php` (`applyCarouselGenAdapter` + `dispatchCarouselGenEngine` gain optional `?string $blogContent = null`; `dispatchCarouselGenEngine` appends content to the prompt)
- Test: `backend/tests/Unit/CarouselGenInlineContentPromptTest.php` (new) — extract prompt-building into a testable seam OR assert via a spy.

**Steps:**
1. Write failing test asserting the `/carousel-gen` prompt contains a `SOURCE ARTICLE CONTENT` block with the article body when `$blogContent` is non-empty, and omits it when null/empty. Expected error: undefined param / assertion fail (prompt has no content block).
2. Run test, confirm fail.
3. Implement: add `?string $blogContent = null` to `dispatchCarouselGenEngine` (last param, backward-compatible). After `$prompt = "/carousel-gen {$flagString}";`, append when non-empty:
   ```php
   if (is_string($blogContent) && trim($blogContent) !== '') {
       $prompt .= "\n\nSOURCE ARTICLE CONTENT (primary source — use this; the --blog-source URL is supplementary OG metadata only):\n"
                . trim($blogContent);
   }
   ```
   Add matching optional `?string $blogContent = null` to `applyCarouselGenAdapter` and forward it to `dispatchCarouselGenEngine`. Existing callers (passing 3 args) keep working.
4. Run tests, confirm pass. Confirm existing `LinkedInGenerationServiceCarouselGenRouterTest` still green (param is optional).
5. Commit: `feat(linkedin): embed blog article inline in /carousel-gen prompt (no live-fetch dependency)`

**Verification:**
- [ ] Prompt includes content block when content present; omitted when empty.
- [ ] Existing carousel-gen router tests unaffected (optional param).
- [ ] No change to flags (`--blog-source` still sent — supplementary).
- [ ] Tests green on CI.

### Phase C — Branch `generate()` to skip `/linkedin-gen` when force-carousel ON

**Files:**
- Modify: `backend/app/Services/LinkedInGenerationService.php` (`generate()`)
- Test: `backend/tests/Feature/LinkedInGenerateSkipPluginTest.php` (new)

**Steps:**
1. Write failing test: with `linkedin_force_carousel='true'`, calling `generate($draft)` MUST NOT call `invokePlugin` and MUST call `dispatchCarouselGenEngine` with the blog content. Use a partial mock (`Mockery::mock(...)->makePartial()`) asserting `invokePlugin` is `shouldNotReceive` and `dispatchCarouselGenEngine` `shouldReceive` returns a valid envelope. Second test: with `='false'`, `invokePlugin` IS called (current behavior preserved). Expected error: `invokePlugin` invoked (Mockery `shouldNotReceive` violation).
2. Run, confirm fail.
3. Implement in `generate()` after `buildBlogPayload` returns `$blog`: read `$forceEnabled = filter_var(Setting::get('linkedin_force_carousel','true'), FILTER_VALIDATE_BOOLEAN)`. **If `$forceEnabled`:** emit progress `'force_carousel_direct' 25 'Force-carousel ON — skipping /linkedin-gen, dispatching /carousel-gen directly'`; `$parsed = $this->buildForcedCarouselEnvelope($draft)`; set `$detectedFormat='carousel'`, `$isCarouselRoute=true`; skip directly to Step 5.5 (the existing `applyCarouselGenAdapter` try/catch), passing `$blog['content']` as the new arg. **Else:** run the existing path unchanged (invoke→parse→forceCarouselEnvelope→governor→Step5). Keep `forceCarouselEnvelope`/governor intact for the force-OFF branch. Ensure the force-ON branch still hits `persistAndRoute` and the same `CarouselGenAdapterException`/`Throwable`→`markFailed` handling.
4. Run tests, confirm pass. Re-run full LinkedIn suite (parse, force-envelope, router, caption) — all green.
5. Commit: `feat(linkedin): skip /linkedin-gen and dispatch /carousel-gen directly when force-carousel ON`

**Verification:**
- [ ] force-ON: `invokePlugin` NOT called; `/carousel-gen` dispatched with inline content; FSM reaches `manual_review`/`awaiting_publish` exactly as today.
- [ ] force-OFF: byte-identical to current behavior (regression).
- [ ] `/carousel-gen` failure still → `markFailed` → manual review (no silent text downgrade).
- [ ] No new SSH call added on the force-OFF path.
- [ ] Full LinkedIn test suite green on CI.

### Phase D — Docs sync

**Files:**
- Modify: root `CLAUDE.md` (LinkedIn carousel section + Last Updated changelog) via `gaspol-sync-docs`.

**Steps:**
1. Document the force-carousel skip path + inline-content behavior in the `LinkedInGenerationService` description and the operational flow ("post-May-2 strict /carousel-gen" list). Note the ~10–13 min saving + that `/linkedin-gen` is now bypassed when force-carousel ON.
2. Commit: `docs: sync CLAUDE.md for force-carousel /linkedin-gen skip`

**Verification:**
- [ ] CLAUDE.md reflects the new branch + inline content + that the governor/`forceCarouselEnvelope` are force-OFF-only now.

## Out of scope / non-goals

- GeminiGen webhook fix (separate; poller is the working completion path — see memory note `geminigen-webhook-never-fires`).
- Cross-post caption logic (IG/TikTok/Threads) — independent of `/linkedin-gen`, untouched.
- Plugin (`linkedin-post-writer` / `ai-image-carousel-prompt-gen`) changes — none. Inline content is delivered via the existing prompt body (LLM uses context; no SKILL.md change).
- Force-OFF behavior — preserved exactly (text drafts + plugin-decided format still run `/linkedin-gen`).

## Risk register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Empty `brief` degrades LinkedIn caption | Low | `buildCarouselCaption` already falls back to slides + blog; observed `brief_hook:null` today anyway |
| `/carousel-gen` ignores inline content, double-counts URL+content | Low | Prompt labels content as primary, URL as supplementary; carousel-gen already tolerant; monitor 1–2 drafts |
| Regression on force-OFF path | Med | Phase C test asserts `invokePlugin` still called when force OFF; full suite re-run |
| Hidden consumer of `/linkedin-gen` text output for carousel | Low | Verified in `persistAndRoute`: carousel caption/hashtags/link all backend-built, brief-optional |
