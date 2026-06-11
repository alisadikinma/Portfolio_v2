# Local Inference Offload for the Content Factory

> Hybrid file. `## Design` written by gaspol-brainstorm (2026-06-10). `## Implementation Plan` will be appended by gaspol-plan.

## Design

### Goal

Cut Claude token spend on the daily autonomous content factory (trending → article → LinkedIn/IG/TikTok) by routing **high-volume text generation to a local model** (`qwen3.6:27b-mtp-q8_0` via Ollama on the M5 48GB Mac), while keeping **Claude Opus 4.8 for the hard task (`/carousel-gen`) and as fallback**. Visual rendering (GeminiGen) is unaffected — it was always a cloud API, never an LLM-on-Claude task.

### What we are NOT building (explicitly cut — YAGNI)

The original instinct was "clone the VPS database to local + sync content back." **This is dropped.** Token cost lives in the **LLM inference**, not in where Laravel runs. By offloading *only inference*, the VPS stays the single source of truth — so there is **no DB clone, no content sync, no two-master conflict surface.** The hardest, most error-prone part of the original idea evaporates.

### Chosen architecture — Arch-1: Offload inference only

Laravel + MySQL + cron + queue worker **stay on the VPS, unchanged**. The Mac becomes an **Ollama inference server** reachable from the VPS over a private **Tailscale** tunnel. The existing pipeline subprocess `claude -p "/skill …" --model {model}` is pointed at the Mac's Ollama via `ANTHROPIC_BASE_URL` **on a per-phase basis** — text phases route to qwen, the carousel phase keeps cloud Claude.

```
VPS (otak — source of truth, unchanged)
  Laravel + MySQL + cron (auto-pipeline 05–22 WIB) + queue worker
     │  per-phase exec wrapper (ArticleGenerationService / LinkedInGenerationService)
     │
     ├── TEXT phase  → ANTHROPIC_BASE_URL=http://mac.tailnet:11434/v1
     │                 ANTHROPIC_AUTH_TOKEN=ollama ; (unset OAuth/API key)
     │                 claude -p "/article-write" --model <local-prose-model>
     │                        │  Tailscale tunnel (private, ACL-scoped)
     │                        ▼
     │                 Mac M5 48GB → Ollama (qwen3 / gemma3-27b / sahabatai)
     │
     └── CAROUSEL phase → no BASE_URL override (keep CLAUDE_CODE_OAUTH_TOKEN)
                          claude -p "/carousel-gen" --model opus   → cloud

GeminiGen (render PNG) → cloud API, called from VPS as today (poller blog:process-images)
Publer / LinkedIn publish / Telegram → cloud, unchanged
```

**"Orchestrator" mapping:** there is no separate orchestrator LLM. **Laravel/cron FSM is the orchestrator** (already exists). "Opus 4.8 orchestrator, local for the rest" translates to: *Laravel orchestrates → routes text to qwen, routes `/carousel-gen` + fallback to Opus.*

### Scope — what moves to local (Phase 1)

| LLM task | Compiled refs | Fits qwen 32K ctx? | Phase-1 placement |
|---|---|---|---|
| Trending pull | small | ✅ | **Local** |
| Article prep / write / score | ~49–59KB (~12–15K tok) | ✅ | **Local** |
| Article image-**prompt** authoring | ~38KB | ✅ | **Local** |
| Translate ID→EN | ~7KB | ✅ | **Local** |
| LinkedIn / IG / TT **caption** | <50KB (verify per-platform) | ✅ | **Local** |
| **`/carousel-gen`** (authors slide JSON) | **169KB ≈ ~42K tok** | ❌ exceeds 32K | **Cloud / Opus** |
| GeminiGen render PNG | — | N/A (not an LLM task) | **Cloud, always** |

`/carousel-gen` is the one LLM task blocked locally: its `refs-carousel-gen-pipeline.md` system prompt alone (~42K tokens) exceeds qwen's 32K window. Localizing it later requires trimming refs to <~28K tokens or raising ctx to 64K+ (pressures 48GB RAM) — **out of scope for Phase 1.**

### Model selection — decided by bake-off, not by leaderboard (Phase 0)

Research verdict: no single benchmark winner for "marketing prose ID+EN, Ali brand-voice" — it is a taste/eval question. Hardware caps the shortlist (70B won't load on 48GB; ceiling ~27–32B @ q4–q8). Shortlist:

- **qwen3.6:27b** (already downloaded) — best instruction/JSON-following; `-coder` variant writes stiff prose (use **instruct** non-coder for prose). Source: [siliconflow 2026](https://www.siliconflow.com/articles/en/best-open-source-LLM-for-Indonesian).
- **Gemma 3 27B instruct** — strong multilingual prose, fits q4 on 48GB, already noted as fallback in hot.md. Confirmed running on identical M5 48GB rig @ 32K ctx ([report](https://www.facebook.com/groups/1577315533418837/posts/1643178380165885/)).
- **SahabatAI** ([sea-lion.ai](https://sea-lion.ai/case-study/sahabat-ai/)) — Bahasa-native continued-pretrain; best ID idiom, but viable variant is only 9B (weaker English long-form / reasoning); 70B variant won't fit.

**Phase 0 bake-off** runs `/article-write` + caption on 3–5 real ideas per candidate, scored via the existing `/article-score` 5-gate + operator brand-voice eyeball. Expected outcome: **qwen → JSON/structure tasks, Gemma3-27B or SahabatAI → prose tasks** (final split decided by the eval, not assumed).

### Feasibility landmines (designed-for, not ignored)

1. **Env injection ≠ zsh alias.** The proven `cc-local` mechanism is an interactive zsh alias; the pipeline runs non-interactive `bash -lc 'source ~/.profile; claude -p …'` (SSH) — zsh aliases never load there. `ANTHROPIC_BASE_URL` + `ANTHROPIC_AUTH_TOKEN` must be injected **explicitly in the exec wrapper, per phase**, and the OAuth/API key must be **unset for text phases** (else the request still hits Anthropic) and **kept for the carousel phase**. Each pipeline phase is already a separate subprocess → per-phase env is clean.
2. **Mac availability.** The daily cron window is 05–22 WIB; the Mac must be awake + on the tailnet then. A **pre-dispatch health-check** (ping Ollama `/api/tags`) gates each text phase: unreachable → drop the BASE_URL override → **fall back to cloud Claude** (Sonnet for cost) and `Log::warning` so the operator sees when local was down. Lightweight version of the existing GeminiGen circuit-breaker pattern.
3. **Tunnel security.** Ollama binds `0.0.0.0` but is exposed **only to the tailnet** via Tailscale ACL — never public. `ANTHROPIC_AUTH_TOKEN=ollama` is a dummy; Tailscale provides the real auth boundary.
4. **OAuth/cloud fallback cost.** Fallback to cloud costs tokens — acceptable as a safety net, but every fallback is logged so the operator knows the local box was the bottleneck.

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Per-phase model + base_url routing | `config('services.article_generation.*')`, `config('linkedin.generation.*')`, `config('carousel-gen.*')` + new `*_BASE_URL` env keys | Partial — `--model` plumbing exists, base_url override is new | Add per-phase `base_url` + prose-vs-json model keys |
| Exec wrappers | `ArticleGenerationService::executeSSH/executeLocal`, `LinkedInGenerationService` exec | ✅ exists | Inject env per phase; keep `--mcp-config empty --strict-mcp-config` |
| Local inference | Mac Ollama `:11434/v1` over Tailscale | ❌ new (Mac side) | qwen + bake-off candidates pulled |
| Health-check / fallback | new guard before text dispatch | ❌ new | Ping Ollama; on fail → cloud Claude + warn |
| Content persistence | VPS MySQL (`content_ideas`, `posts`, `linkedin_posts`) via existing FSM | ✅ unchanged | No clone, no sync — VPS authoritative |
| Image render | GeminiGen + `blog:process-images` poller | ✅ unchanged | Cloud, works regardless of inference location |
| Eval harness (Phase 0) | `/article-score` 5-gate + gaspol-eval | ✅ score exists | Compare candidates on real ideas |

### Phases (for gaspol-plan to expand)

- **F0 — Model bake-off** (decides prose-vs-JSON routing): pull candidates on Mac, run `/article-write` + caption on 3–5 real ideas each, score via `/article-score` + brand-voice review.
- **F1 — Tailscale tunnel**: Mac on tailnet, Ollama bound + ACL-scoped, VPS reaches `mac.tailnet:11434`, verify `claude -p` round-trip with `ANTHROPIC_BASE_URL` override.
- **F2 — Per-phase env injection + health-check fallback**: exec wrappers set/unset env per phase; pre-dispatch Ollama ping → fallback to cloud Claude + `Log::warning`.
- **F3 — Cutover text phases**: flip article + caption phases to local model per F0 result; `/carousel-gen` stays Opus; GeminiGen unchanged.
- **F4 — Eval guardrail + observability**: ongoing quality check on local output (drift/regression), token-savings + fallback-rate logging.

### Open risks / to validate during planning

- LinkedIn/IG/TT caption refs sizes — confirm each fits 32K (assumed, not measured).
- qwen JSON-envelope adherence on `/article-score` (strict 5-gate JSON) vs Sonnet — verify in F0.
- Mac sleep/power management during the cron window (caffeinate / energy settings).
- Token-savings measurement baseline (current daily Claude spend) to prove ROI.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Route the Portfolio_v2 content-factory's high-volume **text** LLM phases to a local Ollama model on the M5 Mac (reached from the VPS over Tailscale) to cut Claude token spend, while keeping `/carousel-gen` on Opus and GeminiGen on cloud. The VPS stays the single source of truth — **no DB clone, no content sync.** The change is a per-phase `ANTHROPIC_BASE_URL` override + health-checked cloud fallback inside the existing exec wrappers; the `--model {model}` plumbing already exists.

### Architecture Context (from CLAUDE.md + verified code)

- **Exec wrappers:** [`ArticleGenerationService`](backend/app/Services/ArticleGenerationService.php) (`executeSSH` L955, `executeLocal` L912, `executeSyncPrompt` L796) and [`LinkedInGenerationService`](backend/app/Services/LinkedInGenerationService.php). The SSH runner script (L1010-1019) is: `source ~/.profile` → `claude -p "$prompt" {modelFlag} {refsFlag} {mcpFlags} --dangerously-skip-permissions`. **No env injection today.**
- **Model source:** `config('services.article_generation.model_{prep,write,score,images,translate}')` ← env `ARTICLE_GEN_MODEL_*` (default `sonnet`), passed as `$model` → `executePrompt($prompt,$ideaId,$phase,$model,$refsFile)`. `executePrompt` is `protected` (existing test seam).
- **MCP leak guard:** `buildMcpFlags()` injects `--mcp-config <empty> --strict-mcp-config` — keep as-is.
- **Driver:** `ARTICLE_GEN_DRIVER=ssh|local`; production = `ssh` to `claudesn@localhost` on the VPS, so `claude` runs on the VPS.
- **Refs sizes (ctx fit):** prep ~59KB / write ~49KB / score ~52KB / images ~38KB / translate ~7KB all fit qwen 32K; `refs-carousel-gen-pipeline.md` ~169KB (~42K tok) does NOT — carousel stays cloud.
- **LinkedIn caption nuance:** under `linkedin_force_carousel='true'` (default) `/linkedin-gen` is SKIPPED and the caption is rebuilt backend-side (`buildCarouselCaption`, no LLM). So LinkedIn-gen routing is low-value; the real cross-post text-LLM volume is IG/TT caption services. **Article pipeline is the primary token sink → Phase 1 target.**
- **Test constraint:** no PHP on dev Mac → all PHP tests authored for CI (`php artisan test`, sqlite `:memory:`) per CLAUDE.md. Logic must be unit-testable WITHOUT running Process/SSH (pure string-building + `Http::fake`).
- **Fallback precedent:** GeminiGen circuit-breaker pattern ([`GeminiGenCircuitBreaker`](backend/app/Services/GeminiGenCircuitBreaker.php)) — reuse the lightweight idea (cache health for the run).

### Tech Stack

Laravel 12, PHP 8.2, `Illuminate\Support\Facades\Http` (health-check), `Illuminate\Process` (exec, unchanged), Tailscale (tunnel), Ollama OpenAI-compat endpoint (`/v1`). No new Composer deps.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Per-phase model name | `config('services.article_generation.model_*')` | config | Yes | Use existing for cloud route |
| Local endpoint | `ARTICLE_GEN_LOCAL_BASE_URL` + `_AUTH_TOKEN` | new config keys | No | Create (empty = feature off) |
| Which phases go local | `ARTICLE_GEN_LOCAL_PHASES` (CSV) | new config key | No | Create |
| Local model per phase | `ARTICLE_GEN_MODEL_LOCAL_*` | new config keys | No | Create (F0 decides values) |
| Route resolution | `resolveRoute($phase)` | new method | No | Create (pure, testable) |
| Health-check | Ollama `GET /v1/models` via `Http::timeout` | new method `localHealthy()` | No | Create + `Http::fake` tests |
| Env injection | runner script in `executeSSH/executeLocal/executeSyncPrompt` | extract `buildRunnerScript()` | Partial | Refactor for testability + inject exports |
| Content persistence | VPS MySQL FSM (`content_ideas`, `posts`) | existing | Yes | Unchanged — no clone/sync |
| Image render | GeminiGen + `blog:process-images` poller | existing | Yes | Unchanged |
| Bake-off eval | `/article-score` 5-gate + brand-voice | existing plugin | Yes | F0 records decision |

### Env / config keys added (all in `config/services.php` → `article_generation`)

```env
ARTICLE_GEN_LOCAL_BASE_URL=            # e.g. http://mac-m5.tailnet.ts.net:11434/v1 ; empty = local routing OFF (all cloud)
ARTICLE_GEN_LOCAL_AUTH_TOKEN=ollama    # dummy token; Tailscale ACL is the real boundary
ARTICLE_GEN_LOCAL_PHASES=              # CSV of phases to route local, e.g. prep,write,score,images,translate ; empty = none
ARTICLE_GEN_MODEL_LOCAL_PREP=          # local model alias per phase (F0 fills these)
ARTICLE_GEN_MODEL_LOCAL_WRITE=
ARTICLE_GEN_MODEL_LOCAL_SCORE=
ARTICLE_GEN_MODEL_LOCAL_IMAGES=
ARTICLE_GEN_MODEL_LOCAL_TRANSLATE=
ARTICLE_GEN_LOCAL_HEALTH_TIMEOUT=3     # seconds for the pre-dispatch Ollama ping
```

**Routing rule (`resolveRoute($phase)`):** route local **iff** `LOCAL_BASE_URL` non-empty AND `$phase ∈ LOCAL_PHASES` AND `MODEL_LOCAL_{phase}` non-empty AND `localHealthy()` true → `{model: MODEL_LOCAL_{phase}, base_url: LOCAL_BASE_URL, auth_token: LOCAL_AUTH_TOKEN}`. Else → `{model: model_{phase} (cloud), base_url: null}`. Health-fail logs `Log::warning('[ArticleGen] local inference unreachable, falling back to cloud', …)`.

**Env injection (only when `route.base_url` set):** prepend to the runner script before `claude -p`:
```bash
export ANTHROPIC_BASE_URL="<base_url>"
export ANTHROPIC_AUTH_TOKEN="<auth_token>"
unset ANTHROPIC_API_KEY CLAUDE_CODE_OAUTH_TOKEN
```
When `base_url` is null (cloud/carousel), inject NOTHING — OAuth from `~/.profile`/`/etc/environment` stays intact.

---

### Phase F0: Model bake-off (decides per-phase local model)

**Type:** Eval/ops (no Laravel code). **Estimated time:** 60–90 min (mostly model pulls + runs).

**Deliverable:** `docs/evals/local-model-bakeoff.md`

**Steps:**
1. On the Mac, pull candidates in Ollama: `qwen3` instruct (non-coder), `gemma3:27b` instruct, a SahabatAI build (e.g. `Supa-AI/gemma2-9b-cpt-sahabatai-v1-instruct`). Record VRAM/RAM + load success at ctx 32K.
2. Pick 3–5 real `content_ideas` (varied: news, how-to, opinion). For each candidate, run `/article-write` then `/article-score` (5-gate) via the existing pipeline with that model.
3. Tabulate per candidate: 5-gate scores (Quality/Virality/SEO/AI-Humanization/GEO + combined), prose brand-voice eyeball (ID idiom + EN fluency), JSON-envelope adherence on `/article-score`, latency.
4. Record the routing decision matrix → concrete `ARTICLE_GEN_MODEL_LOCAL_*` assignments (likely: JSON/structure→qwen, prose→gemma3-27b or SahabatAI).
5. Confirm measured token count of each phase's refs+prompt < 32K (validates the ctx assumption).

**Verification:**
- [ ] `docs/evals/local-model-bakeoff.md` exists with the per-candidate score table + final per-phase model decision
- [ ] Each chosen local model passes the `/article-score` combined threshold (≥70/100) on ≥3 sample ideas
- [ ] Measured ctx headroom confirmed (every Phase-1 phase < 32K tokens)
- [ ] No placeholder values — decision matrix names a real Ollama model per phase

---

### Phase F1: Tailscale tunnel + Ollama exposure

**Type:** Ops + verification (no Laravel code). **Estimated time:** 30 min.

**Steps:**
1. Mac: install Tailscale, join tailnet; set `OLLAMA_HOST=0.0.0.0` (launchd env) + restart Ollama; keep Mac awake during cron window via `caffeinate -dimsu` (or Energy Saver "prevent sleep") scoped to 05–22 WIB.
2. Tailscale ACL: allow ONLY the VPS node → Mac:11434; confirm Ollama is NOT reachable from the public internet.
3. VPS: install Tailscale, join tailnet; `curl http://<mac>.tailnet.ts.net:11434/v1/models` returns the model list.
4. VPS as `claudesn`: raw round-trip `ANTHROPIC_BASE_URL=http://<mac>…:11434/v1 ANTHROPIC_AUTH_TOKEN=ollama claude -p "say hi" --model <local-write-model>` returns a completion from the local model.

**Verification:**
- [ ] `curl …:11434/v1/models` from the VPS succeeds; same curl from a non-tailnet host fails (ACL proven)
- [ ] Raw `claude -p` round-trip from VPS via `ANTHROPIC_BASE_URL` returns local-model output
- [ ] Mac stays awake across a simulated cron tick in the 05–22 WIB window
- [ ] No public exposure of port 11434

---

### Phase F2a: Config keys + `resolveRoute()` (pure, TDD)

**Estimated time:** 12 min.

**Files:** Modify `config/services.php`; Modify `app/Services/ArticleGenerationService.php`; Test `tests/Unit/ArticleGenLocalRouteTest.php`.

**Steps:**
1. Write failing test for `resolveRoute('write')` returning a LOCAL route when `LOCAL_BASE_URL`+`LOCAL_PHASES=write`+`MODEL_LOCAL_WRITE` set (health stubbed true). Expected error: `Error: Call to undefined method App\Services\ArticleGenerationService::resolveRoute()`.
2. Run test, confirm it fails for that reason.
3. Add the new config keys to `config/services.php` `article_generation`; implement `protected function resolveRoute(string $phase): array` (returns `['model','base_url','auth_token']`), with `localHealthy()` temporarily stubbed to `true`.
4. Add tests: cloud fallback when `base_url` empty; cloud when phase ∉ `LOCAL_PHASES`; cloud when `MODEL_LOCAL_{phase}` empty. Run, confirm pass.
5. Commit: "feat(article-gen): per-phase local inference route resolution".

**Verification:**
- [ ] `php artisan test --filter=ArticleGenLocalRouteTest` green on CI
- [ ] `resolveRoute` returns local only when all 3 conditions met; cloud otherwise
- [ ] No placeholder/TODO in new code
- [ ] Existing ArticleGeneration tests still pass

---

### Phase F2b: `localHealthy()` health-check + fallback (TDD, `Http::fake`)

**Estimated time:** 12 min.

**Files:** Modify `app/Services/ArticleGenerationService.php`; Test `tests/Unit/ArticleGenLocalHealthTest.php`.

**Steps:**
1. Write failing test: `resolveRoute('write')` falls back to the CLOUD model + `base_url=null` when `Http::fake([…/v1/models => 500])`, and a warning is logged. Expected error: assertion fail (currently returns local route because health was stubbed true).
2. Run, confirm fail.
3. Implement `protected function localHealthy(): bool` — `Http::timeout(LOCAL_HEALTH_TIMEOUT)->get(LOCAL_BASE_URL.'/models')`, true on 2xx; cache the boolean for the request lifecycle; on non-2xx/exception return false + `Log::warning`. Wire it into `resolveRoute`.
4. Add tests: healthy (200) → local route; unreachable (timeout) → cloud + warning; health cached (one HTTP call across multiple `resolveRoute` calls in a run). Run, confirm pass.
5. Commit: "feat(article-gen): health-checked cloud fallback for local inference".

**Verification:**
- [ ] `php artisan test --filter=ArticleGenLocalHealthTest` green
- [ ] Down endpoint → cloud fallback + `Log::warning` emitted; healthy → local
- [ ] Health result cached once per run (assert single `Http` call)
- [ ] No real network call in tests (all `Http::fake`)

---

### Phase F2c: Inject env into runner scripts (refactor + TDD)

**Estimated time:** 15 min.

**Files:** Modify `app/Services/ArticleGenerationService.php` (`executeSSH`, `executeLocal`, `executeSyncPrompt`); Test `tests/Unit/ArticleGenRunnerScriptTest.php`.

**Steps:**
1. Write failing test: a new `protected function buildRunnerScript(string $phase, array $route, string $refsFile): string` includes the 3 export/unset lines when `route.base_url` set, and omits them when null. Expected error: `Error: Call to undefined method …::buildRunnerScript()`.
2. Run, confirm fail.
3. Extract the runner-script string assembly from `executeSSH`/`executeLocal`/`executeSyncPrompt` into `buildRunnerScript()` (pure — no Process). When `route.base_url` non-null, prepend `export ANTHROPIC_BASE_URL=… ; export ANTHROPIC_AUTH_TOKEN=… ; unset ANTHROPIC_API_KEY CLAUDE_CODE_OAUTH_TOKEN`. Keep `--model {route.model}`, `{refsFlag}`, `buildMcpFlags()` unchanged. Have the three exec methods call it.
4. Add tests: local route → script contains exports + local model + still contains mcp flags; cloud route → NO export lines + cloud model + OAuth untouched; escapeshell safety on `base_url`. Run, confirm pass.
5. Commit: "feat(article-gen): inject ANTHROPIC_BASE_URL per-phase, unset OAuth for local".

**Verification:**
- [ ] `php artisan test --filter=ArticleGenRunnerScriptTest` green
- [ ] Local phase script exports base_url+token and unsets OAuth/API key; cloud phase does neither
- [ ] `--mcp-config … --strict-mcp-config` still present in both (leak guard intact)
- [ ] Existing exec-path tests unaffected (no behavioral change when `LOCAL_BASE_URL` empty)

---

### Phase F3: Cutover article pipeline to local (config + E2E verification)

**Type:** Ops + verification. **Estimated time:** 20 min.

**Steps:**
1. On the VPS `.env`, set `ARTICLE_GEN_LOCAL_BASE_URL`, `ARTICLE_GEN_LOCAL_PHASES=prep,write,score,images,translate`, and `ARTICLE_GEN_MODEL_LOCAL_*` per F0; `php artisan config:cache && systemctl restart portfolio-queue.service`.
2. Trigger one real idea end-to-end through the pipeline; confirm `laravel.log` shows the local route (base_url) for the text phases and Opus/cloud for `/carousel-gen`.
3. Confirm output quality: generated article passes `/article-score` combined ≥70; carousel slides still authored via `/carousel-gen` on cloud; GeminiGen images render via poller as before.
4. Fallback drill: stop Ollama on the Mac, trigger again, confirm text phases fall back to cloud with the `Log::warning` and the pipeline still completes.

**Verification:**
- [ ] One full article generated with text phases on local inference (log-proven)
- [ ] `/carousel-gen` still ran on cloud Opus; GeminiGen images rendered
- [ ] Article passed `/article-score` ≥70
- [ ] Mac-off drill → automatic cloud fallback + warning + successful completion

---

### Phase F3b (optional follow-on): Extend routing to IG/TT caption services

**Type:** Code (reuse F2 pattern). **Estimated time:** 20 min. **Priority:** low (LinkedIn caption mostly skipped under force-carousel; IG/TT caption is the remaining cross-post text-LLM volume).

**Steps:**
1. Apply the same `resolveRoute`/`buildRunnerScript`/`localHealthy` pattern to the IG/TT caption generation services ([`InstagramGenerationService`](backend/app/Services/InstagramGenerationService.php), [`TiktokGenerationService`](backend/app/Services/TiktokGenerationService.php)) — extract a shared trait if duplication warrants (DRY).
2. TDD mirror of F2a–F2c for the chosen service(s).
3. Commit per service.

**Verification:**
- [ ] Caption services route to local when configured, fall back on health-fail
- [ ] Shared logic factored (no copy-paste of route/health/script) if >1 service adopts it
- [ ] Tests green on CI

---

### Phase F4: Observability + eval guardrail

**Type:** Code-light + ops. **Estimated time:** 15 min.

**Files:** Modify `app/Services/ArticleGenerationService.php` (structured dispatch log); `docs/evals/local-model-bakeoff.md` (re-run trigger).

**Steps:**
1. Write failing test asserting each dispatch emits a structured log line tagging `route` = `local|cloud|fallback` + `phase` + `model`. Expected error: assertion fail (no such field today).
2. Implement the structured log in the dispatch path (single `Log::info('[ArticleGen] dispatch', [...])`). Run, confirm pass.
3. Add a "re-run when" trigger block to the bake-off eval doc (model version bump, refs change, score regression) so quality drift is caught.
4. Commit: "feat(article-gen): route observability + eval re-run trigger".

**Verification:**
- [ ] Operator can grep `laravel.log` for `route=local|cloud|fallback` counts (token-savings + fallback-rate visible)
- [ ] Eval doc has explicit re-run triggers
- [ ] Test green on CI

---

### Execution notes

- **Phase order:** F0 → F1 must precede F2 cutover (F0 fills the model envs, F1 proves the tunnel). F2a→F2b→F2c are sequential (same file, build on each other). F3 needs F0+F1+F2. F3b/F4 after F3.
- **Parallelizable:** F0 (ops, Mac) and F2a (pure code, CI) are file-disjoint and can run concurrently; F1 is ops on Mac+VPS, independent of code phases.
- **Backward-compat:** with `ARTICLE_GEN_LOCAL_BASE_URL` empty, every route resolves cloud and `buildRunnerScript` injects nothing → zero behavior change. Safe to ship code (F2) before the tunnel (F1) exists.
- **Security:** F1 must verify port 11434 is tailnet-only (no public exposure); F2c must `escapeshellarg` the base_url before it enters the script.
