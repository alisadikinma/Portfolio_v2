# Named-Entity-Aware Cover Image Generation

**Date:** 2026-04-20
**Status:** Design — ready for `gaspol-plan`
**Author:** Ali Sadikin + Claude
**Problem scope:** Prevent Ali's face from replacing public figures (Dario Amodei, Elon Musk, etc.) and prevent generic locations from replacing famous landmarks (White House, Capitol, Tesla HQ) in blog cover images.

---

## Problem

Current [`CoverBrandingEnhancer.php:51`](../../backend/app/Services/CoverBrandingEnhancer.php#L51) unconditionally prepends Ali's profile photo to every cover image's `face_refs[]`. This creates wrong covers when the article subject is a public figure or when the article references a specific landmark.

**Observed failures:**
1. *"Anthropic CEO Visits the White House Over AI Model Mythos"* — cover showed Ali's face (not Dario Amodei) in front of a generic white building (not the actual White House).
2. *"SpaceX IPO Grok: Musk's Condition That Made Wall Street Kneel"* — cover showed Ali's face (not Elon Musk) beside a rocket.

**Root cause:** No gate detects when the article subject is a named public entity that must be faithfully depicted. Backend assumes creator-face is always correct.

---

## Design

### Section 1 — Core Architecture

Detection happens in plugin `/article-images` Phase 3.5 (image prompt authoring phase). Before plugin writes 300-500 word cinematic prompts, it extends existing Context Extraction with Named Entity Recognition (NER) for persons + landmarks + logos + products, queries Wikidata for notable entities, fetches license-compatible images from Wikimedia Commons, and embeds URLs into `image_prompts[i].entity_refs[]`.

```
──────────────── ARTICLE GENERATION ────────────────
/article-prep     → research + strategy + outline        (~35%)
/article-write    → write body + polish                   (~85%)
/article-score    → 5 gates + 100-point combined          (100%)
                           │
                     [Gate 1 user approval]
                           │
──────────────── IMAGE GENERATION ──────────────────
/article-images   ← Phase 3.5 EXTENDED (entity NER + Wikidata + license filter)
                    → output image_prompts[] with entity_refs[]
                           │
                     [Gate 2 user approval — manual upload if needed]
                           │
GeminiGen (NB2)   → actual image file generation
CoverBrandingEnhancer (backend) → GATE: skip creator-face when entity_refs populated
```

**Phase 3.5 extended flow (plugin side):**

1. **Detect** persons, landmarks, logos, products from `generated_article.content` + `generated_article.title` (Sonnet NER — extends existing Context Extraction).
2. **Filter** by appearance in title OR H1/H2 headings (structural subject indicator).
3. **Resolve** each candidate to Wikidata Q-ID via SPARQL label search (English + Indonesian).
4. **Notability gate:** sitelinks ≥ 5 languages AND P18 (image) property exists. Rejects random individuals.
5. **License filter** via Commons API `extmetadata.LicenseShortName`: keep only `{CC0, PD, PD-USGov, CC-BY-4.0}`. Reject `CC-BY-SA` (share-alike), `CC-BY-ND`, fair-use.
6. **Cache lookup first** — before hitting Wikidata, check backend's `entity_references` table for cached entity (see Section 4).
7. **Write** URLs + metadata to `image_prompts[i].entity_refs[]`.
8. **Flag** missing/failed entities into extended manifest → trigger manual upload flow (Section 2).

**Backend gate (`CoverBrandingEnhancer::enhance`):**

```php
if ($type === 'cover') {
    $hasPersonEntity = collect($prompt['entity_refs'] ?? [])
        ->contains(fn($e) => ($e['entity_type'] ?? null) === 'person');

    if ($hasPersonEntity) {
        // SKIP creator face — subject is the detected public figure
        // SKIP VD auto-rewrite — plugin VD already describes person by name
        // Merge entity URLs into file_urls for GeminiGen
        $prompt = $this->mergeEntityRefs($prompt);
    } else {
        // EXISTING behavior — inject Ali's face + VD rewrite
        $prompt = $this->prependCreatorFace($prompt, $idea);
    }

    // ALWAYS KEEP these (user confirmed in Section 1 Q5):
    $prompt = $this->injectTitleInstruction(...);  // Title overlay
    $prompt = $this->appendWatermark($prompt);     // Watermark logo + tagline
    // Filename still alisadikinma-{keyword}-cover.png
}
```

**Inline images:** Existing `needs_creator_face` + keyword-match logic unchanged. Entity detection doesn't apply to inline (inline images are supporting context, not primary subject).

---

### Section 2 — Telegram Notification + Extended Manifest

Reuses existing Reference Image Manifest pattern (plugin SKILL §3.6 `brand[]`) by adding a parallel `entity[]` category.

**Extended manifest payload** (sent when entities detected but unfetchable):

```json
{
  "step": "manifest_needed",
  "percentage": 20,
  "manifest": {
    "brand": [ /* existing brand logos */ ],
    "entity": [
      {
        "entity_name": "Dario Amodei",
        "entity_type": "person",
        "qid": "Q115468560",
        "used_in": ["Cover"],
        "status": "missing",
        "reason": "Wikimedia P18 license is CC-BY-SA (not in allow-list)",
        "required": true
      },
      {
        "entity_name": "White House",
        "entity_type": "landmark",
        "qid": "Q35525",
        "used_in": ["Cover"],
        "status": "fetched",
        "fetched_url": "https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg",
        "license": "PD-USGov",
        "required": false
      }
    ]
  }
}
```

**Telegram notification flow** (queued job — non-blocking):

```
Plugin → POST /automation/.../progress {step: "manifest_needed", manifest: {...}}
            │
            ▼
ContentIdeaAutomationController@updateProgress
  ├── Persist manifest to content_ideas.pending_manifest (NEW JSON column)
  ├── Set content_ideas.status = 'awaiting_manual_upload' (NEW status)
  └── dispatch(new DispatchTelegramNotification($idea, 'manifest_needed'))
            │
            ▼
TelegramNotificationService::sendManifestAlert
  POST https://api.telegram.org/bot{token}/sendMessage
  {
    "chat_id": "{admin_chat_id}",
    "parse_mode": "Markdown",
    "text": "🚨 *Manual upload needed* — Content Engine\n\nArticle: _{title}_\n\n*Missing:*\n• 👤 Dario Amodei (person) — no PD photo\n\n*Auto-fetched:*\n• 🏛️ White House — PD-USGov\n\n[Open Admin]({admin_url})",
    "reply_markup": {
      "inline_keyboard": [[
        {"text": "📤 Open Upload UI", "url": "{admin_url}"},
        {"text": "⏭️ Skip (use my face)", "callback_data": "skip:{idea_id}"},
        {"text": "❌ Reject idea", "callback_data": "reject:{idea_id}"}
      ]]
    }
  }
```

**Telegram Settings** (new `settings` group='telegram', 6 rows seeded via `TelegramSettingsSeeder`):

| key | default | purpose |
|---|---|---|
| `telegram_bot_token` | null | BotFather token |
| `telegram_chat_id` | null | Admin's personal chat ID (from bot deep-link) |
| `telegram_enabled` | `false` | Master opt-in toggle |
| `telegram_notify_manifest_needed` | `true` | Fire on manual upload required |
| `telegram_notify_generation_failed` | `true` | Fire on GeminiGen failure |
| `telegram_notify_publish_success` | `false` | Fire on successful publish |

Admin UI: new **"Telegram Notifications"** card in [`AboutSettings.vue`](../../frontend/src/views/admin/AboutSettings.vue) below the Creator Brand card, mirroring the `creator_brand` endpoint pattern (`GET /api/admin/settings/telegram`, `PUT /api/admin/settings/telegram`).

**Gate 2 UI extension** ([`ContentEngine.vue`](../../frontend/src/views/admin/ContentEngine.vue) Image Config modal):

- Per-segment chip row (fetched, green): `👤 Dario Amodei · PD-USGov`
- Per-segment missing slot (red banner + file input + Skip button):
  ```
  ⚠️ Dario Amodei — Wikimedia ref unavailable
  [📤 Upload face reference] [⏭️ Skip (use creator face)]
  ```
- Idea row badge in main list: `⚠️ Awaiting manual upload` when `status='awaiting_manual_upload'`

---

### Section 3 — Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Entity detection (LLM NER) | Sonnet in `/article-images` Phase 3.5 | Existing step (extend) | Prompt extension: extract persons/landmarks/logos/products |
| Wikidata SPARQL | `https://query.wikidata.org/sparql` | External, public | ~200ms; notability filter via `?item wdt:P18 ?image` + sitelink count |
| Commons license API | `https://commons.wikimedia.org/w/api.php` | External, public | `action=query&prop=imageinfo&iiprop=extmetadata`; `LicenseShortName` field |
| License whitelist | Hard-coded const | NEW | `['CC0', 'PD', 'Public domain', 'PD-USGov', 'CC-BY-4.0']` |
| Cached entity refs | `entity_references` table + `/storage/uploads/entity-refs/` | NEW | See Section 4 |
| `entity_refs[]` per prompt | `content_ideas.generated_article.image_prompts[i].entity_refs` | NEW JSON field | `[{qid, name, type, url, license, attribution}]` |
| `pending_manifest` | `content_ideas.pending_manifest` | NEW JSONB column | Stored blocking manifest, cleared on resolution |
| `awaiting_manual_upload` status | `content_ideas.status` | NEW enum value | Between `article_ready` and `generating_images` |
| Telegram settings | `settings` group='telegram' (6 rows) | NEW | Seeded idempotently |
| Telegram send | `api.telegram.org/bot{token}/sendMessage` | External | Via `TelegramNotificationService` |
| UI upload slot | Reuses existing reference upload UI | Existing pattern | Extend `brand[]` tab with `entity[]` tab |
| Admin Settings UI | `AboutSettings.vue` | Existing | New card below Creator Brand |

### Affected Files

**Plugin (article-content-writer v2.3.0 → v2.4.0):**
- [`skills/article-images/SKILL.md`](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md)
  - Extend §3.5 Context Extraction with NER for persons/landmarks/logos/products
  - NEW §3.5b Wikidata Lookup + License Filter + Cache Lookup
  - Extend §3.6 Manifest with `entity[]` category alongside `brand[]`
  - Extend §7 Output Format with `entity_refs[]` field on `image_prompts[i]`

**Backend (Laravel 12):**
- [`app/Services/CoverBrandingEnhancer.php`](../../backend/app/Services/CoverBrandingEnhancer.php)
  - `enhance()` — NEW gate: if `type='cover'` AND `entity_refs` contains person → skip `prependCreatorFace` + skip VD rewrite, merge entity URLs into `file_urls`
- [`app/Services/ImageGenerationService.php`](../../backend/app/Services/ImageGenerationService.php)
  - `triggerForIdea()` — pass `entity_refs[]` through to `queue()` as additional `styleRefs`
- `app/Services/EntityReferenceService.php` (**NEW**)
  - `findOrFetch($name)` — cache-first lookup: DB → Wikidata SPARQL → Commons → download → DB insert
  - `resolveWikidataQid($name)` — SPARQL label search with en + id
  - `fetchCommonsLicense($filename)` — MediaWiki API `imageinfo.extmetadata`
  - `downloadAndStore($url, $qid, $type)` — save to `public/uploads/entity-refs/{type}/{qid}_{slug}.{ext}`
- `app/Services/TelegramNotificationService.php` (**NEW**)
  - `sendManifestAlert(ContentIdea)`, `sendGenerationFailed(ContentIdea)`, `sendPublishSuccess(ContentIdea)`
  - Uses `Http::post` to Telegram Bot API with inline keyboard markup
- `app/Jobs/DispatchTelegramNotification.php` (**NEW**, queued)
  - Wraps service calls with 3 retries on transient failures
- `app/Models/EntityReference.php` (**NEW**)
  - Eloquent model for `entity_references` table with `incrementUseCount()` helper
- [`app/Http/Controllers/Api/SettingsController.php`](../../backend/app/Http/Controllers/Api/SettingsController.php)
  - Add `updateTelegramSettings()` + `getTelegramSettings()` parallel to creator_brand pattern
  - Add `testTelegramNotification()` for "Send test message" button
- [`app/Http/Controllers/Api/Admin/ContentIdeaController.php`](../../backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php)
  - NEW `uploadEntityReference()` — multipart upload → merge into `entity_refs[]` + cache to `entity_references` table
  - NEW `skipEntityReference()` — force fallback to creator face for override
- `app/Http/Controllers/Api/Automation/ContentIdeaAutomationController.php` (controller handling automation progress endpoint)
  - Extend `updateProgress()` — on `step='manifest_needed'`, persist `pending_manifest` + set status + dispatch Telegram job
  - NEW `GET /automation/entity-refs/lookup?name={name}` — plugin-side cache lookup endpoint
- `database/migrations/2026_04_20_000001_create_entity_references_table.php` (**NEW**)
- `database/migrations/2026_04_20_000002_add_pending_manifest_to_content_ideas.php` (**NEW**)
- `database/migrations/2026_04_20_000003_add_awaiting_manual_upload_status.php` (**NEW**) — extend enum/string
- `database/seeders/TelegramSettingsSeeder.php` (**NEW**) — 6 idempotent rows
- [`routes/api.php`](../../backend/routes/api.php) — 5 new routes:
  - `GET /admin/settings/telegram`
  - `PUT /admin/settings/telegram`
  - `POST /admin/settings/telegram/test`
  - `POST /admin/content-engine/ideas/{id}/upload-entity-reference`
  - `POST /admin/content-engine/ideas/{id}/skip-entity-reference`
  - `GET /automation/entity-refs/lookup`

**Frontend (Vue 3):**
- [`src/views/admin/ContentEngine.vue`](../../frontend/src/views/admin/ContentEngine.vue) — Idea row badge + modal entity UI
- `src/components/admin/ImageGeneration.vue` — Segment-card entity chip row + `<EntityUploadSlot>` component
- `src/components/admin/EntityUploadSlot.vue` (**NEW**) — per-entity file input + Skip override button
- [`src/composables/useContentEngine.js`](../../frontend/src/composables/useContentEngine.js) — Add `uploadEntityReference()` + `skipEntityReference()` methods
- [`src/views/admin/AboutSettings.vue`](../../frontend/src/views/admin/AboutSettings.vue) — NEW "Telegram Notifications" card
- [`src/stores/settings.js`](../../frontend/src/stores/settings.js) — Add `telegramSettings` state + `saveTelegramSettings()`

---

### Section 4 — Entity Reference Cache (Local Folder + DB)

Cached entities are persisted locally so subsequent articles about the same subject reuse the reference instantly (no Wikidata + Commons round-trip).

**Storage layout:**
```
public/uploads/entity-refs/
├── person/
│   ├── Q115468560_dario-amodei.jpg
│   └── Q317521_elon-musk.jpg
├── landmark/
│   ├── Q35525_white-house.jpg
│   └── Q11768_capitol.jpg
├── logo/
│   ├── Q4017798_anthropic.png
│   └── Q193701_spacex.png
└── product/
    └── Q25956_starship.jpg
```

Filename pattern: `{qid}_{kebab-case-name}.{ext}` — QID guarantees uniqueness, slug aids human browsing.

**`entity_references` table schema:**

```sql
CREATE TABLE entity_references (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    qid VARCHAR(20) UNIQUE NULL,                         -- Wikidata Q-ID; null for user-uploaded
    name VARCHAR(255) NOT NULL,
    entity_type ENUM('person','landmark','logo','product') NOT NULL,
    local_path VARCHAR(500) NOT NULL,                     -- relative: entity-refs/person/Q..._...jpg
    local_url VARCHAR(500) NOT NULL,                      -- full URL via url('/storage/...')
    wikimedia_source_url TEXT NULL,
    license VARCHAR(50) NOT NULL,                         -- CC0, PD-USGov, CC-BY-4.0, USER-UPLOADED
    attribution TEXT NULL,
    source ENUM('wikimedia','user_upload') DEFAULT 'wikimedia',
    fetched_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    use_count INT DEFAULT 1,
    refresh_after TIMESTAMP NULL,                         -- Admin-controlled refresh trigger
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE INDEX uq_qid (qid),
    INDEX idx_name (name),
    INDEX idx_type_source (entity_type, source)
);
```

**Lookup algorithm** (`EntityReferenceService::findOrFetch($name, $type)`):

```
1. Normalize $name (lowercase + trim)
2. Resolve to QID via Wikidata SPARQL (cached in Laravel cache, 30-day TTL)
3. IF QID resolved:
     SELECT * FROM entity_references WHERE qid = ? LIMIT 1
     IF found:
       → UPDATE use_count = use_count + 1, last_used_at = NOW()
       → return $row->local_url (INSTANT, no external call)
     ELSE:
       → Query Commons: P18 filename + license
       → IF license IN whitelist:
           download → public/uploads/entity-refs/{type}/{qid}_{slug}.{ext}
           INSERT row → return local_url
       → ELSE: return null (plugin flags manifest_needed)
4. IF QID not resolved (unknown entity): return null
```

**Cache invalidation:** Never expire by default. Admin can set `refresh_after` on a specific row to trigger re-fetch on next use, or can manually upload a replacement file via the (future Phase 2) Admin Entity Library page.

**User-uploaded entities** (from manifest manual upload path) also write to `entity_references` with `source='user_upload'` and `qid=NULL`, keyed by normalized `name`. Future articles about the same person reuse the uploaded file.

**Telemetry:** `use_count` + `last_used_at` enable audit ("which entities are most requested?" for curating better curated photos manually).

---

## Key Decisions (from brainstorm Q&A)

| Decision | Choice | Alternative considered |
|---|---|---|
| Detection approach | Auto-detect + auto-fetch Wikipedia/Wikimedia | Manual toggle; no-face depiction; CC0-only curated source |
| Scope | Persons + Landmarks + Logos + Products | Persons-only (too narrow); All Wikimedia entities (too broad) |
| Detection location | Plugin `/article-images` Phase 3.5 | Backend `CoverBrandingEnhancer` sync call; Hybrid split |
| Fallback on Wikimedia fail | Block + manual upload + Telegram notify | Silent no-face fallback; Silent creator-face fallback |
| License filter | `{CC0, PD, PD-USGov, CC-BY-4.0}` only | All licenses + auto-attribution footer; Reference-only legal gray zone |
| Notability threshold | Wikidata P18 + sitelinks ≥ 5 | Title/H2 match only; LLM judgment; Any proper noun |
| Brand elements on entity covers | Watermark keeps firing (brand consistency) | (VD rewrite, title overlay, branded filename — user did not toggle on; defaults apply: title overlay + branded filename stay; VD rewrite SKIPPED when entity detected) |
| Entity reference caching | Local folder + DB table + QID key | JSON sidecar only; Redis-only cache; No cache |
| Notification channel | Telegram Bot API | WhatsApp; Email (Resend); Admin dashboard only |

---

## Implementation Feasibility

| Concern | Status | Mitigation |
|---|---|---|
| Wikidata rate limit | Low risk | Public, 500 req/hour; ~10 articles/day safe; Laravel cache (30-day TTL) for QID lookups |
| Commons API | Low risk | No hard limit with User-Agent header |
| Sonnet NER accuracy | Medium risk | Add 3-5 example I/O pairs in plugin prompt; notability gate (sitelinks ≥ 5) catches noise |
| License auto-detection | Low risk | Commons `extmetadata.LicenseShortName` is reliable; unknown → reject |
| Telegram bot setup friction | One-time | Document BotFather flow in AboutSettings card; "Send test" button verifies config |
| Backwards compatibility | Safe | All new fields nullable; old articles without `entity_refs[]` fall through existing creator-face path |
| Placeholder risk | Zero | Every step uses real API, real DB field, real storage path |
| Migration of existing covers | Deferred to Phase 2 | Out of scope for v1 — only new articles detect entities; Phase 2 adds backfill script |

---

## Open Risks

1. **Sonnet NER drift on ambiguous names** — "Tesla" (company vs person); "Apple" (brand vs fruit). Mitigation: Wikidata P31 (instance-of) filter during notability check.
2. **Multi-entity covers** — "Musk meets Altman" needs 2 face refs. Plugin should support `entity_refs[]` with ≥2 entries; verify GeminiGen handles 2+ face refs gracefully. Test case required.
3. **Indonesian public figures** — Wikidata coverage for Jokowi (Q252078), Prabowo (Q318925) exists, but P18 licenses vary. Verify during integration testing.
4. **Fictional/historical persons** — "Einstein" has Wikidata but "Iron Man" is fictional. Entity type gate: require `P31: human (Q5)` for persons only.
5. **Telegram token in settings table** — single-user admin, acceptable risk; ensure log sanitization strips token from `progress_log`.
6. **Entity cache staleness** — if Wikimedia updates a P18 photo (e.g., new official portrait), cached local file is stale. Mitigation: `refresh_after` column on `entity_references`; future Phase 2 admin Library page for curation.

---

## Out of Scope (v1)

- **Migration/backfill** — Existing published articles stay as-is; only new articles going through `/article-images` get entity detection. Phase 2 can add an audit script.
- **Admin Entity Library page** — Browse/curate cached entities. Phase 2.
- **Multi-entity VD rewriting** — Currently skip VD rewrite when any entity detected; Phase 2 could rewrite VD using multi-entity descriptions.
- **Telegram callback_data handlers** — `skip:{id}` and `reject:{id}` inline buttons defer actions; Phase 1 ships with URL-based buttons only (always open admin UI). Phase 2 adds callback_data webhook endpoint.
- **Non-Wikimedia sources** — Official press kits (Anthropic newsroom, SpaceX press page). Phase 3+ if needed.

---

## Next Steps

1. Run `gaspol-plan` to append `## Implementation Plan` to this file with phased execution breakdown (migrations → services → plugin → backend gate → UI → Telegram → integration tests).
2. Prerequisite: confirm Telegram Bot setup workflow with admin (BotFather account creation + chat_id discovery).
3. Prerequisite: verify Wikidata coverage for target Indonesian names (manual test: Jokowi, Prabowo, Megawati, Anies).
4. Plugin version bump coordination: `article-content-writer` 2.3.0 → 2.4.0, update [`CLAUDE.md`](../../CLAUDE.md) Content Pipeline section after v1 ships.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship named-entity detection + Wikimedia auto-fetch + manual-upload fallback + Telegram alerting + local entity cache so that blog cover images show the correct public figure and landmark (not Ali's face and not a generic building) when the article subject is a notable person or place.

### Architecture Context

Pulled from [root CLAUDE.md](../../CLAUDE.md) + [backend CLAUDE.md](../../backend/CLAUDE.md):

- **Cover branding single source of truth:** [`CoverBrandingEnhancer.php`](../../backend/app/Services/CoverBrandingEnhancer.php) — all cover-image policy decisions route through `enhance()`. Line 51 is the unconditional creator-face inject that this plan gates.
- **Dispatch orchestrator:** [`ImageGenerationService::triggerForIdea`](../../backend/app/Services/ImageGenerationService.php#L168) calls `$enhancer->enhance()` on every prompt before queuing to GeminiGen.
- **Pipeline phase mapping:** `/article-images` runs at Gate 2 (after user approves article text at Gate 1). Reuses existing Context Extraction §3.5 from plugin SKILL.md.
- **Status enum:** `content_ideas.status` is MySQL ENUM — all changes require `ALTER TABLE MODIFY COLUMN` (pattern established in [`2026_04_17_110000_expand_content_ideas_status_with_failed.php`](../../backend/database/migrations/2026_04_17_110000_expand_content_ideas_status_with_failed.php)).
- **Settings group pattern:** Creator brand settings (5 rows, `firstOrCreate` seeder) established in [`CreatorBrandSettingsSeeder.php`](../../backend/database/seeders/CreatorBrandSettingsSeeder.php). Telegram settings mirror this exactly (6 rows).
- **API Response format:** `{success: true, data: ..., message: ...}` or `{success: false, error: {code, message}}` per root CLAUDE.md controller pattern.
- **Test framework:** PHPUnit (not Pest) — existing tests in [`tests/Feature/`](../../backend/tests/Feature/) (e.g., [`CoverBrandingEnhancerTest.php`](../../backend/tests/Feature/CoverBrandingEnhancerTest.php)) use `class ... extends TestCase` with Mockery for Setting facade aliasing.
- **Laravel version:** Backend is Laravel 12 + PHP 8.2 (root CLAUDE.md) — use queued jobs via `dispatch()`, not sync. No `app/Jobs` folder exists yet → first job in project.
- **No placeholder rule:** Every API endpoint, DB field, service method must be real. No TODO stubs.

### Tech Stack

- **Backend:** Laravel 12, PHP 8.2, MySQL 8, Eloquent, PHPUnit, Mockery, Laravel Queue (database driver)
- **Plugin:** article-content-writer v2.3.0 → v2.4.0 (Claude CLI plugin, file-edit-only)
- **Frontend:** Vue 3.5 + Composition API, Pinia 3, TanStack Query 5.90, Tailwind 4
- **External APIs:** Wikidata SPARQL (`query.wikidata.org`), Commons MediaWiki (`commons.wikimedia.org/w/api.php`), Telegram Bot (`api.telegram.org`)
- **Deployment:** GitHub Actions CI/CD (`.github/workflows/deploy.yml`) auto-deploys on push to `main`; seeders run in [`scripts/deploy.sh`](../../scripts/deploy.sh)

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Cover branding enhancer | `CoverBrandingEnhancer::enhance()` | direct call | Yes | Modify: add entity gate |
| GeminiGen dispatch | `ImageGenerationService::queue()` | Service | Yes | Modify: pass `entity_refs[]` through |
| Content idea storage | `content_ideas` table | Eloquent | Yes | Modify: add `pending_manifest` JSON column + extend status enum |
| Entity cache table | `entity_references` | Eloquent | No | Create new table + model |
| Wikidata SPARQL | `https://query.wikidata.org/sparql` | HTTP | No | Create `EntityReferenceService::resolveWikidataQid()` |
| Commons license API | `https://commons.wikimedia.org/w/api.php` | HTTP | No | Create `EntityReferenceService::fetchCommonsLicense()` |
| Entity file download | `Http::get()` → `Storage::disk('public')` | existing pattern | Yes | Reuse pattern from `ImageGenerationService::downloadAndStore` |
| Telegram Bot API | `https://api.telegram.org/bot{token}/sendMessage` | HTTP | No | Create `TelegramNotificationService::sendManifestAlert()` |
| Queued job dispatch | Laravel Queue | `dispatch()` | Framework | Create first `app/Jobs/DispatchTelegramNotification.php` |
| Settings group | `settings` table + `SettingsController` | API | Yes | Add `telegram` group endpoints mirroring `creator_brand` |
| Plugin → backend lookup | `/automation/entity-refs/lookup` | HTTP | No | Create new automation endpoint (public, token-gated) |
| Manifest-needed flow | `/automation/.../progress` existing endpoint | HTTP | Yes | Extend `updateProgress` to persist manifest + dispatch Telegram |
| Image Config modal | [`ImageGeneration.vue`](../../frontend/src/components/admin/) | Vue component | Yes | Extend: add entity chip row + upload slot |
| About Settings page | [`AboutSettings.vue`](../../frontend/src/views/admin/AboutSettings.vue) | Vue | Yes | Extend: add Telegram card below Creator Brand |
| Settings store | [`settings.js`](../../frontend/src/stores/settings.js) | Pinia | Yes | Extend: `telegramSettings` + `saveTelegramSettings()` |
| Content engine composable | [`useContentEngine.js`](../../frontend/src/composables/useContentEngine.js) | Composable | Yes | Extend: `uploadEntityReference` + `skipEntityReference` |

---

### Phase A: Database foundation — schema + seeders

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/database/migrations/2026_04_20_000001_create_entity_references_table.php`
- Create: `backend/database/migrations/2026_04_20_000002_add_pending_manifest_to_content_ideas.php`
- Create: `backend/database/migrations/2026_04_20_000003_expand_content_ideas_status_with_awaiting_upload.php`
- Create: `backend/database/seeders/TelegramSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (register `TelegramSettingsSeeder::class`)
- Modify: `scripts/deploy.sh` (add `TelegramSettingsSeeder` to deploy seeder list)
- Test: `backend/tests/Feature/EntityReferencesMigrationTest.php`
- Test: `backend/tests/Feature/TelegramSettingsSeederTest.php`

**Steps:**
1. Write failing test `EntityReferencesMigrationTest::test_table_has_required_columns` asserting `Schema::hasColumns('entity_references', [...])`. Expected error: `BadMethodCallException: table 'entity_references' does not exist`.
2. Run `php artisan test --filter=EntityReferencesMigrationTest` — confirm fail.
3. Create migration 1: `entity_references` table with columns: `id`, `qid` (nullable unique varchar(20)), `name` varchar(255), `entity_type` enum, `local_path` varchar(500), `local_url` varchar(500), `wikimedia_source_url` text nullable, `license` varchar(50), `attribution` text nullable, `source` enum('wikimedia','user_upload') default 'wikimedia', `fetched_at` timestamp, `last_used_at` timestamp nullable, `use_count` int default 1, `refresh_after` timestamp nullable, timestamps. Indexes: unique `qid`, `name`, compound `(entity_type, source)`.
4. Create migration 2: `content_ideas.pending_manifest` JSON nullable column.
5. Create migration 3: raw `ALTER TABLE content_ideas MODIFY COLUMN status ENUM(..., 'awaiting_manual_upload', ...)` following [`2026_04_17_110000_expand_content_ideas_status_with_failed.php`](../../backend/database/migrations/2026_04_17_110000_expand_content_ideas_status_with_failed.php) pattern.
6. Run `php artisan migrate` — confirm no errors.
7. Run test, confirm pass.
8. Write failing test `TelegramSettingsSeederTest::test_seeds_six_rows_idempotently` asserting 6 rows in `settings` where `group='telegram'` after running seeder twice.
9. Run test — confirm fail.
10. Create `TelegramSettingsSeeder` with 6 `firstOrCreate` rows: `telegram_bot_token` (null/text), `telegram_chat_id` (null/text), `telegram_enabled` (false/text), `telegram_notify_manifest_needed` (true/text), `telegram_notify_generation_failed` (true/text), `telegram_notify_publish_success` (false/text).
11. Register in `DatabaseSeeder::run()` + add to `scripts/deploy.sh` seeder list.
12. Run `php artisan db:seed --class=TelegramSettingsSeeder` — verify rows exist.
13. Run test, confirm pass.
14. Commit: `feat(content-engine): add entity_references table + telegram settings foundation`

**Verification:**
- [ ] `php artisan migrate:fresh --seed` completes without error
- [ ] `entity_references` table exists with all 14 columns + 3 indexes
- [ ] `content_ideas.pending_manifest` column exists (JSON nullable)
- [ ] `content_ideas.status` enum includes `awaiting_manual_upload`
- [ ] Running `TelegramSettingsSeeder` twice produces exactly 6 rows (idempotent)
- [ ] No placeholder/TODO comments in new code
- [ ] `php artisan test --filter=EntityReferencesMigrationTest` passes
- [ ] `php artisan test --filter=TelegramSettingsSeederTest` passes

---

### Phase B: EntityReference model + EntityReferenceService

**Estimated time:** 18 minutes

**Files:**
- Create: `backend/app/Models/EntityReference.php`
- Create: `backend/app/Services/EntityReferenceService.php`
- Test: `backend/tests/Unit/EntityReferenceServiceTest.php`
- Test: `backend/tests/Feature/EntityReferenceModelTest.php`

**Steps:**
1. Write failing test `EntityReferenceModelTest::test_increment_use_count_updates_last_used_at`. Expected error: `Class 'App\Models\EntityReference' not found`.
2. Run test — confirm fail.
3. Create `EntityReference` Eloquent model with `$fillable`, `$casts` (`fetched_at`/`last_used_at`/`refresh_after` as `datetime`, `use_count` as int), and `incrementUseCount()` method that does `$this->increment('use_count')` + `$this->update(['last_used_at' => now()])`.
4. Run test — confirm pass.
5. Write failing test `EntityReferenceServiceTest::test_find_or_fetch_returns_cached_when_qid_exists` using `Http::fake()` to verify NO Wikidata call is made when cache hit. Expected error: `Class 'App\Services\EntityReferenceService' not found`.
6. Run test — confirm fail.
7. Create `EntityReferenceService` with public method `findOrFetch(string $name, ?string $type = null): ?array` returning `['qid', 'name', 'type', 'url', 'license', 'attribution']` or null. Structure:
   - Private `resolveWikidataQid(string $name): ?string` — SPARQL label search (en + id) via `Http::get('https://query.wikidata.org/sparql', ['query' => $sparql, 'format' => 'json'])` with `User-Agent: alisadikinma.com-content-engine/1.0`. Cache result in Laravel cache for 30 days keyed by normalized name.
   - Private `fetchCommonsLicense(string $filename): ?array` — MediaWiki `action=query&prop=imageinfo&iiprop=extmetadata|url&titles=File:{filename}`. Returns `['license', 'attribution', 'url']` or null.
   - Private `downloadAndStore(string $url, string $qid, string $type, string $name): string` — mirrors `ImageGenerationService::downloadAndStore` pattern. Saves to `public/uploads/entity-refs/{type}/{qid}_{slug}.{ext}`, returns `url('/storage/...')`.
   - Public `findOrFetch`: lookup cached by QID → if miss, resolve QID → Commons fetch → license whitelist check → download → insert row → return.
8. License whitelist constant: `const ALLOWED_LICENSES = ['CC0', 'Public domain', 'PD-USGov', 'CC BY 4.0', 'CC-BY-4.0'];` — case-insensitive match against Commons `LicenseShortName`.
9. Run test — confirm pass.
10. Write failing test `EntityReferenceServiceTest::test_fetch_rejects_cc_by_sa_license` — mock Commons response with `CC BY-SA 4.0` → expect `findOrFetch` returns null + NO row inserted.
11. Run test — fail expected (no logic yet).
12. Add license filter in `findOrFetch` before insert.
13. Run test — confirm pass.
14. Write failing test `EntityReferenceServiceTest::test_fetch_skips_when_notability_low` — mock SPARQL response with sitelinks=2 → expect null.
15. Add notability gate (sitelinks ≥ 5) in `resolveWikidataQid`.
16. Run test — confirm pass.
17. Commit: `feat(content-engine): add EntityReference model + service with Wikidata/Commons integration`

**Verification:**
- [ ] `php artisan test --filter=EntityReference` — all 4+ tests pass
- [ ] Service rejects CC-BY-SA, CC-BY-ND, fair-use via whitelist
- [ ] Service rejects entities with sitelinks < 5 (notability gate)
- [ ] Second call for same QID returns cached row without HTTP round-trip (verified via `Http::assertNothingSent()` scope)
- [ ] Files written to `storage/app/public/uploads/entity-refs/{type}/{qid}_{slug}.{ext}`
- [ ] No placeholder/TODO comments in new code

---

### Phase C: CoverBrandingEnhancer entity gate

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/CoverBrandingEnhancer.php`
- Modify: `backend/tests/Feature/CoverBrandingAutoInjectTest.php` (append new cases)
- Test: `backend/tests/Feature/CoverBrandingEntityGateTest.php` (new)

**Steps:**
1. Write failing test `CoverBrandingEntityGateTest::test_cover_with_person_entity_skips_creator_face`. Arrange: prompt with `type='cover'`, `entity_refs = [['qid' => 'Q115468560', 'entity_type' => 'person', 'url' => 'https://.../dario.jpg', ...]]`. Act: call `enhance()`. Assert: `$result['face_refs']` does NOT contain creator URL; `$result['file_urls']` DOES contain entity URL.
2. Run test — confirm fail (current code unconditionally prepends creator face).
3. Modify `CoverBrandingEnhancer::enhance()` — inside `if ($type === 'cover')` branch, add gate:
   ```php
   $entityRefs = $prompt['entity_refs'] ?? [];
   $hasPersonEntity = collect($entityRefs)->contains(fn($e) => ($e['entity_type'] ?? null) === 'person');

   if ($hasPersonEntity) {
       // Merge entity URLs into file_urls (GeminiGen consumes these as refs)
       $prompt = $this->mergeEntityRefsIntoFileUrls($prompt, $entityRefs);
       // SKIP prependCreatorFace — subject is the detected public figure
       // SKIP VD rewrite — plugin VD already names the person
   } else {
       $prompt = $this->prependCreatorFace($prompt, $idea);
   }
   ```
4. Add private helper `mergeEntityRefsIntoFileUrls(array $prompt, array $entityRefs): array` that merges each `$e['url']` into `$prompt['file_urls']` (idempotent dedupe).
5. Run test — confirm pass.
6. Write failing test `test_cover_without_entities_keeps_existing_creator_face_behavior` — regression guard for existing articles.
7. Run — confirm pass (no behavior change when `entity_refs` empty).
8. Write failing test `test_cover_with_only_landmark_entity_still_injects_creator_face` — landmark alone (no person) means Ali IS in the scene visiting the landmark; creator face still needed.
9. Run — confirm pass (gate only skips on person entity).
10. Write failing test `test_cover_with_person_entity_still_applies_watermark` — watermark + title + filename branding MUST NOT regress.
11. Run — confirm pass.
12. Commit: `feat(cover-branding): gate creator-face inject when entity person detected`

**Verification:**
- [ ] `php artisan test --filter=CoverBranding` — all existing + 3 new tests pass
- [ ] Regression: cover without `entity_refs` still prepends creator face (backwards compat)
- [ ] New: cover with person entity_refs does NOT prepend creator face
- [ ] New: cover with ONLY landmark entity_refs DOES still prepend creator face (Ali visits landmark scene)
- [ ] Watermark + title overlay + filename branding unchanged in all cases
- [ ] No placeholder/TODO comments in new code

---

### Phase D: ImageGenerationService entity_refs wiring

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php`
- Modify: `backend/tests/Feature/ImageGenerationTriggerForIdeaTest.php` (append new cases)

**Steps:**
1. Write failing test `ImageGenerationTriggerForIdeaTest::test_trigger_passes_entity_refs_to_queue_as_style_refs` — arrange idea with `image_prompts[0].entity_refs = [...]`; use `Http::fake()` to capture GeminiGen multipart; assert `file_urls` array includes entity URLs.
2. Run — confirm fail.
3. Modify `ImageGenerationService::triggerForIdea` — after `$enhanced = $enhancer->enhance(...)`, merge `$enhanced['file_urls']` into the `styleRefs` parameter of `queue()` (already happens, but verify entity URLs arrive via enhancer output).
4. Run — confirm pass.
5. Commit: `feat(image-gen): propagate entity_refs through dispatch to GeminiGen`

**Verification:**
- [ ] `php artisan test --filter=ImageGenerationTriggerForIdea` — all tests pass
- [ ] Entity URLs appear in GeminiGen multipart request as `file_urls` entries
- [ ] No duplicate URLs in `file_urls` when entity + brand + watermark all present
- [ ] No placeholder/TODO comments

---

### Phase E: TelegramNotificationService + queued Job + Settings endpoints

**Estimated time:** 20 minutes

**Files:**
- Create: `backend/app/Services/TelegramNotificationService.php`
- Create: `backend/app/Jobs/DispatchTelegramNotification.php`
- Modify: `backend/app/Http/Controllers/Api/SettingsController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/TelegramNotificationServiceTest.php`
- Test: `backend/tests/Feature/DispatchTelegramNotificationJobTest.php`
- Test: `backend/tests/Feature/TelegramSettingsApiTest.php`

**Steps:**
1. Write failing test `TelegramNotificationServiceTest::test_send_manifest_alert_posts_to_telegram_api` using `Http::fake()`. Expected error: `Class 'App\Services\TelegramNotificationService' not found`.
2. Run — confirm fail.
3. Create `TelegramNotificationService` with:
   - `sendManifestAlert(ContentIdea $idea): bool` — reads `telegram_bot_token` + `telegram_chat_id` from Setting, short-circuits if `telegram_enabled !== 'true'` OR `telegram_notify_manifest_needed !== 'true'`, formats Markdown message from `$idea->pending_manifest`, POSTs to `https://api.telegram.org/bot{token}/sendMessage` with inline keyboard (Open Admin URL + Skip/Reject callback_data).
   - `sendGenerationFailed(ContentIdea $idea): bool` — same pattern, different template + different toggle check.
   - `sendPublishSuccess(ContentIdea $idea): bool` — same pattern.
   - Private `send(string $text, array $inlineKeyboard): bool` — shared HTTP call with 10s timeout.
4. Run test — confirm pass.
5. Write failing test `TelegramNotificationServiceTest::test_does_not_send_when_disabled` — set `telegram_enabled='false'` → expect no HTTP call via `Http::assertNothingSent()`.
6. Run — confirm pass.
7. Write failing test `DispatchTelegramNotificationJobTest::test_job_dispatches_to_service` using `Queue::fake()` + `Bus::fake()`. Expected error: `Class 'App\Jobs\DispatchTelegramNotification' not found`.
8. Run — confirm fail.
9. Create `app/Jobs/DispatchTelegramNotification.php` — queued job with `ShouldQueue`, `$tries = 3`, `$backoff = [30, 120, 300]` seconds. Constructor accepts `ContentIdea $idea` + `string $notificationType`. `handle(TelegramNotificationService $svc)` dispatches to matching service method.
10. Run test — confirm pass.
11. Write failing test `TelegramSettingsApiTest::test_get_returns_all_six_settings` — GET `/api/admin/settings/telegram` returns all 6 keys.
12. Run — confirm fail.
13. Extend `SettingsController` — add `getTelegramSettings()` + `updateTelegramSettings()` parallel to `getCreatorBrandSettings`/`updateCreatorBrandSettings` pattern. Sanitize `telegram_bot_token` from response (return only last 4 chars `***1234` for security; admin can re-paste to update).
14. Add `testTelegramNotification()` action — sends a test message using current settings, returns pass/fail + Telegram API response.
15. Register 3 routes in `routes/api.php` under `auth:sanctum` + `admin` middleware:
    - `GET /api/admin/settings/telegram`
    - `PUT /api/admin/settings/telegram`
    - `POST /api/admin/settings/telegram/test`
16. Run tests — confirm pass.
17. Commit: `feat(telegram): add TelegramNotificationService + queued job + settings API`

**Verification:**
- [ ] `php artisan test --filter=Telegram` — all tests pass
- [ ] `GET /api/admin/settings/telegram` returns 6 rows (with bot_token masked)
- [ ] `PUT /api/admin/settings/telegram` persists values
- [ ] `POST /api/admin/settings/telegram/test` sends actual Telegram message (manual verification with real bot token)
- [ ] Job retries 3x on HTTP 5xx from Telegram API with exponential backoff
- [ ] No placeholder/TODO comments

---

### Phase F: ContentIdeaController — manifest persistence + upload/skip/lookup endpoints

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (add 2 methods)
- Modify: automation controller that handles `/automation/content-ideas/{id}/progress` (find exact path via grep)
- Modify: `backend/routes/api.php` (add 3 routes)
- Test: `backend/tests/Feature/ManifestNeededTriggersTelegramTest.php`
- Test: `backend/tests/Feature/UploadEntityReferenceTest.php`
- Test: `backend/tests/Feature/EntityRefsLookupEndpointTest.php`

**Steps:**
1. Write failing test `ManifestNeededTriggersTelegramTest::test_progress_with_manifest_needed_persists_manifest_and_dispatches_job` using `Bus::fake()` → expect `DispatchTelegramNotification` pushed.
2. Run — confirm fail.
3. Locate the automation progress endpoint (likely `ContentIdeaAutomationController` or `Automation\ContentIdeaController`; grep for `'manifest_needed'` or `save-image-prompts`).
4. Extend `updateProgress` method: when incoming payload has `step='manifest_needed'` AND `manifest` array present:
   - Validate manifest structure (`brand[]` + `entity[]` arrays)
   - Update `$idea->pending_manifest = $payload['manifest']`
   - Update `$idea->status = 'awaiting_manual_upload'`
   - Dispatch `DispatchTelegramNotification::dispatch($idea, 'manifest_needed')`
5. Run test — confirm pass.
6. Write failing test `UploadEntityReferenceTest::test_uploads_file_and_populates_entity_refs` — POST multipart to `/api/admin/content-engine/ideas/{id}/upload-entity-reference` with `entity_name=Dario Amodei` + `file=dario.jpg` → expect `image_prompts[0].entity_refs[]` updated, file saved, `EntityReference` row inserted with `source='user_upload'`.
7. Run — confirm fail.
8. Add `ContentIdeaController::uploadEntityReference($id)` — validates entity_name + file, saves to `public/uploads/entity-refs/person/user_{uuid}_{slug}.{ext}`, inserts `EntityReference` row (qid=null, source=user_upload), merges into `$idea->generated_article['image_prompts'][$i]['entity_refs']` for every segment that referenced this entity, clears corresponding manifest.entity[] slot, resets status to `article_ready` if all entity slots resolved.
9. Run test — confirm pass.
10. Write failing test `UploadEntityReferenceTest::test_skip_forces_creator_face_fallback`.
11. Add `ContentIdeaController::skipEntityReference($id)` — removes entity from `image_prompts[].entity_refs`, marks manifest slot as `status='skipped'`, resumes pipeline.
12. Run — confirm pass.
13. Write failing test `EntityRefsLookupEndpointTest::test_lookup_returns_cached_entity_when_exists`.
14. Add public automation endpoint `GET /api/automation/entity-refs/lookup?name={name}&type={type}` (bearer-token gated like existing automation routes) — returns `{cached: true, qid, url, license, attribution}` or `{cached: false}` for plugin to decide whether to hit Wikidata.
15. Register 3 new routes in `routes/api.php`:
    - `POST /api/admin/content-engine/ideas/{id}/upload-entity-reference` (auth:sanctum)
    - `POST /api/admin/content-engine/ideas/{id}/skip-entity-reference` (auth:sanctum)
    - `GET /api/automation/entity-refs/lookup` (bearer token)
16. Run all 3 feature tests — confirm pass.
17. Commit: `feat(content-engine): manifest persistence + entity upload/skip/lookup endpoints`

**Verification:**
- [ ] `php artisan test --filter="ManifestNeeded|UploadEntityReference|EntityRefsLookup"` — all pass
- [ ] `POST /automation/.../progress` with `step=manifest_needed` triggers `DispatchTelegramNotification` dispatch via `Bus::fake()->assertDispatched()`
- [ ] Upload endpoint stores file + writes `entity_references` row + updates `image_prompts[].entity_refs`
- [ ] Skip endpoint resumes pipeline via existing `continue-pipeline` path
- [ ] Lookup endpoint respects bearer token (401 without)
- [ ] No placeholder/TODO comments

---

### Phase G: Plugin /article-images v2.4.0 — NER + Wikidata + Cache lookup

**Estimated time:** 25 minutes

**Files:**
- Modify: `D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md`
- Modify: `D:/Projects/claude-plugin/article-content-writer/plugin.json` (bump version 2.3.0 → 2.4.0)
- (Optional) Modify: `D:/Projects/claude-plugin/article-content-writer/references/global-config.md` if entity patterns belong in shared refs

**Steps:**
1. Extend SKILL.md §3.5 (Context Extraction) — add Named Entity step after existing brand/product extraction:
   ```
   For each candidate entity (person / landmark / logo / product):
     - Check structural subject indicator: name appears in title OR H1/H2
     - Record {name, type, confidence}
   ```
2. Add new §3.5b (Wikidata Lookup + Cache):
   ```
   FOR EACH detected entity:
     a. Cache lookup first:
        curl "{api_url}/automation/entity-refs/lookup?name={name}&type={type}"
        → if cached: use returned URL, skip Wikidata
     b. Wikidata SPARQL (only on cache miss):
        Query query.wikidata.org/sparql for Q-ID by label match (en + id)
        Require sitelinks ≥ 5 AND P18 exists AND P31 matches entity type
        (P31: Q5 for person, Q41176 for building, Q4830453 for business, etc.)
     c. Commons license check:
        MediaWiki action=query&prop=imageinfo&iiprop=extmetadata
        Accept LicenseShortName IN {CC0, PD, PD-USGov, CC BY 4.0}
        Reject CC BY-SA, CC BY-ND, fair-use
     d. On success: record entity to image_prompts[i].entity_refs[]
     e. On failure (notability, license, missing): add to manifest.entity[] as required=true
   ```
3. Extend §3.6 (Manifest) — add `entity[]` category alongside existing `brand[]`. Schema:
   ```json
   "entity": [
     {
       "entity_name": "Dario Amodei",
       "entity_type": "person",
       "qid": "Q115468560",
       "used_in": ["Cover"],
       "status": "missing" | "fetched",
       "reason": "...",
       "required": true | false
     }
   ]
   ```
4. Extend §7 (Output Format) — add `entity_refs[]` field on each `image_prompts[i]`:
   ```json
   "entity_refs": [
     {
       "qid": "Q115468560",
       "name": "Dario Amodei",
       "entity_type": "person",
       "url": "https://alisadikinma.com/storage/entity-refs/person/Q115468560_dario-amodei.jpg",
       "license": "CC-BY-4.0",
       "attribution": "© TechCrunch via Wikimedia Commons"
     }
   ]
   ```
5. Add example I/O pairs to anchor Sonnet NER format — 3 examples covering: (a) person-subject article, (b) person-mention article (skip), (c) landmark + person combined.
6. Bump `plugin.json` version 2.3.0 → 2.4.0.
7. Regenerate `refs-images.md` compiled reference file on VPS (deployment artifact; document in plan — no code change required here, but note for deploy step).
8. Manual smoke test: run plugin locally against test idea with article about Dario Amodei — verify entity_refs populated and cached on 2nd run.
9. Commit plugin changes in plugin repo: `feat(article-images): v2.4.0 — named entity detection + Wikidata + cache lookup`

**Verification:**
- [ ] SKILL.md §3.5 mentions NER for persons/landmarks/logos/products
- [ ] SKILL.md §3.5b documents Wikidata SPARQL + Commons license filter
- [ ] SKILL.md §3.6 schema includes `entity[]` category
- [ ] SKILL.md §7 output schema includes `entity_refs[]` field
- [ ] plugin.json version = 2.4.0
- [ ] Manual smoke test: entity detected for "Anthropic CEO Visits..." article with Dario Amodei
- [ ] Cache hit verified: 2nd article about same entity makes zero Wikidata calls (trace via backend log)
- [ ] No placeholder/TODO comments (plugin SKILL.md explicitly never ship with TODO)

---

### Phase H: Frontend Gate 2 UI — entity chip row + upload slot + idea badge

**Estimated time:** 20 minutes

**Design Deliverable:** Segment-card extension follows existing Creator Brand + reference chip patterns (cinema-glass aesthetic, green/red accent chips). No new design system tokens needed — reuse `--accent-gold` for status-ok chips, red-500 tailwind token for required-missing. See root CLAUDE.md "ULTRA Redesign" section for dark cinema + gold/cyan dual accent tokens.

**Files:**
- Create: `frontend/src/components/admin/EntityUploadSlot.vue`
- Modify: `frontend/src/components/admin/ImageGeneration.vue` (or wherever Image Config modal lives — grep for `face_refs` component usage)
- Modify: `frontend/src/views/admin/ContentEngine.vue` (add `awaiting_manual_upload` badge)
- Modify: `frontend/src/composables/useContentEngine.js` (add 2 methods)
- Test: `frontend/src/__tests__/EntityUploadSlot.test.js` (if Vitest configured) — otherwise manual browser smoke test

**Steps:**
1. Locate Image Config modal file via grep `face_refs` in `frontend/src/components/admin/` + `frontend/src/views/admin/`.
2. Create `EntityUploadSlot.vue` with props: `entityName`, `entityType`, `status` (`fetched|missing|skipped`), `fetchedUrl`, `onUpload`, `onSkip`. Render:
   - status=fetched: green chip `{typeIcon} {name} · {license}` with thumbnail preview on hover
   - status=missing: red banner + file input + Skip button
   - status=skipped: amber chip `⏭️ {name} skipped — using creator face`
3. In `ImageGeneration.vue`:
   - Add computed `entityRefsBySegment` grouping `entity_refs[]` per segment position
   - Render `<EntityUploadSlot>` chip row under segment card's Visual Direction text, above Style/Model/Ratio pills
   - For each missing entity from `pending_manifest.entity[]`: render upload-required banner with red border on segment card
4. In `useContentEngine.js`:
   - Add `uploadEntityReference(ideaId, entityName, file)` — POST multipart, invalidate TanStack Query cache
   - Add `skipEntityReference(ideaId, entityName)` — POST JSON, invalidate cache
5. In `ContentEngine.vue`:
   - Add status-aware badge mapping: `awaiting_manual_upload` → amber pill `⚠️ Awaiting manual upload`
   - Play ▶ icon tooltip: "Resume image generation after upload"
6. Manual browser test (dev server):
   - `cd frontend && npm run dev`
   - Open admin → Content Engine → create test idea with article about Dario Amodei
   - Trigger /article-images (should block with manifest)
   - Verify modal shows red banner for Dario, green chip for White House
   - Upload a face photo → verify segment resolves
7. Commit: `feat(content-engine-ui): entity reference upload slot + status badges`

**Verification:**
- [ ] `npm run build` succeeds with zero TypeScript errors
- [ ] Image Config modal shows fetched entity chip (green) + missing entity slot (red)
- [ ] Upload button sends correct multipart to `/admin/content-engine/ideas/{id}/upload-entity-reference`
- [ ] Skip button sends correct JSON to `/admin/content-engine/ideas/{id}/skip-entity-reference`
- [ ] Idea row in list shows amber badge when `status='awaiting_manual_upload'`
- [ ] Manual browser smoke test (end-to-end with live admin): red banner appears for entity without Wikimedia CC0 photo
- [ ] No placeholder/TODO comments

---

### Phase I: AboutSettings Telegram card

**Estimated time:** 12 minutes

**Design Deliverable:** New card under Creator Brand card — identical structure (title + helper text + form fields + submit). Reuse Creator Brand card component shell.

**Files:**
- Modify: `frontend/src/views/admin/AboutSettings.vue` (add Telegram card below Creator Brand)
- Modify: `frontend/src/stores/settings.js` (add telegramSettings state + actions)

**Steps:**
1. In `settings.js` store: add `telegramSettings` state object + `fetchTelegramSettings()` + `saveTelegramSettings(payload)` + `sendTestTelegram()` actions.
2. In `AboutSettings.vue`:
   - Add new card section below Creator Brand card titled "Telegram Notifications — Manual Upload Alerts"
   - Form fields: bot_token (password input with show/hide), chat_id (text), enabled (toggle), 3 notification toggles (manifest_needed, generation_failed, publish_success)
   - Submit button: "Save Telegram Settings" (triggers `saveTelegramSettings`)
   - Test button: "Send test message" (triggers `sendTestTelegram`) — disabled when enabled=false OR bot_token empty
   - Status display area below Test button: shows "✅ Sent successfully" or "❌ {error}" for 5 seconds after test
3. Helper text above form: short guide "Create a bot via @BotFather on Telegram → paste token below → message your bot → find your chat_id at `/getUpdates` API"
4. Manual browser test:
   - Open About Settings
   - Paste real bot token (from BotFather) + chat_id
   - Click Save → verify settings persist (reload page, still visible)
   - Toggle Enabled → click Test → verify Telegram message received on phone
5. Commit: `feat(admin-settings): Telegram notifications card in About Settings`

**Verification:**
- [ ] `npm run build` succeeds
- [ ] Telegram card renders below Creator Brand card
- [ ] Save button persists values (reload → still there, token masked as `***1234`)
- [ ] Test button sends real message when fully configured (manual verification with real bot)
- [ ] Toggles disable form when Enabled is off
- [ ] No placeholder/TODO comments

---

### Phase J: End-to-end integration test — full entity flow

**Estimated time:** 15 minutes

**Files:**
- Create: `backend/tests/Feature/NamedEntityCoverE2ETest.php`

**Steps:**
1. Write failing test `NamedEntityCoverE2ETest::test_full_flow_dario_amodei_article` covering:
   - Arrange: seed ContentIdea with article title "Anthropic CEO Dario Amodei Visits White House"
   - Mock Wikidata SPARQL: return QID Q115468560 (Dario) with sitelinks=12, P18=DarioAmodei.jpg
   - Mock Commons: return CC-BY-SA license for Dario (license fail)
   - Mock Wikidata: return QID Q35525 (White House) with sitelinks=50, P18=WhiteHouse.jpg
   - Mock Commons: return PD-USGov for White House (success)
   - Simulate plugin POST `manifest_needed` → verify manifest persisted, Telegram job dispatched
   - Simulate user upload for Dario (POST multipart)
   - Simulate plugin completion → image_prompts finalized with both entity_refs
   - Call `ImageGenerationService::triggerForIdea()` + stub GeminiGen
   - Assert: GeminiGen multipart contains Dario URL + White House URL in `file_urls`
   - Assert: GeminiGen multipart does NOT contain Ali's creator profile_photo URL
   - Assert: GeminiGen multipart DOES contain watermark logo URL
   - Assert: `planned_filename` still follows `alisadikinma-*-cover.png` pattern
2. Run — confirm fail.
3. Iterate fixes until all assertions pass (may expose bugs in earlier phases — fix as found).
4. Run `php artisan test --filter=NamedEntity` — confirm green.
5. Run full test suite `php artisan test` — confirm no regressions.
6. Commit: `test(content-engine): end-to-end named-entity cover flow integration test`

**Verification:**
- [ ] `php artisan test` — full suite green (zero regressions)
- [ ] E2E test passes without any placeholder/mock-of-real-integration (only external HTTP is faked)
- [ ] Creator face NOT in final GeminiGen payload when person entity detected
- [ ] Watermark + title overlay + branded filename all preserved
- [ ] Entity refs cached in `entity_references` table for reuse
- [ ] No placeholder/TODO comments in any file touched during this phase

---

### Post-phase: Documentation + Deployment

**Estimated time:** 8 minutes

1. Update root [`CLAUDE.md`](../../CLAUDE.md) Content Pipeline section: document new entity detection flow + entity_refs field + Telegram integration + `awaiting_manual_upload` status.
2. Update [`backend/CLAUDE.md`](../../backend/CLAUDE.md) "Database Schema" section: document `entity_references` table + `pending_manifest` column + new status.
3. Run `gaspol-verify` to confirm all phases actually shipped (evidence-based audit).
4. Commit docs: `docs: update CLAUDE.md for named-entity cover generation`
5. User reviews: `git push origin main` triggers VPS auto-deploy via GitHub Actions (`.github/workflows/deploy.yml`). Deploy runs `CreatorBrandSettingsSeeder` + `TelegramSettingsSeeder` idempotently. Seed new compiled `refs-images.md` to VPS `/home/claudesn/` via SCP (separate manual step — not in CI).

**Verification:**
- [ ] Both CLAUDE.md files updated with new pipeline details
- [ ] Deploy completes successfully (watch GitHub Actions tab)
- [ ] Health check `/api/health` returns 200 post-deploy
- [ ] Manual smoke test in production: create content idea about a known public figure, trigger pipeline end-to-end

---

## Total Estimate

~2h 45min focused implementation time, split across 10 phases. Realistic calendar estimate: **1-2 working days** including review cycles and the plugin smoke test iterations.

## Phase Parallelism Opportunities

Phases that can run in parallel via `gaspol-parallel` (no cross-dependencies):
- Phase **E** (Telegram service/job/settings) ∥ Phase **C** (CoverBranding gate) — both depend on Phase A + B only
- Phase **I** (Telegram AboutSettings UI) ∥ Phase **H** (Gate 2 entity UI) — both frontend, no shared state
- Phase **G** (Plugin SKILL.md edits) ∥ Phase **C** + **D** — plugin changes don't depend on backend code until E2E test

Sequential phases (must follow order):
- A → B (model needs migrations)
- B → C/D (enhancer + image service both need EntityReference)
- E → F (manifest endpoint depends on Telegram job existing)
- All previous → J (E2E needs the whole stack)
