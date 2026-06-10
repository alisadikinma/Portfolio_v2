# Telegram → Instagram Repurpose → Style-Branded Carousel

> Design doc (gaspol-brainstorm). `## Implementation Plan` akan di-append oleh gaspol-plan.

## Design

### Problem & Goal

Operator kirim **URL Instagram post orang lain** via Telegram. Sistem otomatis:
1. Ambil slide images + caption dari post itu (Playwright).
2. Vision-read tiap slide + caption → ekstrak teks/klaim + struktur naratif.
3. **Deep research / fact-check** klaim → koreksi yang salah + perkuat dengan data & sumber terverifikasi.
4. Rewrite jadi versi **lebih powerful & akurat** dalam style/personal-branding Ali.
5. Render carousel versi style Ali via `/carousel-gen` (bilingual + brand chrome existing).
6. Mendarat sebagai **draft carousel di `/admin/draft-posts`** (`linkedin_posts`) → reuse render + approve + slot + cross-post fan-out. Telegram balas notif "draft siap".

"Lebih powerful dari aslinya" = lebih benar (koreksi klaim) + ada bukti (sumber) + style/visual Ali, bukan sekadar parafrase.

### Locked Decisions (dari brainstorm)

| # | Keputusan | Pilihan |
|---|---|---|
| D1 | Output tujuan | **Tergantung mode (D9)**: `blog` → ContentIdea (`article_ready`) masuk pipeline existing → publish blog → carousel + cross-post otomatis (event-driven ingest). `carousel` → langsung `linkedin_posts` carousel draft (anchor Post, D8). |
| D2 | Ambil konten IG | **Playwright script deterministik (exec)**, narik embedded JSON + slides. Playwright MCP cuma utk dev/authoring script; agent-browser **ditolak** (agent-driven → mahal/non-deterministik/leak MCP, gak cocok pipeline otomatis) |
| D3 | Ekstrak teks | Vision-read slide images **+** caption |
| D4 | Klaim salah | Koreksi + perkuat dgn data benar; research notes + sumber dilampirkan ke draft |
| D5 | Orkestrasi | Tabel `repurpose_jobs` baru + queued orchestrator (FSM, retry per-step, audit) |
| D6 | Storage & retensi gambar | Privat `storage/app/repurpose/{job_id}/`. Purge folder saat job **sukses (drafted)**; job **failed** disimpan utk retry/debug; reaper terjadwal hapus folder **>7 hari** apapun statusnya |
| D7 | Bot Telegram | **Reuse bot existing** (token + webhook + secret sudah ada). Cuma tambah cabang `message.text` (URL IG) di `TelegramWebhookController` yg sekarang baru handle `callback_query` |
| D8 | Anchor draft (post_id NOT NULL) | **Hanya utk mode `carousel`-only**: bikin blog `Post` draft (unpublished) sbg anchor `linkedin_posts`. Mode `blog` gak perlu — ContentIdea→Post jalur normal. |
| D9 | Pilih mode via Telegram | Saat kirim URL, bot balas **2 inline button** (reuse `signCallback`/`verifyCallback` HMAC infra existing): "📝 Blog + Carousel" vs "🎠 Carousel saja". `kind='repurpose'`, `action=blog\|carousel`, `id=repurpose_job_id`. Tap → set `repurpose_jobs.mode` → mulai capture. Tanpa tap → job nunggu di `received` (di-reap). |
| D10 | Sumber carousel (mode blog) | Carousel diauthor **dari teks blog article** (versi terkoreksi) via force-carousel path existing — **zero kode hilir baru**. Gambar IG = input research saja, **gak pernah** dipakai visual. Kalau mau carousel image/struktur-driven dari IG, pakai mode `carousel`-only. |

### Architecture

```
Telegram bot (inbound webhook — BARU)
  POST /api/automation/telegram/webhook
   │ validate X-Telegram-Bot-Api-Secret-Token + allowed chat_id
   │ parse: URL IG (+ opsional 1-baris "angle"/instruksi)
   ▼
RepurposeJob (received) ── RepurposeOrchestrator (PipelineGuard tick) ──┐
   │                                                                    │
   ▼ queued jobs, satu per step, masing2 advance FSM:                   │
  [1] CaptureInstagramPost      received → capturing → captured         │
       └ InstagramCaptureService (Playwright headless via exec)         │
          → storage/app/repurpose/{job}/slide-NN.jpg + caption.txt      │
  [2] ExtractSlideContent       captured → extracting → extracted       │
       └ SlideVisionExtractor (Claude CLI vision / indusia, image in)   │
          → text klaim + struktur naratif per slide + caption merge     │
  [3] ResearchRepurposeClaims   extracted → researching → researched    │
       └ RepurposeResearchService (firecrawl + Claude CLI deep research)│
          → klaim terkoreksi + data + sumber[] (disimpan ke job)        │
  [4] RewriteRepurposeContent   researched → rewriting → rewritten      │
       └ RepurposeRewriteService (Claude CLI, style Ali + angle user)   │
          → "blog body" repurposed (inline source utk carousel-gen)     │
  [5] GenerateRepurposeCarousel rewritten → generating_carousel →       │
       └ seed linkedin_posts draft + applyCarouselGenAdapter            │
          (/carousel-gen, inline content) → carousel_slides[]           │
          → carousel_ready → drafting → drafted                         │
  [6] (reuse) GenerateLinkedInCarouselImages → render slides            │
       (poller completion — blog:process-images, BUKAN webhook)         │
       → CarouselSlideEnhancer brand chrome → cross-post fan-out        │
       ▼
  TelegramNotificationService.sendMessage(chat_id):
     "✅ Draft repurpose siap: {N} slides. Review → /admin/draft-posts/{id}
      ⚠ Research: {k} klaim dikoreksi. Sumber dilampirkan."
```

Step **failed** dari step manapun → status `failed` + `last_error`, Telegram balas alasan + tombol/perintah retry. Retry per-step (idempotent), bukan ulang dari nol.

### New vs Reuse

**BARU:**
- `TelegramWebhookController` (inbound) — Telegram saat ini **outbound-only** (`TelegramNotificationService`). Ini titik integrasi baru. Public route, auth via secret token header + chat_id allowlist.
- `repurpose_jobs` migration + `RepurposeJob` model + `RepurposeJobStatus` enum (pakai `HasStatusTransitions` + `statusEnumClass()` generik yg sudah ada).
- `InstagramCaptureService` (Playwright headless via exec pattern, mirip `ArticleGenerationService` SSH).
- `SlideVisionExtractor` (vision read images + caption).
- `RepurposeResearchService` (fact-check, firecrawl + Claude CLI).
- `RepurposeRewriteService` (rewrite powerful version, style Ali).
- `RepurposeOrchestrator` + 5 queued jobs (Capture/Extract/Research/Rewrite/GenerateCarousel).
- `TelegramBotService` (atau perluas `TelegramNotificationService`) untuk `sendMessage` reply + `setWebhook` helper.
- Settings (`telegram` group): `telegram_repurpose_enabled`, `telegram_webhook_secret`. (Reuse `telegram_chat_id` sbg allowlist.)
- `repurpose:reap` scheduled command — purge `storage/app/repurpose/{job}/` folder >7 hari apapun statusnya (mirror pola `linkedin:reap-stuck-carousel-images`). Purge per-sukses dilakukan inline saat job → `drafted`.

**REUSE (tanpa modif besar):**
- `LinkedInGenerationService::applyCarouselGenAdapter` + `CarouselGenOutputAdapter` — jalankan `/carousel-gen` & assemble slides ke `linkedin_posts.carousel_slides` (feed rewritten body inline, pola `buildCarouselGenPrompt` force-carousel).
- `GenerateLinkedInCarouselImages` + `LinkedInCarouselImageService` — render slide PNG (completion via poller).
- `CarouselSlideEnhancer` — brand chrome (face refs, watermark, bilingual ID+EN).
- Cross-post fan-out (`ScanLinkedInForCrossPost` / `maybeDispatchCrossPostFanout`) — IG/TikTok/Threads sibling otomatis.
- `PipelineGuard` + `HasStatusTransitions` (FSM generik).
- SSH/exec pattern: `config/carousel-gen.php`, `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` (anti MCP-leak), `CAROUSEL_GEN_*` env.
- `ShortLinkService` — opsional, atribusi/sumber jadi short link.

### Data Integration Map

| Komponen | Data Source | Existing? | Catatan |
|---|---|---|---|
| Inbound Telegram | `POST /api/automation/telegram/webhook` | ❌ baru | Auth: secret token header + `telegram_chat_id` allowlist |
| Slide images + caption | Playwright headless (exec) | ❌ baru | Simpan `storage/app/repurpose/{job}/` |
| Vision extract | Claude CLI (image input) / indusia | ⚠️ pola ada | Reuse exec + empty-mcp |
| Fact-check research | firecrawl MCP + Claude CLI deep research | ✅ tools ada | Sumber[] disimpan ke `repurpose_jobs` |
| Rewrite style Ali | Claude CLI + refs style | ✅ pola ada | Reuse `--append-system-prompt-file` refs |
| Carousel assemble | `/carousel-gen` + `CarouselGenOutputAdapter` | ✅ | Feed rewritten body inline |
| Slide render | `GenerateLinkedInCarouselImages` + poller | ✅ | `blog:process-images` poller = jalur selesai |
| Brand chrome | `CarouselSlideEnhancer` | ✅ | Bilingual + watermark + face |
| Draft + publish | `linkedin_posts` + cross-post fan-out | ✅ | `/admin/draft-posts`, slot scheduler |
| Notif balik | `TelegramNotificationService` | ✅ | + `sendMessage` reply baru |

### Feasibility Flags (Phase 4)

1. **Playwright headless Chromium di VPS belum tentu ada** → operator step sekali: install chromium + system deps. Jalankan via user `claudesn` (konsisten dgn SSH key path queue worker). Risk: memory (chromium berat) — pakai `--single-process`/`--no-sandbox` headless, kill after capture.
2. **IG login wall** → public post umumnya kebuka headless tapi gak 100%. Fallback: kalau capture 0 image, job → `failed` dgn alasan jelas; Telegram minta paste caption / skip. JANGAN silent-fail.
3. **Render completion = poller, bukan webhook** (per memory `geminigen-webhook-never-fires`) — step 6 ikut `blog:process-images`; jangan andelin callback.
4. **Copyright/atribusi** — repurpose konten orang. Style & teks ditulis ulang (transformatif) + difakta-cek, tapi pertimbangkan opsional credit/atribusi sumber di slide CTA atau caption. (Default: rewrite transformatif, tanpa repost visual asli.)
5. **MCP leak** — semua Claude CLI call WAJIB `--mcp-config empty-mcp.json --strict-mcp-config`.

### Anti-Scope (YAGNI)

- ❌ Bukan publish otomatis langsung — selalu mampir draft/manual review dulu (konten repurpose + fact-check butuh mata operator).
- ❌ Bukan IG scraper API berbayar (Playwright + fallback paste sudah cukup).
- ❌ Bukan multi-user bot — allowlist `telegram_chat_id` (single operator).
- ❌ Bukan repost gambar asli — selalu render ulang versi style Ali.

### Open items utk Phase Plan

- Bentuk persis `repurpose_jobs` (kolom: source_url, angle, status, slides_path, extracted_json, research_json, rewritten_json, linkedin_post_id, last_error, pipeline_state_log, timestamps).
- Cara feed rewritten content ke `/carousel-gen` (seed `linkedin_posts` draft minimal vs jalur generik adapter).
- Vision extractor: Claude CLI image input vs indusia — pilih saat plan (cek mana yg paling reliable & murah).
- Telegram command format (plain URL vs `/repurpose <url> [angle]`).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.
> **Dev-env note:** No PHP/composer on the dev Mac (per CLAUDE.md). TDD steps are authored full-fidelity and run on CI (`php artisan test`, phpunit sqlite `:memory:`). Where a step says "run test see fail/pass", author the test + `php -l` syntax-check locally; CI is the authoritative green. Frontend `.mjs` smoke tests run locally via `node --test`.

### Goal

Operator kirim URL Instagram post orang lain via Telegram → sistem capture slides (Playwright) → vision-extract teks+klaim → deep-research fact-check (koreksi + perkuat dgn sumber) → rewrite jadi versi powerful versi style Ali → render carousel via `/carousel-gen` → mendarat sebagai carousel draft di `/admin/draft-posts` (anchored ke blog Post draft) + cross-post fan-out, dengan notif balik ke Telegram. FSM `repurpose_jobs` baru, retry per-step, reaper retensi 7 hari.

### Architecture Context (dari CLAUDE.md + pembacaan kode)

- **Inbound Telegram SUDAH ADA**: [`TelegramWebhookController`](backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php) (`POST /automation/telegram/webhook`) memvalidasi `X-Telegram-Bot-Api-Secret-Token` lalu hanya menangani `callback_query`; **pesan teks di-ack & diabaikan** (line ~60). [`SetTelegramWebhook`](backend/app/Console/Commands/SetTelegramWebhook.php) (`telegram:set-webhook`) + `telegram_webhook_secret` (seeded di [`TelegramSettingsSeeder`](backend/database/seeders/TelegramSettingsSeeder.php)) sudah jalan. **Reuse bot yang sama (D7).**
- **FSM**: [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) (`abstract statusEnumClass(): string`, `transitionTo(BackedEnum|string, ?reason, extra): self`) + enum pattern [`ContentIdeaStatus`](backend/app/Enums/ContentIdeaStatus.php) (`case` + `const TRANSITIONS` adjacency + `canTransitionTo`). [`PipelineGuard`](backend/app/Services/PipelineGuard.php) untuk advance berseragam-log.
- **Claude CLI exec (SSH/local)**: [`ArticleGenerationService`](backend/app/Services/ArticleGenerationService.php) — `runSonnetSync(prompt, phase, model)` public, `executeSSH/executeLocal`, `buildMcpFlags()` sudah inject `--mcp-config <empty-mcp> --strict-mcp-config` (anti MCP-leak). Mirror pola ini untuk vision/research/rewrite.
- **Carousel render**: [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php) — `applyCarouselGenAdapter(parsed, blogUrl, draftId, ?blogContent)` jalankan `/carousel-gen` + [`CarouselGenOutputAdapter::adapt`](backend/app/Services/CarouselGenOutputAdapter.php) → `linkedin_posts.carousel_slides`; `buildForcedCarouselEnvelope(draft)` + `buildCarouselGenPrompt(brief, blogUrl, blogContent)` (embed body inline). [`GenerateLinkedInCarouselImages::dispatch($draftId)`](backend/app/Jobs/GenerateLinkedInCarouselImages.php) render slides. **Completion via `blog:process-images` poller, BUKAN webhook** (memory: geminigen-webhook-never-fires).
- **Cross-post fan-out**: otomatis via [`LinkedInCarouselImageService::maybeDispatchCrossPostFanout($linkedinPostId)`](backend/app/Services/LinkedInCarouselImageService.php) saat semua slide `done`; atau targeted `Artisan::queue('social-cross-post:scan', ['--draft-id' => $id])`.
- **Anchor (D8)**: `linkedin_posts.post_id` NOT NULL → buat blog `Post` draft (`published=false`) + `PostTranslation` primary row. [`Post`](backend/app/Models/Post.php) fillable: `category_id, slug, featured_image, published`; HasSlug `doNotGenerateSlugsOnUpdate` (slug pre-set). [`PostTranslation`](backend/app/Models/PostTranslation.php) fillable: `post_id, language, title, excerpt, content, meta_*`.
- **Telegram notif**: [`TelegramNotificationService`](backend/app/Services/TelegramNotificationService.php) — banyak `send*` (POST bot `sendMessage`). Tambah method repurpose + 1 `sendDirectMessage`-style helper.
- **Reaper pola**: [`ReapStuckLinkedInCarouselImages`](backend/app/Console/Commands/ReapStuckLinkedInCarouselImages.php) + row di [`ScheduledCommandSeeder`](backend/database/seeders/ScheduledCommandSeeder.php) (dynamic scheduler).

### Tech Stack

Laravel 12 + PHP 8.2 + MySQL 8 (queue=database, systemd `portfolio-queue.service`). Playwright (Node, headless Chromium) sebagai exec script di VPS. Claude CLI (auth operator, no API key) untuk vision/research/rewrite + `/carousel-gen`. firecrawl untuk research sumber. Tests: PHPUnit (sqlite `:memory:` di CI).

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Webhook secret + inbound plumbing | `telegram_webhook_secret` + `TelegramWebhookController` | `POST /automation/telegram/webhook` | **Yes** | Reuse; tambah `message` branch |
| Allowlist pengirim | `Setting{group=telegram,key=telegram_chat_id}` | `Setting::where(...)` | **Yes** | Reuse sbg allowlist |
| Mode pilih (blog/carousel) | inline buttons HMAC | `signCallback`/`verifyCallback` + `kind='repurpose'` | **Yes** | Reuse; tambah `resolveRepurposeAction` |
| Blog-mode output | `content_ideas` (article_ready) | `ContentIdea::create` + existing pipeline | **Yes** | Use directly (carousel auto via June-10 ingest) |
| Feature toggle | `telegram_repurpose_enabled` (baru) | `Setting` | No | Seed di `TelegramSettingsSeeder` |
| Job state/FSM | `repurpose_jobs` (baru) | `RepurposeJob` + `HasStatusTransitions` | No | Create migration/model/enum |
| IG capture | Playwright headless (exec) | `InstagramCaptureService` | No | Create — script Node + service |
| Vision extract | Claude CLI image input (exec) | `SlideVisionExtractor` (mirror `runSonnetSync`) | No | Create — **verify CLI image-input reliability (feasibility)** |
| Fact-check research | firecrawl + Claude CLI | `RepurposeResearchService` | ⚠️ tools ada | Create service |
| Rewrite style Ali | Claude CLI + style refs | `RepurposeRewriteService` | ⚠️ pola ada | Create service |
| Anchor blog Post | `posts` + `post_translations` | `Post::create` + `PostTranslation::create` | **Yes** | Use directly (draft, `published=false`) |
| Carousel assemble | `/carousel-gen` + adapter | `LinkedInGenerationService::applyCarouselGenAdapter` | **Yes** | Use directly (feed rewritten body inline) |
| Slide render | GeminiGen + poller | `GenerateLinkedInCarouselImages::dispatch` + `blog:process-images` | **Yes** | Use directly |
| Cross-post fan-out | sibling generators | `social-cross-post:scan --draft-id` / auto | **Yes** | Auto-fires; no new code |
| Telegram reply | bot `sendMessage` | `TelegramNotificationService` (new methods) | **Yes** | Add `sendRepurpose*` methods |
| Image retention purge | filesystem | `repurpose:reap` cmd + scheduler row | No | Create cmd + seeder row |

### Phases

#### Phase 0: Schema, Enum, Model, Settings

**Estimated time:** ~12 min · **Files:** Create migration `2026_06_1X_..._create_repurpose_jobs_table.php`, `app/Enums/RepurposeJobStatus.php`, `app/Models/RepurposeJob.php`, `database/factories/RepurposeJobFactory.php`; Modify `database/seeders/TelegramSettingsSeeder.php`; Test `tests/Unit/RepurposeJobStatusTransitionsTest.php`.

`repurpose_jobs` cols: `id, source_url, angle (nullable text), mode (string null — 'blog'|'carousel', set by Telegram button D9), status (string, idx), slides_path (nullable), extracted (json null), research (json null), rewritten (json null), content_idea_id (nullable FK content_ideas nullOnDelete — blog mode), linkedin_post_id (nullable FK linkedin_posts nullOnDelete — carousel mode), anchor_post_id (nullable FK posts nullOnDelete — carousel mode), last_error (nullable text), pipeline_state_log (json null), chat_id (string null), timestamps`.

`RepurposeJobStatus` cases: `received, capturing, captured, extracting, extracted, researching, researched, rewriting, rewritten, finalizing, drafted, failed` (`received` = awaiting mode-button tap) + `const TRANSITIONS` (linear forward; setiap non-terminal `→ failed`; `failed → capturing|extracting|researching|rewriting|finalizing` utk retry-per-step) + `canTransitionTo()`. `RepurposeJob` pakai `HasStatusTransitions` + `statusEnumClass(): RepurposeJobStatus::class`, casts json, `$fillable`.

**Steps:**
1. Write failing test for `RepurposeJobStatus::canTransitionTo` (received→capturing true, received→drafted false). Expected error: `Error: Class "App\Enums\RepurposeJobStatus" not found`.
2. Run test, confirm fail for that reason (CI / `php -l`).
3. Implement enum + TRANSITIONS + canTransitionTo.
4. Implement migration + model (HasStatusTransitions, statusEnumClass, fillable, casts) + factory.
5. Add `telegram_repurpose_enabled` (`'false'`) row to `TelegramSettingsSeeder` (idempotent `firstOrCreate`).
6. Run tests, confirm pass. Commit: `feat(repurpose): repurpose_jobs schema + FSM enum + settings`.

**Verification:**
- [ ] `php -l` clean on all new files; migration runs on sqlite.
- [ ] Transition test passes (legal allowed, illegal throws `InvalidStateTransitionException`).
- [ ] `telegram_repurpose_enabled` seeded idempotently.
- [ ] No TODO/placeholder in new code.

#### Phase A: Inbound message → mode-prompt buttons → capture (D9)

**Estimated time:** ~14 min · **Files:** Modify `TelegramWebhookController.php` (add message branch + `resolveRepurposeAction`), `TelegramNotificationService.php` (add `sendRepurposeModePrompt(RepurposeJob)`); Create `app/Jobs/CaptureInstagramPost.php` (stub dispatch target — real impl Phase B); Test `tests/Feature/TelegramRepurposeMessageTest.php`, `tests/Feature/TelegramRepurposeCallbackTest.php`.

**A1 — message → buttons:** Add `handleMessage(array $update)` branch BEFORE the current callback-ignore return: if `message.text` present AND `telegram_repurpose_enabled` true AND `message.chat.id` == `telegram_chat_id` AND text contains a valid `instagram.com/(p|reel|tv)/...` URL → create `RepurposeJob{source_url, angle (text after URL, optional), chat_id, mode=null, status=received}`, then `sendRepurposeModePrompt($job)` (2 inline buttons via `signCallback`: 📝 `signCallback('blog','repurpose',$job->id,$secret)` · 🎠 `signCallback('carousel','repurpose',$job->id,$secret)`). NO capture dispatch yet. Non-IG / wrong chat → ack `{ok:true}` no-op. callback_query path untouched.

**A2 — button → mode → capture:** Extend `dispatchAction` with `kind==='repurpose'` → new `resolveRepurposeAction(RepurposeJob $job, string $action)`: validate `action ∈ {blog,carousel}`, idempotent (if `mode` already set → "Already started."), set `mode=action`, `transitionTo(capturing)`, `CaptureInstagramPost::dispatch($job->id)`, return toast "⏳ {mode} repurpose started".

**Steps:**
1. Write failing test (message): POST webhook (valid secret) with `message.text="https://instagram.com/p/ABC123/ fokus bisnis"` from allowed chat → asserts `RepurposeJob` row created (status=received, mode=null) + Http::fake sees a `sendMessage` with `reply_markup` carrying 2 signed callback_data + NO `CaptureInstagramPost` queued. Expected error: assertion fail (no row).
2. Run, confirm fail.
3. Implement `handleMessage` + IG-URL extractor (host allowlist `instagram.com`/`www.instagram.com`) + `sendRepurposeModePrompt`.
4. Write failing test (callback): POST webhook with `callback_query.data=signCallback('blog','repurpose',$id,$secret)` → job `mode='blog'`, status=`capturing`, `CaptureInstagramPost` queued (Bus::fake). Then implement `resolveRepurposeAction`.
5. Add tests: wrong chat → no row; toggle off → no row; non-IG → no row; invalid secret → 403 regression; tampered callback hmac → verify-fail no-op; double-tap → second is no-op.
6. Run, confirm pass. Commit: `feat(repurpose): telegram URL → mode buttons → capture dispatch`.

**Verification:**
- [ ] Valid IG URL → job(received) + 2 signed buttons sent; capture NOT dispatched until a button is tapped.
- [ ] Button tap sets `mode` + advances to capturing + dispatches capture; double-tap idempotent.
- [ ] **Security:** secret header enforced (403); chat_id allowlist enforced; URL host restricted to instagram.com (no SSRF); HMAC callback verified (tamper → no-op); toggle-gated.
- [ ] callback_query LinkedIn/idea flows unchanged (existing tests green).
- [ ] No placeholder beyond the Phase-B job stub.

#### Phase B: Instagram capture (Playwright)

**Estimated time:** ~15 min · **Files:** Create `app/Services/InstagramCaptureService.php`, `scripts/playwright/ig-capture.cjs` (Node), `app/Jobs/CaptureInstagramPost.php` (real); Modify `config/services.php` (add `instagram_capture` block: node_path, script_path, storage_state_path, timeout); Test `tests/Feature/CaptureInstagramPostJobTest.php`.

`ig-capture.cjs`: arg `--url --out <dir> [--storage-state <path>]`; headless chromium (`--no-sandbox --single-process`); navigate; prefer embedded JSON / `og:image` + carousel media; download semua slide ke `out/slide-NN.jpg`; tulis `caption.txt`; emit JSON `{slides:[...], caption, count}` ke stdout; exit non-zero kalau 0 slide. `InstagramCaptureService::capture($job)` exec script (mirror `executeSSH/executeLocal` toggle), parse stdout, simpan ke `storage/app/repurpose/{job}/`, set `slides_path`. `CaptureInstagramPost` job: pada sukses `transitionTo(captured)` + `ExtractSlideContent::dispatch`; pada 0-slide/login-wall `transitionTo(failed, 'capture_failed: ...')` + telegram reply minta paste/cek.

**Steps:**
1. Write failing test: `CaptureInstagramPost` with service mocked to return 3 slides → job → `captured` + `ExtractSlideContent` queued; mocked 0 slides → `failed` + telegram fail reply. Expected error: class not found.
2. Run, confirm fail.
3. Implement service (exec + parse + persist) + job + the `.cjs` script + config block.
4. Run tests (service mocked — no real browser in CI), confirm pass. Commit: `feat(repurpose): playwright IG capture service + job`.

**Verification:**
- [ ] Mocked-3-slides → `captured` + next dispatched; mocked-0 → `failed` + telegram fail reply (no silent fail).
- [ ] Files land in private `storage/app/repurpose/{job}/` (not public).
- [ ] **Security:** URL host re-validated server-side before exec; script args shell-escaped; no eval of remote content.
- [ ] Operator step documented (Phase I): `npx playwright install chromium` + deps on VPS as `claudesn`.

#### Phase C: Vision extract (slides + caption → claims)

**Estimated time:** ~12 min · **Files:** Create `app/Services/SlideVisionExtractor.php`, `app/Jobs/ExtractSlideContent.php`; Test `tests/Feature/ExtractSlideContentJobTest.php`.

`SlideVisionExtractor::extract($job)`: Claude CLI dengan input gambar (path slide-NN.jpg) + caption → JSON `{slides:[{n, text, role}], caption, claims:[...], narrative}`. Mirror `ArticleGenerationService` exec + `buildMcpFlags()`. **Feasibility-verify**: kalau CLI image-input gak reliable, fallback Anthropic API image blocks (flag di Phase I). Job: sukses → simpan `extracted` json + `transitionTo(extracted)` + `ResearchRepurposeClaims::dispatch`; gagal → `failed`.

**Steps:**
1. Write failing test: extractor mocked → job persists `extracted` + `extracted` status + next dispatched. Expected error: class not found.
2. Run, confirm fail. 3. Implement service + job. 4. Run, pass. Commit: `feat(repurpose): vision extract slides+caption → claims`.

**Verification:**
- [ ] `extracted` JSON persisted; FSM advanced; next job dispatched.
- [ ] All Claude CLI calls carry `--mcp-config empty-mcp.json --strict-mcp-config`.
- [ ] Extractor failure → `failed` (no half-state).

#### Phase D: Deep research / fact-check

**Estimated time:** ~12 min · **Files:** Create `app/Services/RepurposeResearchService.php`, `app/Jobs/ResearchRepurposeClaims.php`; Test `tests/Feature/ResearchRepurposeClaimsJobTest.php`.

`RepurposeResearchService::research($job)`: per claim → web research (firecrawl/Claude CLI) → verdict `{claim, status: correct|wrong|outdated, corrected, sources[]}`. Output `research` json (corrected claims + sources[]). Per D4: koreksi + perkuat, sumber dilampirkan (dipakai Phase E + di-attach ke draft). Job: sukses → `researched` + `RewriteRepurposeContent::dispatch`; gagal → `failed`.

**Steps:** 1. Failing test: service mocked → `research` persisted + `researched` + next dispatched. 2. Fail. 3. Implement. 4. Pass. Commit: `feat(repurpose): fact-check research service + job`.

**Verification:**
- [ ] `research` JSON (verdicts + sources) persisted; FSM advanced.
- [ ] **Non-deterministic phase:** define eval `docs/evals/repurpose-research.md` (capability: catches a known-false claim in fixture; regression: stable verdict shape) via gaspol-eval.
- [ ] No claim silently dropped.

#### Phase E: Rewrite (powerful, style Ali)

**Estimated time:** ~12 min · **Files:** Create `app/Services/RepurposeRewriteService.php`, `app/Jobs/RewriteRepurposeContent.php`; Test `tests/Feature/RewriteRepurposeContentJobTest.php`.

`RepurposeRewriteService::rewrite($job)`: Claude CLI + style refs (reuse `ARTICLE_GEN_REFS_*` style guide) + `angle` user → article body powerful + akurat (klaim terkoreksi dari `research`) → `rewritten` json `{title, body, excerpt, meta_keywords, sources_appendix}`. Job: sukses → `rewritten` + `FinalizeRepurpose::dispatch`; gagal → `failed`.

**Steps:** 1. Failing test: mocked → `rewritten` persisted + `rewritten` status + next dispatched. 2. Fail. 3. Implement. 4. Pass. Commit: `feat(repurpose): rewrite to powerful style-Ali article + job`.

**Verification:**
- [ ] `rewritten` JSON persisted; FSM advanced.
- [ ] Eval entry: corrected claim from Phase D present in output, original false claim absent.
- [ ] CLI carries empty-mcp flags + style refs.

#### Phase F: Finalize — branch on `mode` (D1/D8/D9)

**Estimated time:** ~16 min · **Files:** Create `app/Jobs/FinalizeRepurpose.php`; Test `tests/Feature/FinalizeRepurposeBlogModeTest.php`, `tests/Feature/FinalizeRepurposeCarouselModeTest.php`.

`FinalizeRepurpose` reads `$job->mode` and branches:

**F-blog (`mode='blog'`)** — enter existing Content Engine pipeline:
1. Create `ContentIdea{status='article_ready', pillar (from research/angle, default 'ai_generalist'), source_data={source:'ig_repurpose', url:$job->source_url}, generated_article={title, body, excerpt, meta_keywords, sources_appendix} (from rewritten)}`; set `content_idea_id`.
2. `transitionTo(drafted)` + purge folder + telegram notif ("📝 Artikel siap di Content Engine · {k} klaim dikoreksi · approve utk images→publish→carousel otomatis: /admin/content-engine").
   → Operator drives Gate 2 (images) + publish via existing UI; publish auto-fires event-driven `linkedin:scan-blog` → force-carousel → carousel + cross-post (NO new code — reuse June-10 ingest).

**F-carousel (`mode='carousel'`)** — direct LinkedIn carousel (anchor Post, D8):
1. Create blog `Post` draft (`published=false`, slug pre-set from rewritten title, default `category_id`) + primary `PostTranslation` (title/content/excerpt/meta_keywords + sources appendix); set `anchor_post_id`.
2. Create `LinkedInPost{post_id=anchor, format='carousel', status=PendingGeneration}`; set `linkedin_post_id`.
3. `buildForcedCarouselEnvelope($draft)` + `applyCarouselGenAdapter($parsed, blogUrl, $draft->id, $rewrittenBody)` → assemble `carousel_slides`; FSM LinkedInPost → Generating→Validating→ManualReview.
4. `GenerateLinkedInCarouselImages::dispatch($draft->id)` (render via poller; cross-post fan-out auto when slides done).
5. `transitionTo(drafted)` + purge folder + telegram notif ("🎠 Carousel draft siap: {N} slides · {k} klaim dikoreksi · /admin/draft-posts/{id}").

Either branch: any exception → `transitionTo(failed)` + folder retained for retry. Success-purge (D6) only on `drafted`.

**Steps:**
1. Write failing test (blog mode): research/rewrite seeded, `mode='blog'` → asserts `ContentIdea` created (status=article_ready, generated_article populated, source_data.source='ig_repurpose'), `content_idea_id` set, RepurposeJob `drafted`, folder purged, telegram notif; NO LinkedInPost/Post created. Expected error: class not found.
2. Run, confirm fail. Implement F-blog branch.
3. Write failing test (carousel mode): `mode='carousel'` + adapter mocked → asserts Post draft (published=false) + PostTranslation + LinkedInPost(carousel) created, `GenerateLinkedInCarouselImages` queued, `drafted`, folder purged, notif; NO ContentIdea created. Implement F-carousel branch (reuse `LinkedInGenerationService` injected).
4. Add test: adapter throws (carousel) → `failed`, folder retained, no half-done LinkedInPost; exception (blog) → `failed`, folder retained.
5. Run, pass. Commit: `feat(repurpose): finalize branch — blog→ContentIdea, carousel→linkedin_posts`.

**Verification:**
- [ ] `mode='blog'` → ContentIdea(article_ready) only; NO Post/LinkedInPost; shows in Content Engine.
- [ ] `mode='carousel'` → anchor Post(`published=false`) + LinkedInPost carousel + render dispatched + fan-out intact; NO ContentIdea.
- [ ] On success: folder purged; on failure: folder retained + `failed` (both branches).
- [ ] Telegram notif per branch includes correct link + corrected-claim count.

#### Phase G: Retention reaper

**Estimated time:** ~8 min · **Files:** Create `app/Console/Commands/ReapRepurposeArtifacts.php` (`repurpose:reap {--days=7} {--dry-run}`); Modify `ScheduledCommandSeeder.php` (row: `repurpose:reap`, cron daily `0 4 * * *`, category `system`, enabled); Test `tests/Feature/ReapRepurposeArtifactsTest.php`.

Purge `storage/app/repurpose/{job}/` dirs older than N days regardless of status (catches failed/abandoned). Idempotent.

**Steps:** 1. Failing test: old dir purged, fresh dir kept, `--dry-run` purges nothing. 2. Fail. 3. Implement cmd + seeder row. 4. Pass. Commit: `feat(repurpose): retention reaper + scheduler row`.

**Verification:**
- [ ] Dirs >N days purged; fresh kept; `--dry-run` no-op; idempotent.
- [ ] Seeder row idempotent (`firstOrCreate`); appears in `/admin/settings?tab=scheduler`.

#### Phase H: Telegram repurpose messages

**Estimated time:** ~8 min · **Files:** Modify `TelegramNotificationService.php`; Test `tests/Unit/TelegramRepurposeMessagesTest.php`.

Add `sendRepurposeStarted(RepurposeJob)`, `sendRepurposeFailed(RepurposeJob, string)`, `sendRepurposeDrafted(RepurposeJob, int $linkedinDraftId, int $correctedClaims)` — build text + POST `sendMessage` to `telegram_chat_id` (reuse existing private send helper). Used by Phases A/B/F.

**Steps:** 1. Failing test (Http::fake) asserting sendMessage payload shape per method. 2. Fail. 3. Implement. 4. Pass. Commit: `feat(repurpose): telegram status messages`.

**Verification:**
- [ ] Each method POSTs to bot sendMessage with chat_id from settings; gated by `telegram_enabled`.
- [ ] Drafted message contains `/admin/draft-posts/{id}` link.

#### Phase I: Docs + operator runbook

**Estimated time:** ~8 min · **Files:** Modify root `CLAUDE.md` (new section + Last Updated); Create `docs/runbooks/repurpose-ig-carousel-deploy.md`.

Runbook: VPS `npx playwright install chromium` + system deps as `claudesn`; confirm `telegram:set-webhook` already covers inbound; flip `telegram_repurpose_enabled='true'`; verify `repurpose:reap` in scheduler; feasibility note on vision CLI image-input (+ Anthropic-API fallback if unreliable); IG login-wall note (optional authenticated `storage_state` cookie path); reaper + retention behavior.

**Steps:** 1. Write CLAUDE.md section (architecture, routes, settings, FSM, files). 2. Write runbook. 3. Commit: `docs(repurpose): CLAUDE.md sync + operator runbook`.

**Verification:**
- [ ] CLAUDE.md documents the feature (FSM, files, settings, reuse points) + Last Updated bumped.
- [ ] Runbook covers Playwright install, toggle, reaper, feasibility flags.

### Phase dependency / parallelism

Sequential chain by data dependency: **0 → A → B → C → D → E → F**. **G, H, I** independent of each other once 0 done (H needed by A/B/F so do H early or stub messages). Suggested parallel groups for `gaspol-parallel`: after Phase 0, run **{H}** alongside **A**; **G** + **I** can run anytime after their targets exist. Core pipeline (B–F) is strictly sequential (each consumes prior step's JSON).

### Feasibility flags (must verify during execution)

1. **Playwright Chromium di VPS** — operator install step; memory budget (headless single-process). If unavailable at runtime → job `failed` with clear reason.
2. **Vision via Claude CLI image-input** — verify reliability in Phase C; fallback Anthropic API image blocks (needs API key) if CLI can't read local images.
3. **IG login wall** — public posts mostly OK headless; else `failed` + telegram "paste/cek". Optional: authenticated `storage_state` cookie (refresh periodically) for reliable full-carousel pull.
4. **Render completion = poller** — never rely on GeminiGen webhook.
5. **MCP leak** — every Claude CLI call MUST pass empty-mcp flags.
6. **Creator-face placement = already guaranteed (no action).** Both modes go through `/carousel-gen` (plugin v2.22.0), which already enforces: creator face MANDATORY on **Hook / Foreshadow / CTA** (+ Loop-end / Thumbnail / B-roll-with-humans) — Hard Rule #3; **Body/Peak content slides without humans = NO creator face** — Hard Rule #4; the literal `creator` token (Hard Rule #19) is the contract `CarouselSlideEnhancer` regex-detects to attach `face_refs` (+ fallback `\bcreator\b` sniff). So source IG posts lacking a face on hook/CTA get the creator's face injected automatically in our version. Known soft-spots (not blocking, plugin-side follow-up if desired): PEAK can be `human_fingerprint`-with-face (not strictly tutorial-only); face-attach depends on the token reaching the backend.
