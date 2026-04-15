# Translation Pipeline Design — Indonesian → English (Sonnet)

## Goal

Add a **finalize-stage translation pipeline** that translates published articles from Indonesian (primary) to English using Sonnet, persisted in the existing `post_translations` table. Triggered by the "Approve & Publish" button, runs silently in background, falls back to ID-only publish + auto-retry on failure.

## Locked Decisions (from brainstorm)

| # | Decision | Choice |
|---|---|---|
| 1 | Trigger point | **Approve & Publish** (after Gate 2 images_ready) |
| 2 | Scope | title + content + meta_title + meta_description + og_title + og_description + image alt text (skip image_concepts, hook, framework labels) |
| 3 | Storage | `post_translations` table directly on publish (uses existing `Post::translations()` hasMany) |
| 4 | Idempotency | Re-translate every publish (UPSERT posts + post_translations rows) |
| 5 | Failure mode | Publish ID-only immediately, mark `translation_pending=true`, background cron retry every 5min |
| 6 | Progress UI | Silent — toast on completion ("Published EN + ID" or "Published — English pending") |
| Locked above | Translation model | **Sonnet** (uniform with rest of pipeline) |
| Locked above | Default Configure modal | English + Indonesian both checked |

## Architecture

```
Admin Panel (/admin/content-engine)
       │
  Idea reaches images_ready status
  User clicks "Approve & Publish"
       │
  ┌────▼─────────────────────────────────────────────────────────────┐
  │  ContentIdeaController::approveAndPublish                        │
  │                                                                  │
  │  1. UPSERT post (slug from ID title, primary content from ID)   │
  │  2. UPSERT post_translations.id row (Indonesian fields)          │
  │  3. IF translate target 'en' in idea.languages:                  │
  │       SYNCHRONOUS: triggerTranslate(post_id) → wait ~30s         │
  │       IF success → UPSERT post_translations.en row → status=completed │
  │       IF fail → mark translation_pending=true → status=completed │
  │            → background ProcessPendingTranslations cron retries  │
  │  4. Return success (always, even on translate fail)              │
  └──────────────┬───────────────────────────────────────────────────┘
                 │
  Frontend toast: "Published EN + ID" or "Published — English pending"
```

## Plugin Design

### New Skill: `article-translate`

```yaml
---
name: article-translate
description: "Pipeline-only skill for finalize-stage Indonesian → English translation. Reads published post from backend, translates title + content + meta + alt text using Sonnet, posts back to /save-translation endpoint. Part of finalize flow: Gate 2 approve → publish → translate."
---
```

**Flags:**
- `--post-id {id}` — Published Post ID (not idea_id)
- `--api-url`, `--api-token`, `--idempotency-key` (standard)
- `--target-locale en` (default `en`, future-proof for other targets)

**Steps:**
1. GET `/api/automation/posts/{post_id}/for-translation` → returns ID title, content, meta, image_alt[]
2. Translate each field with Sonnet using preserve-formatting prompt (HTML structure intact, no markdown drift)
3. PUT `/api/automation/posts/{post_id}/save-translation` → backend creates/updates post_translations.en row
4. POST `/api/automation/posts/{post_id}/translation-complete` → marks `translation_pending=false`

**Reference bundle:** `refs-translate.md` — small bundle (~5KB), just translation guidelines (preserve HTML, preserve technical terms, English casual tone matching Indonesian Gen-Z register).

## Backend Design

### Service Method

```php
// app/Services/ArticleGenerationService.php
public function triggerTranslate(int $postId, string $idempotencyKey, string $targetLocale = 'en'): array
{
    $prompt = "/article-translate --post-id {$postId} --api-url {$this->apiUrl}";
    $prompt .= " --api-token {$this->apiToken} --idempotency-key {$idempotencyKey}";
    $prompt .= " --target-locale {$targetLocale}";
    $model = config('services.article_generation.model_translate', 'sonnet');
    $refsFile = config('services.article_generation.refs_translate', '');
    return $this->executePrompt($prompt, $postId, 'translate', $model, $refsFile);
}
```

### Controller Changes

`approveAndPublish($id)` — full rewrite:
1. Validate idea status in `['images_ready', 'article_ready']`
2. Build post payload from `idea.generated_article` (title, content, meta, image URLs from `generated_images`)
3. UPSERT `posts` row by `source_idea_id` (NEW column on posts table)
4. UPSERT `post_translations.id` row
5. If `idea.languages` contains 'en':
   - Generate idempotency key
   - Call `triggerTranslate($post->id, $key)` — but synchronous-ish: return immediately, plugin runs async
   - Mark `posts.translation_pending = true`
6. Update `idea.status = 'completed'`
7. Return success with `published_post_id`

### New Endpoints

```
GET  /api/automation/posts/{id}/for-translation     → returns ID fields to translate
PUT  /api/automation/posts/{id}/save-translation    → save EN row to post_translations
POST /api/automation/posts/{id}/translation-complete → mark translation_pending=false
```

### Background Cron

`ProcessPendingTranslations` artisan command (every 5min):
1. Find posts where `translation_pending = true` AND `last_translation_attempt < now()-5min`
2. For each: re-trigger `triggerTranslate($post->id)` with new idempotency key
3. Log attempt count; after 3 fails, set `translation_pending = false` + flag for manual review

Registered in `routes/console.php`:
```php
Schedule::command('content:process-pending-translations')->everyFiveMinutes();
```

### Migration

```php
Schema::table('posts', function ($table) {
    $table->unsignedBigInteger('source_idea_id')->nullable()->index();
    $table->boolean('translation_pending')->default(false);
    $table->unsignedTinyInteger('translation_attempts')->default(0);
    $table->timestamp('last_translation_attempt')->nullable();
});
```

## Frontend Design

### Modal Default Fix

```js
// ContentEngine.vue:901
configLanguages.value = ['id', 'en']  // was: ['id']
```

### Approve & Publish Button — toast handling

```js
async function handlePublish(idea) {
  const result = await approveAndPublish(idea.id)
  if (result.success) {
    if (result.data?.translation_pending) {
      toast.warning('Published — English version pending (auto-retry)')
    } else {
      toast.success('Published in EN + ID')
    }
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to publish')
  }
}
```

### YAGNI Cuts

- ❌ NO progress modal phase card for translation (silent per Decision 6)
- ❌ NO inline progress bar in idea table row
- ❌ NO translation preview UI (no edit step before publish)
- ❌ NO bahasa ke-3 (es, ja, dll) — locked to en for now, future when needed

## Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Default modal language | `configLanguages.value = ['id', 'en']` | Existing var, change init | One-line change |
| Approve & Publish trigger | `approveAndPublish(id)` composable | Yes | No frontend rewrite |
| Idea source data for post | `idea.generated_article` JSON | Yes | Contains title, content, meta, image refs |
| Post creation | `posts` table + `Post` model | Yes | Need: add `source_idea_id` migration |
| Post translation rows | `post_translations` table + `PostTranslation` model | Yes | Already exists, currently unused by content engine |
| Translate skill trigger | `ArticleGenerationService::triggerTranslate()` | NEW | Mirror `triggerImages()` |
| Plugin skill | `skills/article-translate/SKILL.md` | NEW | New 8th skill |
| Translate refs bundle | `refs-translate.md` | NEW | Add to compile-references.sh |
| Background retry | `ProcessPendingTranslations` artisan command | NEW | Mirror `ProcessPendingImages` pattern |
| Cron registration | `routes/console.php` | Yes | Add one Schedule::command line |

## Implementation Feasibility

✅ All real integrations available. Pattern-match from existing `triggerImages()` work.

⚠️ **Cron prerequisite:** `ProcessPendingTranslations` won't run until VPS crontab `* * * * * php artisan schedule:run` is registered (currently absent — confirmed earlier). User can install crontab anytime; no blocker for this work.

⚠️ **`source_idea_id` is new column:** Requires migration — adds ability to UPSERT post by source idea (instead of finding by slug, which is fragile if user edits title).

## File Change Summary

| Layer | File | Action |
|---|---|---|
| Plugin | `skills/article-translate/SKILL.md` | CREATE |
| Plugin | `references/translation-guidelines.md` | CREATE (small ~3KB) |
| Plugin | `scripts/compile-references.sh` | MODIFY (add refs-translate.md build) |
| Plugin | `references/compiled/refs-translate.md` | BUILD |
| Backend | `database/migrations/...add_translation_columns_to_posts.php` | CREATE |
| Backend | `config/services.php` | MODIFY (refs_translate, model_translate) |
| Backend | `app/Services/ArticleGenerationService.php` | MODIFY (add triggerTranslate) |
| Backend | `app/Http/Controllers/Api/Admin/ContentIdeaController.php` | MODIFY (rewrite approveAndPublish) |
| Backend | `app/Http/Controllers/Api/PostController.php` | MODIFY (add automation endpoints) — OR new sub-controller |
| Backend | `routes/api.php` | MODIFY (3 new automation endpoints) |
| Backend | `app/Console/Commands/ProcessPendingTranslations.php` | CREATE |
| Backend | `routes/console.php` | MODIFY (register cron) |
| Backend | `.env`, `.env.example`, VPS `.env` | MODIFY (refs_translate path, model_translate) |
| Frontend | `frontend/src/views/admin/ContentEngine.vue` | MODIFY (default `['id', 'en']`, toast handling) |
| Docs | `CLAUDE.md` | MODIFY (add 5th pipeline phase + translation env vars) |

## Estimated Total Time

- Plugin (skill + refs + compile): ~25 min
- Backend (migration + service + controller + endpoints + cron): ~60 min
- Frontend (1-line default + toast): ~5 min
- Env + deploy: ~10 min
- Testing + docs: ~15 min
- **Total: ~2 hours**

## Open Questions

None — all 6 decisions locked, plus translation model (Sonnet) and default modal (en+id checked) confirmed upfront.
