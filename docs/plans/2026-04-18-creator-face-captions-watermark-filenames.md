> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Creator Face, Image Captions, Watermark, Branded Filenames

Cross-repo spec: Portfolio_v2 backend (Laravel) + Portfolio_v2 frontend (Vue) + article-content-writer plugin. Four linked image-generation improvements tied through a single new `creator_brand` Settings group.

---

## Design

### User Concerns

1. **Creator face missing in thumbnails** — Even with `creator-face.png` profile photo configured, generated cover thumbnails don't render Ali's face. VDs describe a fictional "Southeast Asian developer" instead of Ali.
2. **No image captions** — Generated images lack context labels; readers can't tell what the image represents without reading surrounding paragraphs.
3. **No brand watermark** — Need `creator-brand.png` logo + `alisadikinma.com` tagline at 30% opacity centered, configurable via profile UI (DB, not hardcoded).
4. **Generic filenames** — Current pattern `1234567890_abc.jpg` provides zero SEO value; need `alisadikinma-{seo-keyword}-{segment}.png`.

### Root Cause Analysis

**Concern #1 root cause** — [CoverBrandingEnhancer.php:12-19](../../backend/app/Services/CoverBrandingEnhancer.php#L12-L19) defines `HUMAN_KEYWORDS` that excludes common article subjects (`developer`, `engineer`, `designer`, `marketer`, `user`, `student`, `entrepreneur`). Articles like "10 Best Vibe Coding Tools" never match → creator face URL never prepended → GeminiGen invents a face. Auto-inject also skips the sync VD rewrite (`ArticleGenerationService::rewriteVisualDirectionForFace` at [line 164](../../backend/app/Services/ArticleGenerationService.php#L164)) that prevents demographic mismatch between VD ("young developer") and reference photo (bald older man).

### Locked Decisions

| # | Decision | Choice |
|---|---|---|
| 1 | Creator face trigger | Always on cover + plugin-controlled inline via `needs_creator_face` flag + sync VD rewrite on every auto-inject |
| 2 | Watermark mode | Prompt-injection (backend-appended at dispatch time, DB-driven) |
| 3 | Caption source | New dedicated `caption` field authored by `/article-images`. **Cover** = article title (exact or light SEO paraphrase). **Inline** = 5-12 words, short/tight, supports context only, must NOT duplicate title or nearest H2 heading |
| 4 | Config storage | New `creator_brand` Settings group + dedicated card on AboutSettings.vue |
| 5 | Injection point | Backend appends watermark at dispatch time (plugin stays creative-focused). **Applies to BOTH cover and inline** — brand consistency across article |
| 6 | Filename format | `alisadikinma-{seo-keyword}-{segment-label}.png` — all parts DB-driven |

### New DB State — `creator_brand` Settings Group

| key | default |
|---|---|
| `creator_brand_logo` | null (user uploads via AboutSettings) |
| `creator_brand_tagline` | `alisadikinma.com` |
| `creator_brand_slug` | `alisadikinma` |
| `watermark_opacity` | `0.30` |
| `watermark_enabled` | `false` (opt-in) |

### New Schema Fields

**`image_prompts[i].caption`** (plugin-authored; **cover** = article title; **inline** = 5-12 word supporting context, must not duplicate title or H2 heading)
**`image_prompts[i].needs_creator_face`** (plugin-authored boolean, inline only)
**`image_generation_jobs.planned_filename`** (backend-computed, surfaced in webhook rename)

---

## Implementation Plan

### Architecture Context

Pulled from `CLAUDE.md`:

- **Backend:** Laravel 12, PHP 8.2, MySQL 8, Pest for tests
- **Frontend:** Vue 3.5 + `<script setup>`, Pinia 3, Tailwind CSS 4, TanStack Query 5.90
- **Plugin:** article-content-writer v2.0.0 at `D:\Projects\claude-plugin\article-content-writer\`
- **Existing CoverBrandingEnhancer** at [backend/app/Services/CoverBrandingEnhancer.php](../../backend/app/Services/CoverBrandingEnhancer.php) — extend in-place, don't fork
- **Existing sync VD rewrite** at `ArticleGenerationService::rewriteVisualDirectionForFace` — invoke from backend context, not just frontend route
- **Existing settings pattern** — `Setting::where('group','X')->where('key','Y')->value('value')` (see [CoverBrandingEnhancer.php:79-81](../../backend/app/Services/CoverBrandingEnhancer.php#L79-L81))
- **Existing test pattern** — [backend/tests/Feature/ImageGenerationTriggerForIdeaTest.php](../../backend/tests/Feature/ImageGenerationTriggerForIdeaTest.php) (uses Mockery aliases for statics)
- **Existing admin settings UI** at [frontend/src/views/admin/AboutSettings.vue](../../frontend/src/views/admin/AboutSettings.vue) with file uploader for `profile_photo` — reuse pattern for `creator_brand_logo`
- **Existing primary keyword** at `generated_article.prep_data.research.keyword` (from `/article-prep`)

### Tech Stack

No new dependencies. Uses:
- Laravel Eloquent (`Setting` model, `Str::slug()` helper for keyword slugification)
- `Illuminate\Support\Facades\Http` for GeminiGen (already used)
- Vue 3 Composition API (`ref`, `computed`, `<script setup>`)
- Existing `useContentEngine.js` composable extended with new setting loaders
- Existing `SettingsController` — no new endpoints; `creator_brand` group works with existing `GET /api/settings/{group}` route

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Profile photo (creator face) | `settings` where group=about, key=profile_photo | `CoverBrandingEnhancer::getCreatorFaceUrl()` | Yes | Extend — always-inject on cover |
| Sync VD rewrite | `ArticleGenerationService::rewriteVisualDirectionForFace` | Method call | Yes | Invoke from `CoverBrandingEnhancer::enhance()` on auto-inject |
| Primary SEO keyword | `generated_article.prep_data.research.keyword` | Array read | Yes | Read in filename helper |
| Creator brand settings | `settings` where group=creator_brand, 5 keys | `Setting::where('group','creator_brand')` | No | Seed via new `CreatorBrandSettingsSeeder` |
| Planned filename on job | `image_generation_jobs.planned_filename` column | `ImageGenerationJob` model | No | Add nullable column via migration |
| Branded filename compute | `Str::slug()` of brand-slug + keyword + segment-label | New helper in `ImageGenerationService` | No | Create `buildBrandedFilename()` method |
| Caption field | `image_prompts[i].caption` | Additive JSON — no migration | No | Plugin authors; backend reads in `insertInlineImage` |
| needs_creator_face flag | `image_prompts[i].needs_creator_face` | Additive JSON | No | Plugin authors; backend reads in `CoverBrandingEnhancer::enhance()` |
| Watermark instruction append | `Setting` creator_brand group | Direct read in `CoverBrandingEnhancer::enhance()` | Yes (table) | New method `buildWatermarkInstruction()` |
| Watermark logo in `file_urls` | `$prompt['file_urls'][]` array | Already flows to `ImageGenerationService::queue()` multipart | Yes | Append logo URL to array |
| Admin UI — creator_brand card | `settings/about` and `settings/{group}` routes | `usePostgresApi` pattern from AboutSettings.vue | Partial | New card + new route `GET|PUT /api/settings/creator-brand` may already work via generic group route |
| Branded lightbox download | `lightboxFilename` computed in ImageGeneration.vue | `BaseLightbox` download prop | Yes | Swap pattern to branded format |
| Figcaption render | `<figcaption>` in `insertInlineImage` | `ImageGenerationService::insertInlineImage()` | Yes | Swap source from `insert_after_heading` → `caption` |

### Files Touched

| Layer | File | Action |
|---|---|---|
| Backend | `database/seeders/CreatorBrandSettingsSeeder.php` | NEW |
| Backend | `database/migrations/2026_04_18_000000_add_planned_filename_to_image_generation_jobs_table.php` | NEW |
| Backend | `database/seeders/DatabaseSeeder.php` | MODIFY — register new seeder |
| Backend | `app/Models/ImageGenerationJob.php` | MODIFY — add `planned_filename` to `$fillable` |
| Backend | `app/Services/CoverBrandingEnhancer.php` | MODIFY — always-inject cover, VD rewrite hook, watermark append, keyword expansion |
| Backend | `app/Services/ImageGenerationService.php` | MODIFY — `buildBrandedFilename()` helper, thread `planned_filename` through queue → webhook → download, figcaption source swap |
| Plugin | `skills/article-images/SKILL.md` | MODIFY — add `caption` + `needs_creator_face` to output schema |
| Frontend | `src/views/admin/AboutSettings.vue` | MODIFY — Creator Brand card |
| Frontend | `src/composables/useSettings.js` (or existing) | MODIFY — load creator_brand group |
| Frontend | `src/views/admin/ImageGeneration.vue` | MODIFY — caption input + branded lightbox filename |
| Frontend | `src/components/admin/ImageConfigModal.vue` | MINOR — caption preview in pills |
| Tests | `backend/tests/Feature/CoverBrandingAutoInjectTest.php` | NEW |
| Tests | `backend/tests/Feature/BrandedFilenameTest.php` | NEW |
| Tests | `backend/tests/Feature/WatermarkInjectionTest.php` | NEW |
| Tests | `backend/tests/Feature/CreatorBrandSettingsTest.php` | NEW |

### Design Deliverables

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| 1 | Seeder + migration | n/a (backend) | Tests + migrate:fresh |
| 2 | Filename helper + figcaption swap | n/a (backend) | Tests |
| 3 | Always-inject face + VD rewrite | n/a (backend) | Tests |
| 4 | Watermark append | n/a (backend) | Tests |
| 5 | Plugin SKILL.md update | n/a (docs) | Lint + schema check |
| 6 | AboutSettings Creator Brand card | UI spec: card layout, label tokens, logo uploader, slider, toggle, validation states | Design system compliance + visual check |
| 7 | ImageGen caption input + branded dl | UI spec: inline editable input under VD, download filename tooltip | Design system compliance + visual check |
| 8 | E2E | n/a | Full pipeline run |

---

## Phase 1: Backend Foundation — Settings Seeder + Migration

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/database/seeders/CreatorBrandSettingsSeeder.php`
- Create: `backend/database/migrations/2026_04_18_000000_add_planned_filename_to_image_generation_jobs_table.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `backend/app/Models/ImageGenerationJob.php`
- Test: `backend/tests/Feature/CreatorBrandSettingsTest.php`

**Steps:**

1. Write failing test for `CreatorBrandSettingsSeeder` producing 5 rows in `settings` table with group='creator_brand' and correct defaults. Expected error: `Illuminate\Database\Eloquent\ModelNotFoundException` or assertion failure "creator_brand_logo setting not found".
2. Run `php artisan test --filter=CreatorBrandSettingsTest`, confirm it fails for the expected reason.
3. Create the migration adding `planned_filename` (string, nullable, 255 chars) column to `image_generation_jobs` table.
4. Create `CreatorBrandSettingsSeeder` with `updateOrCreate` pattern for idempotency (5 keys: logo=null, tagline='alisadikinma.com', slug='alisadikinma', watermark_opacity='0.30', watermark_enabled='false').
5. Register seeder in `DatabaseSeeder::run()`.
6. Add `planned_filename` to `ImageGenerationJob` model `$fillable` array.
7. Run `php artisan migrate` then `php artisan db:seed --class=CreatorBrandSettingsSeeder`.
8. Run tests, confirm pass.
9. Commit: `feat(content-engine): seed creator_brand settings + planned_filename column`.

**Verification:**
- [ ] `php artisan migrate:status` shows new migration applied
- [ ] `SELECT * FROM settings WHERE group='creator_brand'` returns 5 rows with correct defaults
- [ ] `php artisan test --filter=CreatorBrandSettingsTest` passes
- [ ] `DESCRIBE image_generation_jobs;` shows `planned_filename` column (nullable varchar 255)
- [ ] No placeholder/TODO comments in new files
- [ ] Seeder is idempotent (run twice → same 5 rows, no duplicates)

---

## Phase 2: Backend — Branded Filename Helper + downloadAndStore Extension + Figcaption Swap

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php`
- Test: `backend/tests/Feature/BrandedFilenameTest.php`

**Steps:**

1. Write failing test `buildBrandedFilename_uses_brand_slug_keyword_and_segment_label()` — builds filename from creator_brand settings + `generated_article.prep_data.research.keyword` + segment index. Expected: `'alisadikinma-vibe-coding-tools-cover.png'` for segment index 0 and `'alisadikinma-vibe-coding-tools-body-1.png'` for segment index 1. Expected error: `Error: Call to undefined method App\Services\ImageGenerationService::buildBrandedFilename()`.
2. Run test, confirm fail.
3. Implement `buildBrandedFilename(ContentIdea $idea, int $segmentIndex): string`:
   - Read `Setting::where('group','creator_brand')->where('key','creator_brand_slug')->value('value')` (fallback `'alisadikinma'`)
   - Read `$idea->generated_article['prep_data']['research']['keyword'] ?? $idea->title` → `Str::slug()`
   - Segment label: `$segmentIndex === 0 ? 'cover' : "body-{$segmentIndex}"`
   - Compose: `"{$brandSlug}-{$keywordSlug}-{$label}.png"`
   - Collision handling: if `ImageGenerationJob::where('planned_filename', $candidate)->exists()` append `-v2`, `-v3`, etc.
4. Run test, confirm pass.
5. Write failing test `triggerForIdea_stores_planned_filename_on_job()`. Expected error: assertion "planned_filename expected 'alisadikinma-...' got null".
6. Modify `ImageGenerationService::queue()` to accept new optional `?string $plannedFilename = null` param and store it on the `ImageGenerationJob::create` call.
7. Modify `ImageGenerationService::triggerForIdea()` to compute filename via `buildBrandedFilename($idea, $i)` and pass it to `queue()`.
8. Run test, confirm pass.
9. Write failing test `downloadAndStore_uses_planned_filename_from_job()`. Expected error: filename mismatch (test expects `alisadikinma-...` pattern, gets timestamp pattern).
10. Modify `downloadAndStore(string $imageUrl, ?string $customFilename = null): ?string` — if `$customFilename` provided, use `"blog-images/{$customFilename}"` instead of timestamp pattern. Preserve timestamp fallback for legacy blog pipeline.
11. Modify `handleWebhook()` → look up `$job->planned_filename`, pass to `downloadAndStore()`.
12. Modify `queue()` instant path (status=2) — also pass `$plannedFilename` to `downloadAndStore()`.
13. Run tests, confirm pass.
14. Write failing test `insertInlineImage_uses_caption_field_when_present()`. Expected error: figcaption text mismatch.
15. Modify `insertInlineImage()` — change figcaption source from `$job->insert_after_heading` to the segment's caption from `content_idea.generated_article.image_prompts[]`. Lookup by `$job->uuid` → find segment → read `caption` (fallback to `concept`, then to `insert_after_heading`).
16. Run tests, confirm pass.
17. Commit: `feat(content-engine): branded filenames + caption-driven figcaptions`.

**Verification:**
- [ ] `php artisan test --filter=BrandedFilenameTest` passes (all 4 tests)
- [ ] `php artisan test --filter=ImageGenerationTriggerForIdeaTest` still passes (regression check)
- [ ] `tinker` test: generate idea → dispatch → check `image_generation_jobs.planned_filename` populated
- [ ] Filename for cover matches `alisadikinma-{slug(research.keyword)}-cover.png`
- [ ] Filename for inline-1 matches `alisadikinma-{slug(research.keyword)}-body-1.png`
- [ ] Collision (same filename exists) → appends `-v2`
- [ ] Figcaption sources from `caption` field when present, falls back gracefully
- [ ] No placeholder/TODO comments
- [ ] Legacy blog pipeline (no idea context) still works with timestamp filename

---

## Phase 3: Backend — CoverBrandingEnhancer Always-Inject Face + VD Rewrite Hook

**Estimated time:** 12 minutes

**Files:**
- Modify: `backend/app/Services/CoverBrandingEnhancer.php`
- Test: `backend/tests/Feature/CoverBrandingAutoInjectTest.php`

**Steps:**

1. Write failing test `cover_always_gets_creator_face_even_without_human_keyword()` — VD says "futuristic dashboard with holographic panels" (no human keyword), expect face URL prepended on cover, NOT prepended on inline. Expected error: assertion "face_refs array empty on cover".
2. Run test, confirm fail.
3. Modify `CoverBrandingEnhancer::enhance()` — split face-injection logic:
   - If `$prompt['type'] === 'cover'` AND `getCreatorFaceUrl()` returns URL → always prepend (skip keyword gate)
   - If `$prompt['type'] === 'inline'` AND (`$prompt['needs_creator_face'] === true` OR `hasHumanKeyword($scanText)`) → prepend
4. Expand `HUMAN_KEYWORDS` to include: `developer`, `engineer`, `designer`, `marketer`, `user`, `student`, `entrepreneur`, `coder`, `programmer`, `executive`, `pengembang`, `perancang`, `mahasiswa`, `wirausaha`.
5. Run test, confirm pass.
6. Write failing test `auto_inject_triggers_vd_rewrite_when_enabled()` — mock `ArticleGenerationService::rewriteVisualDirectionForFace` to return `{success: true, rewritten_vd: 'A middle-aged bald Asian man in navy blazer...'}`. Expect `$prompt['visual_direction']` updated and `$prompt['visual_direction_original']` preserved. Expected error: assertion "visual_direction unchanged".
7. Run test, confirm fail.
8. Inject `ArticleGenerationService` via constructor DI. Add `app(ArticleGenerationService::class)` fallback.
9. Add logic after face prepend: if `$prompt['visual_direction_original']` is empty AND creator face was just prepended, call `rewriteVisualDirectionForFace($prompt['visual_direction'], $creatorUrl, [...])`. On success: `$prompt['visual_direction_original'] = $oldVd; $prompt['visual_direction'] = $newVd`. On failure: log warning, leave VD untouched (non-blocking).
10. Run test, confirm pass.
11. Write test `vd_rewrite_failure_is_non_blocking()` — mock rewrite to return `{success: false}`. Expect face still prepended, VD unchanged, no exception thrown.
12. Run test, confirm pass.
13. Commit: `feat(content-engine): always-inject creator face on cover + VD rewrite auto-hook`.

**Verification:**
- [ ] `php artisan test --filter=CoverBrandingAutoInjectTest` passes (all 3 tests)
- [ ] `php artisan test --filter=ImageGenerationTriggerForIdeaTest` still passes (regression)
- [ ] Cover images always get `face_refs[0] = creator_face_url` when profile_photo set
- [ ] Inline images only get face on `needs_creator_face=true` or expanded keyword match
- [ ] VD rewrite fires on auto-inject, preserves original via `visual_direction_original`
- [ ] VD rewrite failure logged as warning, does not block generation
- [ ] Expanded keyword list matches `developer` case-insensitively
- [ ] No placeholder/TODO comments

---

## Phase 4: Backend — CoverBrandingEnhancer Watermark Prompt Append

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/CoverBrandingEnhancer.php`
- Test: `backend/tests/Feature/WatermarkInjectionTest.php`

**Steps:**

1. Write failing test `watermark_appended_on_cover_and_inline_when_enabled()` — set `watermark_enabled=true`, `watermark_opacity=0.30`, `creator_brand_tagline='alisadikinma.com'`, `creator_brand_logo='/storage/creator-brand.png'`. Run `enhance()` on a cover prompt AND an inline prompt separately. Expect BOTH `$prompt['prompt_text']` values contain `"30% opacity"` and `"alisadikinma.com"`. Expect BOTH `$prompt['file_urls']` arrays contain the logo URL. Expected error: assertion "inline prompt_text does not contain watermark instruction" (because existing early-return currently skips non-cover entirely).
2. Run test, confirm fail.
3. Add `buildWatermarkInstruction(string $tagline, float $opacity): string` private method returning the formatted prompt append string (see Design section for template).
4. Add logic in `enhance()` after existing title/face logic — read 4 creator_brand settings; if `watermark_enabled === 'true'` AND logo URL resolves, append watermark instruction + push logo URL into `$prompt['file_urls'][]`.
5. **Apply watermark to BOTH cover and inline** — brand consistency across the full article. The existing early-return `if (($prompt['type'] ?? null) !== 'cover') return $prompt;` must be removed/restructured so it ONLY guards title-inject + face-inject. Watermark path runs for every type.
6. Run test, confirm pass.
7. Write test `watermark_skipped_when_disabled()` — `watermark_enabled=false`. Expect prompt_text unchanged, file_urls unchanged.
8. Run test, confirm pass.
9. Write test `watermark_skipped_when_logo_missing()` — `watermark_enabled=true` but `creator_brand_logo=null`. Expect no watermark append, log warning emitted.
10. Run test, confirm pass.
11. Restructure `enhance()` method order so watermark logic runs on both cover and inline — extract title-inject + face-inject into separate helpers called conditionally.
12. Run full test suite, confirm no regressions.
13. Commit: `feat(content-engine): DB-driven watermark prompt injection`.

**Verification:**
- [ ] `php artisan test --filter=WatermarkInjectionTest` passes (all 3 tests)
- [ ] `php artisan test --filter=CoverBrandingAutoInjectTest` still passes
- [ ] `php artisan test --filter=ImageGenerationTriggerForIdeaTest` still passes
- [ ] When `watermark_enabled=true`: prompt_text contains opacity percentage + tagline, file_urls contains logo URL
- [ ] When `watermark_enabled=false`: no watermark mutation to prompt
- [ ] Missing logo: warning logged, generation proceeds
- [ ] Watermark applies to cover AND inline (not cover-only)
- [ ] No placeholder/TODO comments

---

## Phase 5: Plugin — article-images SKILL.md Schema Update

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-images\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\references\image-prompt-guide.md` (if caption guidance belongs there)

**Steps:**

1. Write a regression check test (bash) that greps `SKILL.md` for both `caption` and `needs_creator_face` in the output schema section. Expected error: `grep: pattern not found`.
2. Run grep, confirm fail.
3. Modify `SKILL.md` Section 7 (Output Format) — extend JSON example:
   - Cover: `"caption": "<article title, verbatim or SEO-paraphrased>"`
   - Inline: `"caption": "<5-12 words, supporting context, no duplication of title/heading>"`
   - Add `"needs_creator_face": <true|false>` for inline only (cover always gets face via backend)
4. Modify SKILL.md Section 5 (Authoring Rules) — add caption authoring rule with **per-type differentiation**:
   - **Cover caption** = the article title (exact string from `generated_article.title`), optionally lightly paraphrased to include primary SEO keyword if not already present. Purpose: caption on blog cover reinforces the article's core promise.
   - **Inline caption** = 5-12 words, short / clear / dense. Supporting context ONLY — explains what the image shows so the reader understands the scene at a glance. HARD RULES:
     - Must NOT duplicate the article title
     - Must NOT duplicate the nearest H2 heading (the `insert_after_heading` value)
     - Must NOT describe lighting, camera, or mood (those belong in `prompt`)
     - Must be in the article's language
     - Examples of good inline captions: "Installing Claude Code from the VS Code extensions panel.", "Terminal output after the first successful generation.", "Dashboard view during the third tutorial step."
     - Examples of bad inline captions: "10 Best Vibe Coding Tools 2026" (duplicates title), "Cinematic shot of developer at glass desk" (describes prompt), "Getting Started" (matches H2)
5. Modify SKILL.md Section 5 — add `needs_creator_face` rule:
   - Set `true` when the visual includes the reader-as-protagonist persona (tutorial scenes where "you" are the subject)
   - Set `false` for abstract scenes, product shots, charts, environments without people
6. Modify SKILL.md Section 6 (Cover) — remove the in-prompt brand-logo instruction since backend handles watermark now. Keep title/subtitle/key-visual/composition.
7. Modify SKILL.md Section 7 field requirements table — add `caption` (required, all types) and `needs_creator_face` (required, inline only, boolean) rows.
8. Re-run grep, confirm pass.
9. Commit in plugin repo: `feat(article-images): add caption + needs_creator_face to output schema`.

**Verification:**
- [ ] `grep -c '"caption"' SKILL.md` returns ≥ 2 (cover + inline examples)
- [ ] `grep -c '"needs_creator_face"' SKILL.md` returns ≥ 1 (inline example)
- [ ] Authoring rules document caption length + content guidance
- [ ] Field requirements table updated
- [ ] Cover section no longer prescribes logo-in-prompt (backend owns watermark)
- [ ] No placeholder/TODO comments

---

## Phase 6 (UI): Frontend — AboutSettings.vue Creator Brand Card

**Estimated time:** 15 minutes

**Files:**
- Modify: `frontend/src/views/admin/AboutSettings.vue`
- Modify: `frontend/src/composables/useSettings.js` or `useAboutSettings.js`

**Design Deliverable:**

UI spec (aligns with existing AboutSettings.vue patterns):
- Card heading: "Creator Brand — Image Watermark" with subheading "Applied automatically to all generated images"
- Field group inside card:
  - Logo upload (reuses existing `profile_photo` uploader pattern; preview 96×96 thumbnail; drag-drop + click-to-browse)
  - Tagline text input (single line, default `alisadikinma.com`, maxLength 60)
  - Brand slug text input (single line, default `alisadikinma`, validation: `/^[a-z0-9-]+$/`, used in filenames)
  - Opacity slider (0–100, step 5, current value badge, default 30, shows percentage)
  - Enable toggle (switch component, default off, with helper text "Watermark will be applied to every generated image")
- Tokens: uses same `bg-white dark:bg-neutral-800 rounded-2xl border` card pattern, `amber-500` accent for active states (matches existing admin UI)
- Validation states: red outline + error text on invalid slug; disabled save button during upload
- No new dependencies

**Steps:**

1. Write failing Vitest test `AboutSettings.test.js` — mount AboutSettings.vue, expect card with heading "Creator Brand" to render. Expected error: "element with text 'Creator Brand' not found".
2. Run test, confirm fail.
3. Extend settings composable to load `creator_brand` group via existing `GET /api/settings/{group}` route (`useSettings.js` or add method to `useAboutSettings.js`).
4. Add reactive state to AboutSettings.vue: `creatorBrand = ref({ logo: null, tagline: '', slug: '', opacity: 0.30, enabled: false })`.
5. In `onMounted`, fetch `creator_brand` settings group → hydrate `creatorBrand.value`.
6. Add template: new card section between existing About card and any footer, following token spec above.
7. Wire logo upload to `POST /api/admin/settings/about` with `_method=PUT` (reuse profile_photo pattern — settings UI already sends all settings together, so `creator_brand_*` just joins the payload under its group).
8. Add slug validation (pattern match on blur, show inline error).
9. Add opacity slider bound to `creatorBrand.opacity`, display as percentage.
10. Add enable toggle bound to `creatorBrand.enabled`.
11. Implement save handler — serialize all 5 fields into settings payload, POST to settings endpoint. Confirm existing `SettingsController@update` accepts group=creator_brand without changes (it's generic key-value).
12. Run test, confirm pass.
13. Manual browser test: save + refresh, confirm values persist.
14. Commit: `feat(admin): creator brand watermark settings card`.

**Verification:**
- [ ] `npm run test -- AboutSettings` passes
- [ ] Browser: navigate to /admin/settings/about, Creator Brand card visible
- [ ] Upload logo → preview appears → save → refresh → preview still shows logo
- [ ] Change slug to `invalid slug with spaces` → red error below input
- [ ] Change slug to `valid-slug` → error clears, save enables
- [ ] Opacity slider shows percentage badge (0–100%)
- [ ] Toggle enable/disable + save → refresh → value persists
- [ ] Database: `SELECT * FROM settings WHERE group='creator_brand'` reflects UI changes
- [ ] No placeholder/TODO comments
- [ ] Matches existing AboutSettings.vue design tokens (amber accent, rounded-2xl cards, neutral palette)

---

## Phase 7 (UI): Frontend — ImageGeneration.vue Caption Input + Branded Download Filename

**Estimated time:** 12 minutes

**Files:**
- Modify: `frontend/src/views/admin/ImageGeneration.vue`
- Modify: `frontend/src/components/admin/ImageConfigModal.vue` (minor)

**Design Deliverable:**

UI spec:
- Caption input: directly below Visual Direction textarea in the left column of each segment card
  - Label: `CAPTION` (same styling as `VISUAL DIRECTION` — `text-[11px] font-semibold uppercase tracking-wider`)
  - Single-line `<input>` with placeholder **that changes by segment type**:
    - Cover: `Article title (or SEO-paraphrased version)`
    - Inline: `5-12 words of supporting context (e.g. "Terminal output after first successful generation")`
  - Max length: 150 chars; character count shown on focus
  - Auto-save on blur + debounce via existing `scheduleAutoSave()`
  - Helper text differs by type:
    - Cover: "Rendered as the hero caption on the blog post."
    - Inline: "Short figcaption below the image — helps readers understand context at a glance. Do not repeat the article title or section heading."
- Branded download filename shown on lightbox — append to lightbox title: `(Download: alisadikinma-vibe-coding-tools-cover.png)` in small text
- ImageConfigModal: add caption preview chip when caption is set, styled same as existing Notes chip

**Steps:**

1. Write failing Vitest test `ImageGeneration.test.js` — mount with idea fixture containing `image_prompts[0].caption = "Test caption"`. Expect input with value "Test caption" to render. Expected error: "input not found".
2. Run test, confirm fail.
3. Add `caption` to the segment mapping in `initSegments()` — `caption: img.caption || ''`.
4. Add `caption` to `persistDraft()` payload — include in the mapped prompt object.
5. Add caption `<input>` to template, below Visual Direction, bound to `seg.caption`, `@input="scheduleAutoSave"`.
6. Run test, confirm pass.
7. Write failing test `lightboxFilename_uses_branded_pattern()` — with idea keyword "Vibe Coding Tools" + brand slug "alisadikinma" + segment index 0 (cover). Expect `lightboxFilename` = `'alisadikinma-vibe-coding-tools-cover.png'`. Expected error: filename mismatch.
8. Modify `lightboxFilename` computed — read `idea.value.generated_article.prep_data.research.keyword` and brand slug from settings (fetched via existing settings composable at mount). Slugify keyword. Compose `{brandSlug}-{keywordSlug}-{segmentLabel}.png`.
9. Run test, confirm pass.
10. Load brand slug at mount via settings composable (reuse cached value if available; otherwise call `GET /api/settings/creator_brand`).
11. Add caption chip to ImageConfigModal reference preview section (below existing chips) — 4 lines, reuse existing chip styling.
12. Manual browser test: generate image → click to open lightbox → confirm download filename matches pattern.
13. Commit: `feat(admin): editable image captions + branded download filenames`.

**Verification:**
- [ ] `npm run test -- ImageGeneration` passes (both new tests)
- [ ] Browser: open /admin/content-engine/{id}/images → caption input visible below VD on every segment
- [ ] Type in caption → auto-saves after 2s → refresh → value persists
- [ ] Click generated image → lightbox opens → download button filename = `alisadikinma-{slug(keyword)}-cover.png` (or body-N)
- [ ] Download works, filename on disk matches
- [ ] If keyword missing (fallback path), filename falls back to article slug
- [ ] No placeholder/TODO comments
- [ ] Caption chip appears in config modal when caption is set
- [ ] Matches existing UI tokens (neutral palette, amber accents)

---

## Phase 8: E2E Verification — Full Pipeline Run

**Estimated time:** 15 minutes

**Files:** None modified — verification only.

**Steps:**

1. In admin: navigate to `/admin/settings/about` → upload `creator-brand.png` as brand logo → set opacity 30% → enable watermark → save.
2. Verify `SELECT * FROM settings WHERE group='creator_brand'` has logo URL + enabled=true.
3. Create a new content idea with a human-focused topic (e.g., "How Developers Use Claude Code Daily") → start research → wait for article_ready.
4. Approve article → trigger image generation (Gate 2).
5. Inspect `generated_article.image_prompts` in DB:
   - Cover: `face_refs[0]` should be profile_photo URL (auto-injected)
   - Cover: `visual_direction` should describe Ali (auto-rewritten)
   - **All prompts (cover + every inline): `prompt_text` contains watermark instruction (30% opacity, alisadikinma.com) and `file_urls` contains brand logo URL**
   - Cover: `caption` = article title (exact or close paraphrase)
   - Inline: `caption` = 5-12 words, does NOT match article title, does NOT match `insert_after_heading`
   - Inline prompts: should have `needs_creator_face` boolean
6. Wait for GeminiGen webhooks → verify `image_generation_jobs.planned_filename` column populated with branded filenames.
7. Check `storage/app/public/blog-images/` → confirm files named `alisadikinma-how-developers-use-claude-code-daily-cover.png` etc.
8. Visual inspection of generated cover: confirm watermark visible (30% opacity, centered logo + tagline) and face resembles profile photo.
9. Navigate to published blog post → confirm `<figcaption>` under each image renders the authored caption (not the heading text).
10. Open lightbox on Image Gen page → verify download filename matches branded pattern.
11. Run full backend test suite: `php artisan test` → all green.
12. Run full frontend test suite: `npm run test` → all green.
13. Run route sanity: `php artisan route:list | grep settings` → confirm creator_brand group accessible via existing routes.

**Verification:**
- [ ] Cover image visibly contains creator face AND watermark
- [ ] Body images visibly contain watermark (every single one, not just cover)
- [ ] Body images do NOT contain creator face unless `needs_creator_face=true` flag set
- [ ] Cover caption on blog matches article title
- [ ] Inline captions are short (5-12 words) and do NOT repeat article title or the section heading above them
- [ ] All `storage/app/public/blog-images/*.png` follow `alisadikinma-*-*.png` pattern
- [ ] Each generated image has `<figcaption>` on blog detail page rendering the caption
- [ ] Lightbox download button produces branded filename
- [ ] `image_generation_jobs.planned_filename` column populated for new rows
- [ ] `php artisan test` all green (no regressions)
- [ ] `npm run test` all green
- [ ] Watermark disabled toggle → subsequent generations omit watermark instruction
- [ ] No console errors, no Laravel log errors
- [ ] Manual verification screenshot attached to commit or PR

---

## Phase Dependency Graph (for gaspol-parallel)

```
Phase 1 (seeder + migration)
   ├─→ Phase 2 (filename helper + downloadAndStore + figcaption)
   ├─→ Phase 3 (always-inject face + VD rewrite)
   ├─→ Phase 4 (watermark append)
   └─→ Phase 6 (AboutSettings UI — reads seeded settings)

Phase 5 (plugin SKILL.md) — INDEPENDENT, can run parallel to 2/3/4/6

Phase 2 ┐
Phase 5 ┴─→ Phase 7 (ImageGen UI — needs filename helper + caption field)

All phases → Phase 8 (E2E)
```

**Parallelization opportunities:**
- Phases 2, 3, 4 can run in parallel after Phase 1 (touch different methods in same file — coordinate via separate commits)
- Phase 5 can run in parallel to Phases 2, 3, 4, 6 (different repo)
- Phases 6 and 7 depend on earlier backend work but are independent of each other

**Recommended sequential path (if running single-threaded):**
Phase 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8

**Recommended parallel path (gaspol-parallel):**
- Wave 1: Phase 1 alone (blocking)
- Wave 2: Phases 2, 3, 4, 5 in parallel (Phase 5 in plugin repo, Phases 2/3/4 in backend — merge-coordinate)
- Wave 3: Phase 6 alone
- Wave 4: Phase 7 alone
- Wave 5: Phase 8 alone

---

## Anti-Placeholder Contract

Every data source in this plan is real and verified to exist:

- ✅ `Setting::where('group','creator_brand')` — table exists, seeder creates rows
- ✅ `ArticleGenerationService::rewriteVisualDirectionForFace` — verified at [ArticleGenerationService.php:164](../../backend/app/Services/ArticleGenerationService.php#L164)
- ✅ `generated_article.prep_data.research.keyword` — authored by `/article-prep` per [article-prep/SKILL.md:168](../../../claude-plugin/article-content-writer/skills/article-prep/SKILL.md#L168)
- ✅ `image_prompts[i].file_urls` — already flows to GeminiGen multipart per [ImageGenerationService.php:84-86](../../backend/app/Services/ImageGenerationService.php#L84-L86)
- ✅ `profile_photo` setting — confirmed used at [CoverBrandingEnhancer.php:79](../../backend/app/Services/CoverBrandingEnhancer.php#L79)
- ✅ `SettingsController@update` for group=creator_brand — uses existing generic group pattern
- ✅ `BaseLightbox` download prop consumes `lightboxFilename` — already wired in ImageGeneration.vue

If any of these fail verification during execution, STOP and ask the user before proceeding. Never stub.

---

## Red Flag Self-Check

| Red Flag | Status |
|---|---|
| No Data Integration Map | ✅ Present (13 rows) |
| Phase without Verification | ✅ All 8 phases have verification blocks |
| No reference to CLAUDE.md | ✅ Architecture Context cites root + backend + frontend CLAUDE.md |
| Vague data sources | ✅ Specific table.column / method / field path for every integration |
| No test steps | ✅ TDD-first for every code phase (write test, see fail, implement, see pass) |
| Phase too large (>15 min) | ✅ Longest phase is 15 min (Phase 6 UI); most are 10-12 min |
| Placeholder language | ✅ Anti-Placeholder Contract section confirms real sources |
| TDD Step 1 format | ✅ Every phase step 1 = "Write failing test... Expected error: ..." |
| Design Deliverable for UI phases | ✅ Phases 6 and 7 have UI specs |

---

## Execution Handoff Options

**Option 1: Execute in this session**
> Ready to start Phase 1? Use `gaspol-execute` — per-phase checkpoints + TDD hard gate + anti-placeholder enforcement.

**Option 2: Parallel execution**
> Use `gaspol-parallel` after Phase 1 completes — Phases 2, 3, 4, 5 can run in parallel (dispatch 4 isolated subagents, merge-coordinate on CoverBrandingEnhancer.php via sequential commits).

**Option 3: Separate session**
> Plan saved at `docs/plans/2026-04-18-creator-face-captions-watermark-filenames.md`. Start a new session with `gaspol-execute` pointing at this file.
