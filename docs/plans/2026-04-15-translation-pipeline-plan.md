> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Add a **finalize-stage translation pipeline** to the Content Engine: when user clicks "Approve & Publish" on an Indonesian-primary article, the backend creates the published `Post` + Indonesian `PostTranslation` row synchronously, then triggers `/article-translate` skill (Sonnet) to author the English `PostTranslation` row. On translate failure, post is still published ID-only with `translation_pending=true` and a `ProcessPendingTranslations` cron retries every 5 minutes. Ships the missing post-creation flow the Content Engine has never actually implemented.

Companion design doc: `docs/plans/2026-04-15-translation-pipeline-design.md`.

## Architecture Context

**From Portfolio_v2 CLAUDE.md:**
- Content Idea Pipeline status flow: `draft → researching → article_ready → generating_images → images_ready → completed`
- Split article pipeline (4 CLI calls, uniform Sonnet): `article-prep → article-write → article-score → article-images` via `ArticleGenerationService`
- `approveAndPublish` currently flips status to `completed` but **never creates a `Post` row** — this plan ships that missing logic alongside translation
- Feature-flag pattern from image phase split: `ARTICLE_GEN_USE_IMAGES_PHASE` — will add `ARTICLE_GEN_USE_TRANSLATE_PHASE` for safe rollout
- Plugin location on VPS: `/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/{2.3.0,2.0.0}/`
- Compiled refs on VPS: `/home/claudesn/refs-{prep,write,score,images}.md`
- VPS scheduler **not yet active** — `routes/console.php` has `Schedule::command()` entries but no crontab entry on VPS for `php artisan schedule:run`. Cron work (`ProcessPendingTranslations`) is written now; activation is a separate user step.

**From verified code state:**
- `Post` model + `posts` table (existing) — has `HasSeoFields` trait, SoftDeletes, `translations()` hasMany relation
- `PostTranslation` model uses column name `language` (NOT `locale`) — fillable: `post_id, language, title, slug, excerpt, content, meta_title, meta_description, meta_keywords, og_title, og_description, canonical_url, ai_summary, schema_markup, faq_schema`
- `ContentIdeaController::approveAndPublish` ([line 701](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php#L701)) is near-empty — will be rewritten
- `ArticleGenerationService::triggerImages()` ([newly added](backend/app/Services/ArticleGenerationService.php)) is the exact pattern to mirror for `triggerTranslate()`
- `scripts/compile-references.sh` builds 4 bundles — will add 5th (refs-translate.md)
- `content_ideas.generated_article` JSON contains all data needed to build Post: `title`, `content`, `language`, `keyword`, `framework`, hook metadata, `image_prompts[]` with `prompt`, `concept`, `insert_after_heading`, plus `generated_images` JSON with resolved image URLs

**From in-progress state (Nov 2024):**
- `ContentEngine.vue:901` sets default `configLanguages = ['id']` — plan changes to `['id', 'en']`
- No frontend rewrite beyond that single line + toast handler update in `handlePublish`

## Tech Stack

- Backend: Laravel 12, PHP 8.2, Eloquent upsert via `updateOrCreate`, PHPUnit (feature tests blocked by pre-existing sqlite+MODIFY COLUMN ENUM harness issue — will note in deviations)
- Plugin: Markdown skill files + Bash compile script
- Frontend: Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + Tailwind 4
- VPS: SSH MCP for deployment, Claude CLI (Sonnet) for skill execution
- No new npm/composer dependencies

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Idea source data for Post | `content_ideas.generated_article` JSON | Eloquent `ContentIdea::find($id)` | Yes | Use existing (no schema change) |
| Published Post | `posts` table + `Post` model | `Post::updateOrCreate(['source_idea_id' => $id], [...])` | Partial | Add `source_idea_id` + 3 translation-tracking columns via migration |
| Indonesian translation row | `post_translations` table + `PostTranslation` model (language='id') | `PostTranslation::updateOrCreate([post_id, language], [...])` | Yes | Use existing model, it's just never been used by content engine |
| English translation row | Same as above (language='en') | Created by `/article-translate` skill via `save-translation` automation endpoint | No | Endpoint NEW, row creation NEW |
| Translate skill trigger | `ArticleGenerationService::triggerTranslate()` | NEW service method | No | Create mirroring `triggerImages()` |
| Translate skill definition | `skills/article-translate/SKILL.md` | New 8th skill | No | Create in plugin repo |
| Translate refs bundle | `references/compiled/refs-translate.md` | Built by `compile-references.sh` | No | Add new build target |
| Translation guidelines | `references/translation-guidelines.md` | Source for refs-translate.md | No | Create small (~3KB) reference |
| Env var — refs path | `ARTICLE_GEN_REFS_TRANSLATE` | `config/services.php` | No | Add config key + env entries |
| Env var — model | `ARTICLE_GEN_MODEL_TRANSLATE` (default sonnet) | `config/services.php` | No | Add config key + env entries |
| Feature flag | `ARTICLE_GEN_USE_TRANSLATE_PHASE` (default false) | `config/services.php` | No | Add config key + env entries (safe rollout gate) |
| Automation: fetch post for translation | `GET /api/automation/posts/{id}/for-translation` | NEW route | No | Add to `routes/api.php` + `ContentIdeaController::getPostForTranslation()` (kept close to existing controller for code locality) |
| Automation: save translation | `PUT /api/automation/posts/{id}/save-translation` | NEW route | No | Add route + `saveTranslation()` method |
| Automation: mark translation complete | `POST /api/automation/posts/{id}/translation-complete` | NEW route | No | Add route + `markTranslationComplete()` method |
| Background retry | `ProcessPendingTranslations` artisan command | NEW command | No | Create mirroring `ProcessPendingImages` |
| Cron registration | `Schedule::command('content:process-pending-translations')->everyFiveMinutes()` | `routes/console.php` | Yes (file exists) | Add one line |
| Modal default | `configLanguages.value = ['id', 'en']` | `ContentEngine.vue:901` | Existing var | Change init value |
| Publish toast | `handlePublish` in `ContentEngine.vue` | Existing function | Yes | Branch on `result.data.translation_pending` for toast text |

## Phases

---

### Phase 0: Create plugin feature branch + plan TodoWrite

**Estimated time:** 3 minutes

**Files:** None (git only)

**Steps:**
1. `cd D:\Projects\claude-plugin\article-content-writer && git checkout main && git pull origin main`
2. `git checkout -b feat/translation-pipeline`
3. Create 1:1 TodoWrite entry for every phase in this plan (expected 24 phases)

**Verification:**
- [ ] Branch `feat/translation-pipeline` active on plugin repo
- [ ] TodoWrite has 1:1 phase mapping

---

### Phase 1: Create translation-guidelines.md reference

**Estimated time:** 8 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\references\translation-guidelines.md`

**Steps:**
1. Create ~3KB reference file covering:
   - HTML structure preservation (all `<h1>`/`<h2>`/`<p>`/`<ul>`/`<li>`/`<strong>`/`<em>`/`<a>` tags + attrs INTACT)
   - Preserve code blocks (`<pre>`, `<code>`) verbatim — no translation inside
   - Preserve technical terms (brand names, CLI commands, library names, JSON keys) verbatim
   - Tone match: Indonesian Gen-Z casual → English casual conversational register (NOT formal academic)
   - Handle bucket brigades naturally (English equivalents: "Here's the thing:", "Now check this:", "Listen:")
   - Translate alt text, meta_title (50-60 chars), meta_description (140-160 chars)
   - Keep SEO keyword intent — find closest English equivalent for target_keyword
   - Zero markdown drift (if input is HTML, output is HTML; no accidental markdown injection)
2. Include 2 short mini-examples: one paragraph ID→EN showing tone preservation + HTML intact

**Verification:**
- [ ] File exists, 2-5KB size
- [ ] Contains "HTML structure" + "bucket brigade" + "preserve code" sections
- [ ] No placeholder/TODO markers
- [ ] 2 mini-examples present

---

### Phase 2: Create article-translate skill SKILL.md

**Estimated time:** 12 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\skills\article-translate\SKILL.md`

**Steps:**
1. `mkdir skills/article-translate/`
2. Write SKILL.md with frontmatter:
   ```yaml
   ---
   name: article-translate
   description: "Pipeline-only skill for finalize-stage translation. Reads a published Post from backend, translates title + content (HTML) + SEO meta + image alt text into target locale (default English). Runs on Sonnet with refs-translate.md injected. Part of finalize flow: Gate 2 approve → post created → article-translate → post_translations.en row."
   ---
   ```
3. Sections:
   - **1. Pipeline Flags** — `--post-id`, `--api-url`, `--api-token`, `--idempotency-key`, `--target-locale` (default `en`)
   - **2. Don't Read Reference Files** — refs injected via `refs-translate.md`
   - **3. Read Post Data** — `GET /api/automation/posts/{post_id}/for-translation` → returns `{ title, slug, content, meta_title, meta_description, og_title, og_description, excerpt, ai_summary, image_alt_map: {img_url: alt_text} }`
   - **4. Progress Reporting** — PUT `/api/automation/posts/{post_id}/progress` at 10% (loaded), 40% (translating), 80% (saving), 100% (complete). Progress is posted to `posts` table, not `content_ideas`, via the new automation endpoint
   - **5. Translation Rules** — HTML preservation, technical terms verbatim, Gen-Z casual → casual English, bucket brigade equivalents (rules are in system prompt via refs-translate.md, skill only references)
   - **6. Slug Generation** — English slug = kebab-case of translated title (lowercase, strip punctuation, collapse whitespace)
   - **7. Output Format** — JSON payload:
     ```json
     {
       "idempotency_key": "...",
       "target_locale": "en",
       "translation": {
         "title": "...",
         "slug": "...",
         "excerpt": "...",
         "content": "<p>...</p>",
         "meta_title": "...",
         "meta_description": "...",
         "og_title": "...",
         "og_description": "...",
         "ai_summary": "...",
         "image_alt_map": {"url": "english alt"}
       }
     }
     ```
   - **8. Save via Callback** — `PUT /api/automation/posts/{post_id}/save-translation`
   - **9. Mark Complete** — `POST /api/automation/posts/{post_id}/translation-complete`
   - **10. Error Handling** — progress callback `step='failed'`, do NOT call translation-complete

**Verification:**
- [ ] `skills/article-translate/SKILL.md` exists
- [ ] Frontmatter `name: article-translate` present
- [ ] Sections 1-10 present
- [ ] References `refs-translate.md` (NOT any other refs bundle)
- [ ] Output schema matches `PostTranslation` fillable columns

---

### Phase 3: Update compile-references.sh — add refs-translate.md build

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`

**Steps:**
1. Update header comment: "Combines individual reference .md files into 5 skill-specific bundles"
2. Update output list comment: `refs-{prep,write,score,images,translate}.md`
3. Insert new block AFTER the `refs-images.md` block (before the size report loop):
   ```bash
   # --- refs-translate.md (Finalize: Indonesian → English translation) ---
   TRANSLATE="$OUT_DIR/refs-translate.md"
   cat > "$TRANSLATE" << 'HEADER'
   # Article Generation Reference — Translate (Finalize)

   System prompt reference for the `/article-translate` skill.
   Contains: translation-guidelines (HTML preservation, tone matching, SEO meta rules).
   These references are injected via --append-system-prompt-file. Do NOT read them with the Read tool.
   HEADER

   append_ref "$TRANSLATE" "$REFS_DIR/translation-guidelines.md"
   ```
4. Add `$TRANSLATE` to the size-report loop at the bottom

**Verification:**
- [ ] Script has `refs-translate.md` block
- [ ] `bash scripts/compile-references.sh` exits 0
- [ ] Output shows 5 compiled files including `refs-translate.md`
- [ ] `grep -c "HTML structure\|bucket brigade" references/compiled/refs-translate.md` returns ≥ 2

---

### Phase 4: Compile all reference bundles + size check

**Estimated time:** 2 minutes

**Files:**
- Run: `bash D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`

**Steps:**
1. Run compile script
2. Capture file sizes

**Verification:**
- [ ] All 5 files exist in `references/compiled/`
- [ ] `refs-translate.md` size between 2-8KB (small bundle)
- [ ] Other 4 bundles unchanged from their last sizes

---

### Phase 5: Plugin commit + push

**Estimated time:** 3 minutes

**Files:** All plugin changes from Phases 1-4

**Steps:**
1. `cd D:\Projects\claude-plugin\article-content-writer`
2. `git add skills/article-translate/ references/translation-guidelines.md references/compiled/refs-translate.md scripts/compile-references.sh`
3. Commit:
   ```
   feat: add article-translate skill for finalize-stage ID→EN translation

   - NEW skill article-translate (Sonnet, translates published Post to English)
   - NEW refs-translate.md bundle (~3KB translation-guidelines)
   - compile-references.sh builds 5 bundles now (add refs-translate.md)
   - Preserves HTML structure, code blocks, technical terms; matches Gen-Z tone
   ```
4. Push feature branch to origin (DO NOT merge to main yet; backend + frontend wire-up must land first):
   `git push -u origin feat/translation-pipeline`

**Verification:**
- [ ] `git status` clean on plugin branch
- [ ] Commit exists
- [ ] Remote branch `origin/feat/translation-pipeline` exists

---

### Phase 6: Backend migration — add translation-tracking columns to posts

**Estimated time:** 8 minutes

**Files:**
- Create: `D:\Projects\Portfolio_v2\backend\database\migrations\2026_04_16_000001_add_translation_tracking_to_posts.php`

**Steps:**
1. Create migration via full file path (do not use `artisan make:migration` if date prefix needs precision):
   ```php
   public function up()
   {
       Schema::table('posts', function (Blueprint $table) {
           $table->unsignedBigInteger('source_idea_id')->nullable()->after('id')->index();
           $table->boolean('translation_pending')->default(false)->after('published_at');
           $table->unsignedTinyInteger('translation_attempts')->default(0)->after('translation_pending');
           $table->timestamp('last_translation_attempt')->nullable()->after('translation_attempts');
       });
   }

   public function down()
   {
       Schema::table('posts', function (Blueprint $table) {
           $table->dropIndex(['source_idea_id']);
           $table->dropColumn(['source_idea_id', 'translation_pending', 'translation_attempts', 'last_translation_attempt']);
       });
   }
   ```
2. Verify `posts` table has `published_at` column (it should — part of standard blog schema). If not, adjust `after()` placement accordingly.
3. `D:/xampp/php/php.exe artisan migrate`

**Verification:**
- [ ] `artisan migrate` exits 0
- [ ] `SHOW COLUMNS FROM posts LIKE 'source_idea_id'` returns 1 row
- [ ] All 4 new columns exist and have correct types
- [ ] Migration is reversible (test with `migrate:rollback` then `migrate` on a dev DB snapshot)

---

### Phase 7: Update Post model fillable

**Estimated time:** 3 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Models\Post.php`

**Steps:**
1. Add to `$fillable` array: `'source_idea_id'`, `'translation_pending'`, `'translation_attempts'`, `'last_translation_attempt'`
2. Add to `$casts` array: `'translation_pending' => 'boolean'`, `'last_translation_attempt' => 'datetime'`

**Verification:**
- [ ] Post model fillable includes all 4 new columns
- [ ] Casts include boolean + datetime
- [ ] `tinker`: `Post::factory()->make(['source_idea_id' => 1, 'translation_pending' => true])` does not throw MassAssignmentException

---

### Phase 8: Backend config — add translate keys

**Estimated time:** 4 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\config\services.php`

**Steps:**
1. In `'article_generation'` array, add:
   ```php
   'refs_translate' => env('ARTICLE_GEN_REFS_TRANSLATE', ''),
   'model_translate' => env('ARTICLE_GEN_MODEL_TRANSLATE', 'sonnet'),
   'use_translate_phase' => env('ARTICLE_GEN_USE_TRANSLATE_PHASE', false),
   ```
2. `artisan config:clear`

**Verification:**
- [ ] `config('services.article_generation.refs_translate')` returns empty string by default
- [ ] `config('services.article_generation.model_translate')` returns `'sonnet'`
- [ ] `config('services.article_generation.use_translate_phase')` returns `false`

---

### Phase 9: Backend service — add triggerTranslate() method

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Services\ArticleGenerationService.php`

**Steps:**
1. Add method AFTER `triggerImages()`, mirroring its shape:
   ```php
   public function triggerTranslate(int $postId, string $idempotencyKey, string $targetLocale = 'en'): array
   {
       $prompt = "/article-translate --post-id {$postId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
       $prompt .= " --idempotency-key {$idempotencyKey}";
       $prompt .= " --target-locale {$targetLocale}";

       $model = config('services.article_generation.model_translate', 'sonnet');
       $refsFile = config('services.article_generation.refs_translate', '');

       return $this->executePrompt($prompt, $postId, 'translate', $model, $refsFile);
   }
   ```
2. Verify `executePrompt` signature accepts phase string `'translate'` — it does (phase is just string tag for logging + tmp file naming).

**Verification:**
- [ ] Method exists on service class
- [ ] PHP syntax check clean: `php -l app/Services/ArticleGenerationService.php`
- [ ] `tinker`: `app(ArticleGenerationService::class)->triggerTranslate(1, 'test-key')` returns `['success' => true, 'pid' => ..., 'error' => null]` (SSH/local driver works)

---

### Phase 10: Backend automation endpoint — GET for-translation

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\routes\api.php`
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`

**Steps:**
1. Add route to the authenticated automation group (`Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('automation')`):
   ```php
   Route::get('/posts/{id}/for-translation', [ContentIdeaController::class, 'getPostForTranslation']);
   Route::put('/posts/{id}/save-translation', [ContentIdeaController::class, 'saveTranslation']);
   Route::post('/posts/{id}/translation-complete', [ContentIdeaController::class, 'markTranslationComplete']);
   ```
2. Add method `getPostForTranslation($id)` to `ContentIdeaController`:
   - Eager-load `Post::with('translations')->find($id)`
   - 404 if not found
   - Return the primary-language translation fields (title, slug, content, meta_*, og_*, excerpt, ai_summary) plus a derived `image_alt_map` (iterate the post's content HTML, extract `<img>` src + current alt → map)
   - Response: `{ success: true, data: { post_id, primary_language, title, slug, content, meta_title, meta_description, og_title, og_description, excerpt, ai_summary, image_alt_map } }`

**Verification:**
- [ ] Route registered: `artisan route:list --path=for-translation` shows 1 entry
- [ ] Unauthenticated request returns 401
- [ ] Authenticated request for existing post returns 200 with all 10 expected fields
- [ ] Post with no `translations` rows returns 409 with message "Post has no primary translation to translate from"
- [ ] `image_alt_map` contains every `<img>` in content HTML

---

### Phase 11: Backend automation endpoint — PUT save-translation

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`

**Steps:**
1. Add method `saveTranslation($id, Request $request)`:
   - Validate payload:
     ```php
     $request->validate([
         'target_locale' => 'required|string|in:en,id',
         'translation.title' => 'required|string|max:255',
         'translation.slug' => 'required|string|max:255',
         'translation.content' => 'required|string',
         'translation.meta_title' => 'nullable|string|max:70',
         'translation.meta_description' => 'nullable|string|max:170',
         'translation.og_title' => 'nullable|string|max:100',
         'translation.og_description' => 'nullable|string|max:200',
         'translation.excerpt' => 'nullable|string|max:500',
         'translation.ai_summary' => 'nullable|string',
         'translation.image_alt_map' => 'nullable|array',
     ]);
     ```
   - Load `Post::findOrFail($id)`
   - UPSERT `PostTranslation::updateOrCreate(['post_id' => $post->id, 'language' => $request->target_locale], [...translation fields...])`
   - If `image_alt_map` present, rewrite `alt` attrs in `post.content` HTML for the target locale's `post_translations.content` (not the ID post body)
   - Return `{ success: true, data: { post_id, language: $targetLocale, translation_id: $pt->id } }`

**Verification:**
- [ ] Route registered, PHP syntax clean
- [ ] PUT with valid payload creates/updates `post_translations` row; row count increments for first call, stays same for second
- [ ] Invalid payload (missing content) returns 422
- [ ] Unauthenticated returns 401

---

### Phase 12: Backend automation endpoint — POST translation-complete

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`

**Steps:**
1. Add method `markTranslationComplete($id)`:
   - Load `Post::findOrFail($id)`
   - `$post->update(['translation_pending' => false, 'last_translation_attempt' => now()])`
   - Return `{ success: true, data: { post_id, translation_pending: false } }`

**Verification:**
- [ ] POST sets `translation_pending` = false
- [ ] `last_translation_attempt` updated to current timestamp
- [ ] Returns 200 with expected shape

---

### Phase 13: Backend — rewrite approveAndPublish to create real Post + trigger translate

**Estimated time:** 14 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`

**Steps:**
1. Rewrite `approveAndPublish($id)` with these stages:

   **Stage A — Validate + extract:**
   ```php
   $idea = ContentIdea::findOrFail($id);
   if (!in_array($idea->status, ['article_ready', 'images_ready'])) {
       return response()->json(['success' => false, 'message' => 'Cannot publish in current status.'], 422);
   }
   $article = $idea->generated_article ?? [];
   $primaryLang = $article['language'] ?? 'id';
   ```

   **Stage B — UPSERT Post:**
   ```php
   $post = Post::updateOrCreate(
       ['source_idea_id' => $idea->id],
       [
           'slug' => Str::slug($article['title'] ?? $idea->title),
           'status' => 'published',
           'published_at' => now(),
           'user_id' => auth()->id(),
           'translation_pending' => false,
           // no title/content on posts table — lives on post_translations per schema
       ]
   );
   ```

   **Stage C — UPSERT primary translation (ID):**
   ```php
   PostTranslation::updateOrCreate(
       ['post_id' => $post->id, 'language' => $primaryLang],
       [
           'title' => $article['title'] ?? $idea->title,
           'slug' => Str::slug($article['title'] ?? $idea->title),
           'content' => $article['content'] ?? '',
           'excerpt' => $article['excerpt'] ?? null,
           'meta_title' => $article['meta_title'] ?? null,
           'meta_description' => $article['meta_description'] ?? null,
           'og_title' => $article['og_title'] ?? null,
           'og_description' => $article['og_description'] ?? null,
           'ai_summary' => $article['ai_summary'] ?? null,
       ]
   );
   ```

   **Stage D — Trigger translate (feature-flagged):**
   ```php
   $translationPending = false;
   $targetLocales = array_diff($idea->languages ?? ['id'], [$primaryLang]);

   if (
       config('services.article_generation.use_translate_phase')
       && !empty($targetLocales)
   ) {
       $targetLocale = $targetLocales[0]; // MVP: first non-primary locale
       $idempotencyKey = (string) Str::uuid();
       $result = $this->articleGen->triggerTranslate($post->id, $idempotencyKey, $targetLocale);

       $post->update([
           'translation_pending' => true,
           'translation_attempts' => 1,
           'last_translation_attempt' => now(),
       ]);
       $translationPending = true;
   }
   ```

   **Stage E — Complete idea:**
   ```php
   $idea->update(['status' => 'completed']);

   return response()->json([
       'success' => true,
       'data' => [
           'idea' => $idea->fresh(),
           'published_post_id' => $post->id,
           'translation_pending' => $translationPending,
       ],
       'message' => $translationPending
           ? 'Published — English translation in progress.'
           : 'Published in primary language only.',
   ]);
   ```

2. Add imports at top of controller:
   ```php
   use App\Models\Post;
   use App\Models\PostTranslation;
   use Illuminate\Support\Str;
   ```
   (Str already imported from prior phase; Post + PostTranslation are new.)

**Verification:**
- [ ] PHP syntax clean
- [ ] `artisan route:list | grep "approve.*publish\|publish"` shows the existing publish route still registered
- [ ] Manual tinker test: create dummy `ContentIdea` with `generated_article = ['title'=>'T','content'=>'<p>B</p>','language'=>'id']` and `languages=['id','en']`, call controller method, verify:
  - `posts` table has 1 new row with `source_idea_id` matching
  - `post_translations` has 1 new row with `language='id'`
  - If `ARTICLE_GEN_USE_TRANSLATE_PHASE=true`, `translation_pending=true` + article-translate process spawned (check /tmp/article-translate-*.log on local)
  - If flag off, `translation_pending=false`, no subprocess
  - Second call on same idea UPDATES rather than creates new rows
- [ ] No placeholder/TODO comments in rewritten method

---

### Phase 14: Backend — ProcessPendingTranslations cron command

**Estimated time:** 12 minutes

**Files:**
- Create: `D:\Projects\Portfolio_v2\backend\app\Console\Commands\ProcessPendingTranslations.php`

**Steps:**
1. Scaffold via `artisan make:command ProcessPendingTranslations`
2. Fill in:
   ```php
   protected $signature = 'content:process-pending-translations';
   protected $description = 'Retry failed translations for published posts (runs every 5min)';

   public function handle(ArticleGenerationService $service): int
   {
       $posts = Post::where('translation_pending', true)
           ->where(function ($q) {
               $q->whereNull('last_translation_attempt')
                 ->orWhere('last_translation_attempt', '<=', now()->subMinutes(5));
           })
           ->where('translation_attempts', '<', 3)
           ->limit(5)
           ->get();

       foreach ($posts as $post) {
           $idea = ContentIdea::where('id', $post->source_idea_id)->first();
           $targetLocales = array_diff($idea?->languages ?? ['en'], [$idea?->generated_article['language'] ?? 'id']);
           if (empty($targetLocales)) {
               $post->update(['translation_pending' => false]);
               continue;
           }

           $key = (string) Str::uuid();
           $result = $service->triggerTranslate($post->id, $key, $targetLocales[0]);

           $post->update([
               'translation_attempts' => $post->translation_attempts + 1,
               'last_translation_attempt' => now(),
           ]);
           $this->line("Retry translate for post #{$post->id} (attempt {$post->translation_attempts})");
       }

       // Mark posts that exhausted 3 attempts as non-pending (manual review)
       Post::where('translation_pending', true)
           ->where('translation_attempts', '>=', 3)
           ->update(['translation_pending' => false]);

       $this->info("Processed " . $posts->count() . " pending translations");
       return 0;
   }
   ```

**Verification:**
- [ ] Command registered: `artisan list | grep process-pending-translations` shows it
- [ ] Running `artisan content:process-pending-translations` on an empty DB exits 0 with "Processed 0"
- [ ] With a seeded `translation_pending=true` post: calling the command increments `translation_attempts` and spawns a translate subprocess

---

### Phase 15: Backend — register cron schedule

**Estimated time:** 2 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\routes\console.php`

**Steps:**
1. Append:
   ```php
   Schedule::command('content:process-pending-translations')->everyFiveMinutes()->withoutOverlapping();
   ```

**Verification:**
- [ ] `artisan schedule:list` shows `content:process-pending-translations` entry
- [ ] Schedule fires every 5 minutes (cron expression `*/5 * * * *`)

---

### Phase 16: Backend routes — register 3 automation endpoints

**Estimated time:** 2 minutes

**Files:** `backend/routes/api.php` (already edited in Phase 10 for GET; double-check all 3 routes are present)

**Steps:**
1. Confirm these 3 routes are inside the `auth:sanctum + throttle:60,1` automation group:
   - `GET  /posts/{id}/for-translation`
   - `PUT  /posts/{id}/save-translation`
   - `POST /posts/{id}/translation-complete`
2. `artisan route:clear && artisan route:list | grep -E "for-translation|save-translation|translation-complete"`

**Verification:**
- [ ] All 3 routes listed
- [ ] All 3 routes sit in automation middleware group

---

### Phase 17: Backend commit

**Estimated time:** 3 minutes

**Files:** All Phases 6-16

**Steps:**
1. `cd D:\Projects\Portfolio_v2\backend`
2. `git add database/migrations app/Models/Post.php config/services.php app/Services/ArticleGenerationService.php app/Http/Controllers/Api/Admin/ContentIdeaController.php app/Console/Commands/ProcessPendingTranslations.php routes/api.php routes/console.php`
3. Commit:
   ```
   feat: translation pipeline — real Post creation + ID→EN translate + retry cron

   - Migration: posts.source_idea_id + 3 translation-tracking columns
   - approveAndPublish actually creates Post + PostTranslation rows (finally)
   - triggerTranslate() service method (Sonnet, mirrors triggerImages)
   - 3 new automation endpoints: for-translation, save-translation,
     translation-complete
   - ProcessPendingTranslations cron (every 5min, retry up to 3x)
   - Config keys: refs_translate, model_translate, use_translate_phase flag
   ```
4. No push yet — wait until frontend also ready.

**Verification:**
- [ ] `git log -1 --stat` shows expected files
- [ ] `D:/xampp/php/php.exe artisan test` passes as much as baseline (no new regressions)
- [ ] PHP -l clean on all modified PHP files

---

### Phase 18: Frontend — default config languages + publish toast

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue`

**Steps:**
1. Line 901: change `configLanguages.value = ['id']` → `configLanguages.value = ['id', 'en']`
2. Find `handlePublish` function (search for `approveAndPublish`). Update success branch:
   ```js
   if (result.success) {
     if (result.data?.translation_pending) {
       toast.warning('Published — English version pending (auto-retry)')
     } else {
       toast.success('Published!')
     }
     await refreshIdeas()
   }
   ```
3. Ensure `toast.warning` exists on the `useToast` composable; if not, fall back to `toast.info` or `toast.success` with warning styling. Read the composable to verify.

**Verification:**
- [ ] `npm run build` clean
- [ ] Line 901 now shows `['id', 'en']`
- [ ] `handlePublish` branches on `translation_pending`
- [ ] Manual DevTools inspection: open Configure Research modal → both EN + ID checkboxes pre-checked
- [ ] No placeholder/TODO comments

---

### Phase 19: Frontend commit

**Estimated time:** 2 minutes

**Steps:**
1. `cd D:\Projects\Portfolio_v2 && git add frontend/src/views/admin/ContentEngine.vue`
2. Commit:
   ```
   feat(content-engine): default EN+ID languages + translation-pending publish toast
   ```

**Verification:**
- [ ] Commit created
- [ ] `git log --oneline -1` shows the expected message

---

### Phase 20: Env updates — local + .env.example + VPS

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\.env`
- Modify: `D:\Projects\Portfolio_v2\backend\.env.example`
- Modify (via SSH): `/var/www/Portfolio_v2/backend/.env`

**Steps:**
1. Local `.env` — append after existing image-phase vars:
   ```
   ARTICLE_GEN_REFS_TRANSLATE=
   ARTICLE_GEN_MODEL_TRANSLATE=sonnet
   ARTICLE_GEN_USE_TRANSLATE_PHASE=false
   ```
2. `.env.example` — same append
3. VPS via sudo-exec:
   ```bash
   sudo sed -i '/^ARTICLE_GEN_USE_IMAGES_PHASE=/a ARTICLE_GEN_REFS_TRANSLATE=/home/claudesn/refs-translate.md\nARTICLE_GEN_MODEL_TRANSLATE=sonnet\nARTICLE_GEN_USE_TRANSLATE_PHASE=false' /var/www/Portfolio_v2/backend/.env
   ```
4. VPS `artisan config:clear` as www-data

**Verification:**
- [ ] Local config returns `'sonnet'` for `model_translate`, `false` for `use_translate_phase`, empty for `refs_translate`
- [ ] VPS config returns `'/home/claudesn/refs-translate.md'` for `refs_translate`
- [ ] VPS flag `use_translate_phase` = `false` (safe rollout)

---

### Phase 21: Plugin VPS deploy (pull new files into plugin cache)

**Estimated time:** 5 minutes

**Files:** GitHub push + VPS curl pull

**Steps:**
1. Plugin: `git checkout main && git merge feat/translation-pipeline --no-ff && git push origin main`
2. On VPS: `claude plugin uninstall article-content-writer@... && claude plugin marketplace update ... && claude plugin install article-content-writer@...` (as `claudesn` user)
3. Copy `refs-translate.md` to canonical path:
   ```bash
   cp /home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/2.3.0/references/compiled/refs-translate.md /home/claudesn/refs-translate.md
   ```
4. Local: same uninstall/install routine to pick up article-translate skill

**Verification:**
- [ ] `ls /home/claudesn/.claude/plugins/.../2.3.0/skills/article-translate/` shows SKILL.md
- [ ] `/home/claudesn/refs-translate.md` exists, 2-8KB
- [ ] `grep "HTML structure" /home/claudesn/refs-translate.md` returns ≥ 1 match
- [ ] `ls -la /home/claudesn/refs-*.md` shows 5 files

---

### Phase 22: VPS deploy — migration + backend git pull + build frontend

**Estimated time:** 8 minutes

**Files:** VPS only

**Steps:**
1. VPS: `cd /var/www/Portfolio_v2 && sudo git pull origin main`
2. VPS: `cd backend && sudo -u www-data php artisan migrate`
3. VPS: `sudo -u www-data php artisan config:clear && sudo -u www-data php artisan route:clear && sudo -u www-data php artisan cache:clear`
4. VPS: `cd ../frontend && sudo npm run build`
5. Verify routes: `artisan route:list | grep -E "for-translation|save-translation|translation-complete"` → 3 entries
6. Verify schedule: `artisan schedule:list | grep process-pending-translations` → 1 entry

**Verification:**
- [ ] All 3 new automation routes registered on VPS
- [ ] Schedule includes `content:process-pending-translations` every 5min
- [ ] Migration `add_translation_tracking_to_posts` applied (`artisan migrate:status` shows Yes)
- [ ] Frontend `dist/` rebuilt (fresh timestamps on `index.html` + ContentEngine-*.js)

---

### Phase 23: E2E test — flag OFF path (regression check)

**Estimated time:** 6 minutes (manual)

**Steps:**
1. Keep `ARTICLE_GEN_USE_TRANSLATE_PHASE=false` on VPS
2. In admin panel, take a published-ready idea through the full pipeline
3. Click Approve & Publish
4. Expect: post_translations gets 1 row (ID language), no EN row
5. Toast: "Published!"
6. Check `posts` table: `source_idea_id` populated, `translation_pending=false`

**Verification:**
- [ ] Publish completes without errors
- [ ] `post_translations` has exactly 1 row (language='id')
- [ ] `translation_pending = false`
- [ ] No `/tmp/article-translate-*.log` subprocess files on VPS
- [ ] No regressions in existing idea pipeline

---

### Phase 24: E2E test — flag ON path (activation check) + CLAUDE.md update

**Estimated time:** 10 minutes (manual + docs)

**Steps:**
1. SSH VPS: set `ARTICLE_GEN_USE_TRANSLATE_PHASE=true`, `artisan config:clear`
2. Take another idea through pipeline, click Approve & Publish
3. Expect toast: "Published — English version pending (auto-retry)"
4. `posts.translation_pending = true`, `translation_attempts = 1`
5. `/tmp/article-translate-*.log` spawned on VPS
6. Wait ~60-120 seconds
7. Check `post_translations` — EN row should appear with translated title/content/meta
8. `posts.translation_pending` flips to `false`
9. Manual SQL check: `SELECT title FROM post_translations WHERE post_id=X ORDER BY language` shows both ID + EN
10. Update CLAUDE.md:
    - Add 5th pipeline phase "Translate (Finalize)" to the diagram (under Content Pipeline section)
    - Add `article-translate` to skill list (9 skills now)
    - Add env vars `ARTICLE_GEN_REFS_TRANSLATE`, `ARTICLE_GEN_MODEL_TRANSLATE`, `ARTICLE_GEN_USE_TRANSLATE_PHASE`
    - Add 3 new endpoints to automation list
    - Update Last Updated date
11. Commit: `docs: update CLAUDE.md — add translation pipeline architecture`

**Verification:**
- [ ] Full flag-on flow completes, both translations in DB
- [ ] Translated English content preserves HTML (no markdown drift)
- [ ] Translation quality spot-check: read 2 paragraphs, confirm tone matches casual English register, technical terms preserved
- [ ] CLAUDE.md reflects new pipeline accurately
- [ ] Cron retry has not yet fired (since first attempt succeeded) — confirmed via `translation_attempts=1`

---

## File Change Summary

| Phase | File | Action | Location |
|---|---|---|---|
| 0 | (branch) | CHECKOUT | plugin git |
| 1 | translation-guidelines.md | CREATE | plugin/references |
| 2 | article-translate/SKILL.md | CREATE | plugin/skills |
| 3 | compile-references.sh | MODIFY | plugin/scripts |
| 4 | refs-translate.md | BUILD | plugin/references/compiled |
| 5 | Plugin commit + push | COMMIT | plugin git |
| 6 | add_translation_tracking_to_posts.php | CREATE | backend/database/migrations |
| 7 | Post.php | MODIFY | backend/app/Models |
| 8 | config/services.php | MODIFY | backend/config |
| 9 | ArticleGenerationService.php | ADD METHOD | backend/app/Services |
| 10-12 | ContentIdeaController.php | ADD 3 METHODS | backend/app/Http/Controllers |
| 10-12 | routes/api.php | ADD 3 ROUTES | backend/routes |
| 13 | ContentIdeaController.php | REWRITE approveAndPublish | backend/app/Http/Controllers |
| 14 | ProcessPendingTranslations.php | CREATE | backend/app/Console/Commands |
| 15 | routes/console.php | MODIFY | backend/routes |
| 16 | routes/api.php | VERIFY | backend/routes |
| 17 | Backend commit | COMMIT | backend git |
| 18 | ContentEngine.vue | MODIFY (1-line default + toast) | frontend/views/admin |
| 19 | Frontend commit | COMMIT | frontend git |
| 20 | .env + .env.example + VPS .env | ADD ENV VARS | both |
| 21 | Plugin push + VPS plugin reinstall | DEPLOY | VPS |
| 22 | VPS deploy + migrate + build | DEPLOY | VPS |
| 23-24 | E2E tests + CLAUDE.md | TEST + DOCS | admin panel + repo |

## Dependencies

- Plugin repo clone at `D:\Projects\claude-plugin\article-content-writer\`
- SSH access to VPS (via `mcp__ssh-prod-vps__exec` MCP)
- Claude CLI authenticated on VPS (already verified via existing image-phase flow)
- `claude plugin marketplace update` access on both local + VPS

## Rollout Safety

- Feature flag `ARTICLE_GEN_USE_TRANSLATE_PHASE` defaults `false` — production publish flow still works without translation (just creates ID-only post + translation row)
- `approveAndPublish` rewrite is the RISKY change (was near-empty, now creates real rows). Phase 23 flag-OFF test validates this before flag-ON activation.
- Migration is additive (adds columns; no data loss). Rollback = `migrate:rollback` on the specific migration.
- Cron activation is gated on VPS crontab (currently absent) — user decides when to enable via `crontab -u www-data -e` (separate from this plan).

## Estimated Total Time

- Plugin (Phases 0-5): ~35 min
- Backend (Phases 6-17): ~85 min
- Frontend (Phases 18-19): ~10 min
- Env + deploy (Phases 20-22): ~20 min
- Testing + docs (Phases 23-24): ~20 min
- **Total: ~170 min (~2h 50min)**

## Open Questions

None — all 6 design decisions + translation model (Sonnet) + modal default (EN+ID checked) resolved pre-plan.

## Known Deviations (accepted pre-execution)

- **Feature tests on SQLite harness blocked by pre-existing MySQL-specific `MODIFY COLUMN ENUM` migration** (same issue as image phase split plan). Plan still specifies test file creation for coverage; will use `markTestSkipped('MySQL-only')` pattern. Not a blocker for manual E2E in Phases 23-24.
- **Cron not actively firing on VPS** — `Schedule::command()` registered but no `php artisan schedule:run` entry in crontab. `ProcessPendingTranslations` code ships in Phase 14 so it's ready when user installs crontab. Manual `artisan content:process-pending-translations` can be invoked at any time to test retry path.
