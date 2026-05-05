# CV Master Markdown API — Jobhunter Platform Integration

**Status:** Design approved — pending implementation plan
**Owner:** Ali Sadikin
**Created:** 2026-05-05
**Source:** Brainstorm session 2026-05-05 via `/gaspol-brainstorm`

## Context

Existing endpoint `GET /api/cv/export` (shipped April 2026, Phase 10 — see CLAUDE.md) returns
JSON Resume schema for jobhunter platform: 56 projects + 5 awards + top 5 thought_leadership
with `relevance_hint` heuristics. Token-protected (Sanctum + `ability:cv:read` + `throttle:30,1`).

**Gap:** jobhunter consumes CV via two LLM-driven skills (`cv-tailor` + `job-score`) where
JSON Resume is awkward for prompt embedding — verbose key/value structure, mixed schema,
~15-20% token overhead vs. flat narrative. This design adds a parallel endpoint that
returns the same source data rendered as dense, LLM-optimized markdown.

## Design

### Endpoint

```
GET /api/cv/master.md
  Auth: auth:sanctum, ability:cv:read
  Throttle: 30 requests/minute
  Optional query: ?compact=1 (truncates project narrative for shorter context windows)
  Response: text/markdown; charset=utf-8
  Caching: ETag via existing ApiETag middleware (Phase A)
```

Decision: separate endpoint, NOT `?format=md` on `/api/cv/export`. Rationale:
- JSON Resume schema is strict (RFC-ish) — mixing format negotiation pollutes the contract.
- Independent throttling/audit per format simpler.
- Single-purpose actions easier to test.

### Auth & Access

- **Sanctum bearer + `cv:read` ability** (same as `/api/cv/export`).
- Jobhunter platform already has token minted via `User::find(1)->createToken('jobhunter-cv-export', ['cv:read'])`.
- No public access — protects email/phone (when set in settings), prevents AI training scraping
  without consent.

### Language

- **English only.** Reads `post_translations` / `project_translations` rows where `locale='en'`.
  Silent fallback to primary-language translation when EN missing (mirrors current
  `/api/cv/export` behavior).
- No `?locale=` parameter — keeps endpoint surface minimal. Indonesian variant deferred until
  jobhunter actually needs ID-locale matching.

### Markdown Structure

```markdown
# Ali Sadikin
**{title}** · {city}, {country}
{email} · {linkedin_url} · {portfolio_url}

## Summary
{2-paragraph elevator pitch}

## Skills Matrix

### {Domain Name} (~{years} yrs · {n} projects)
- {skill bullet 1}
- {skill bullet 2}

[...4-5 domain groups]

## Selected Projects ({total_count})

### {seq}. {title} — {role} ({year_range})
Industry: {industry} · Stack: {tech_stack_csv}
Problem: {problem_statement}
Outcome: {outcome_summary}
Relevance: {relevance_hints_csv}

[...all published projects, sort_order ASC]

## Awards & Recognition

- **{year}** — {title}: {short_description}

[...5 awards, is_active=true]

## Thought Leadership

- [{post_title}]({url}) · {date} · {excerpt_120ch}

[...top 5 posts, published, by published_at DESC]

---
Generated {date} · alisadikinma.com/api/cv/master.md
```

### Skills Matrix Source

No explicit `skills` table exists. Hybrid derivation:

1. **Curated domain definitions** in new `config/cv.php`:
   ```php
   'skill_domains' => [
       'ai_automation' => [
           'label' => 'AI Automation',
           'years' => 7,
           'bullets' => [
               'LLM orchestration, RAG pipelines, prompt engineering',
               'n8n / Zapier / Make multi-step workflows',
               'Agent platforms: Claude Agent SDK, OpenAI Assistants, LangGraph',
           ],
       ],
       // ... vibe_coding, ai_agents, video_gen, manufacturing
   ],
   ```

2. **Project counts auto-aggregated** from `Project` `relevance_hint` matches —
   rendered as `(~{years} yrs · {n} projects)` suffix.

3. Manual control over bullet copy keeps quality high; counts stay live.

### Caching Strategy

**On-demand + ETag** (chosen over pre-render / TTL cache / no-cache):

- Generated fresh on each cache MISS.
- Existing `App\Http\Middleware\ApiETag` (Phase A, May 2026) emits `ETag: W/"..."`.
- Jobhunter sends `If-None-Match` on subsequent calls → 304 Not Modified (~80 bytes).
- Zero storage drift, no observer plumbing, no manual invalidation.
- DB query cost (~50 queries: settings + 56 projects + translations + 5 awards + 5 posts)
  acceptable since 304 path skips controller entirely.

### Files to Touch

**New (3):**
- `backend/app/Services/CvMasterMarkdownService.php` — pure rendering, single `render(): string`.
- `backend/resources/views/cv/master.blade.php` — Blade template (whitespace-controlled).
- `backend/config/cv.php` — `skill_domains` array.

**Modified (2):**
- `backend/app/Http/Controllers/Api/CvExportController.php` — add `master(Request)` action.
- `backend/routes/api.php` — register `GET /cv/master.md` under existing `cv` group.

**Tests (2):**
- `backend/tests/Feature/CvMasterMarkdownApiTest.php`
- `backend/tests/Unit/CvMasterMarkdownServiceTest.php`

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|-----------|-------------|-----------|-------|
| Identity (name, title, social) | `Setting{group=about}` keys: `name`, `title`, `social_links` | ✅ | Reuse `CvExportController::resolveBasics()` private method |
| Contact (email, phone, city) | `Setting{group=about}` keys: `email`, `phone`, `city` | ⚠️ | Per CLAUDE.md Phase 10 notes, may return null — render only when present |
| Summary | `Setting{group=about}` key TBD (`summary` / `bio` / `description`) | ⚠️ | Verify in plan phase; fallback to "AI Generalist with N+ years..." stub |
| Skills matrix copy | `config/cv.php` (hand-curated) | ❌ | New file |
| Skills matrix counts | `Project::published()->whereJsonContains('relevance_hint', $key)->count()` | ✅ | Aggregate at render time |
| Projects | `Project::published()->with('translations')->orderBy('sort_order')->get()` | ✅ | Same as `/api/cv/export` |
| Awards | `Award::where('is_active', true)->orderBy('year', 'desc')->get()` | ✅ | Same |
| Thought leadership | `Post::published()->orderByDesc('published_at')->limit(5)->with('translations')->get()` | ✅ | Same |
| ETag | `App\Http\Middleware\ApiETag` | ✅ | Phase A shipped |

### Token Budget

- Header + summary + skills: ~800 tokens
- 56 projects × ~150 tokens avg = ~8,400 tokens
- Awards (5) + thought leadership (5) + footer: ~700 tokens
- **Default total: ~9,900 tokens** (~25% of GPT-4 8k context, ~5% of Claude 200k)
- **Compact mode (`?compact=1`):** ~5,000 tokens — drops project Problem/Outcome,
  keeps title + role + year + industry + relevance.

### Out of Scope (deferred)

- Per-domain endpoints (`/api/cv/skills`, `/api/cv/experience` etc.) — single endpoint
  suffices for MVP; split if jobhunter actually needs cherry-pick.
- Bilingual `?locale=id` — add when ID-job matching becomes a real flow.
- Real-time delta endpoint (`?since=`) — ETag revalidation handles freshness for now.
- Tailoring metadata (per-role `role_fit_score`) — jobhunter side computes from
  `relevance_hint` already exposed in `/api/cv/export`.

### Verification Criteria

- [ ] `GET /api/cv/master.md` without auth → 401
- [ ] With token lacking `cv:read` ability → 403
- [ ] With valid token → 200 + `Content-Type: text/markdown; charset=utf-8`
- [ ] Response body contains `# Ali Sadikin`, `## Summary`, `## Skills Matrix`,
      `## Selected Projects`, `## Awards & Recognition`, `## Thought Leadership`.
- [ ] Project count rendered matches `Project::published()->count()`.
- [ ] `?compact=1` reduces body size by ≥40%.
- [ ] Second request with `If-None-Match: <etag>` returns 304.
- [ ] When `post_translations.en` missing, falls back to primary translation silently
      (no "[ID]" prefix, no error).
- [ ] Settings-derived contact fields render only when present (no `null` literals).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship `GET /api/cv/master.md` — an LLM-optimized markdown rendering of Ali's full
professional profile (identity + summary + skills + 56 projects + awards + thought
leadership) for jobhunter platform's `cv-tailor` and `job-score` skills. Reuses the
exact same data sources as the existing `/api/cv/export` JSON endpoint; adds zero
new tables, zero new auth surface.

### Architecture Context (from CLAUDE.md)

- **Existing endpoint:** `GET /api/cv/export` ([CvExportController](backend/app/Http/Controllers/Api/CvExportController.php)) returns JSON Resume schema for the same data. Reuses settings, projects, awards, posts.
- **Auth:** Sanctum bearer with `cv:read` ability (alias registered in [bootstrap/app.php](backend/bootstrap/app.php)). Token mint pattern: `User::find(1)->createToken('jobhunter-cv-export', ['cv:read'])`.
- **Settings group `about` keys** (verified May 4, 2026 in CvExportController phpdoc): `name, title, bio, profile_photo, skills, experience, social_links, languages, certifications, hero_tagline, availability_note, trust_strip, mission, what_i_do, approach, collaboration_modes, statistics`. Notably ABSENT until operator adds them: `email, phone, city, country` — render only when truthy.
- **Project ordering:** `Project::with('translations')->orderBy('sort_order')->orderBy('id')->get()` (matches existing CvExportController, NO `published` filter — full inventory, ~56 rows).
- **Award ordering:** `Award::orderByDesc('is_featured')->orderByDesc('id')->get()` — NO `is_active` filter (corrects design assumption; matches CvExportController).
- **Post sourcing:** `Post::with(['translations', 'category'])->where('published', true)->whereNotNull('published_at')->orderByDesc('published_at')->limit(5)->get()`.
- **ETag middleware:** [`App\Http\Middleware\ApiETag`](backend/app/Http/Middleware/ApiETag.php) (Phase A, May 2026) auto-emits `W/"hash"` on 2xx GET JSON responses. Confirmed it skips streamed/binary — for markdown text response we need to verify it still applies, OR set ETag manually if the middleware bails on `text/markdown`. Check during Phase G.
- **Test pattern:** `tests/Feature/CvExportApiTest.php` is the authoritative reference — uses `RefreshDatabase`, `Sanctum::actingAs($user, ['cv:read'])`, `config(['app.url' => 'http://localhost'])` setUp workaround for sqlite.

### Tech Stack

- **PHP 8.2** + **Laravel 12** (existing).
- **Blade** for the markdown template (whitespace control + readability for future ops).
- **PHPUnit** (project convention — NOT Pest, per existing `CvExportApiTest`).
- **No new dependencies.** Uses existing Sanctum, Setting model, Project/Award/Post models.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Identity (name, title, summary) | `Setting{group=about, key IN (name, title, bio)}` | `Setting::where('group','about')->pluck('value','key')` | ✅ Yes | Use existing pattern from `CvExportController::export()` |
| Contact (email, phone, city) | `Setting{group=about}` keys (currently null in prod) | Same pluck | ✅ Yes (keys may be absent) | Render only when truthy — never emit `null` |
| Social profiles | `Setting{group=about, key=social_links}` JSON array | Reuse private `parseSocialLinks($raw)` from `CvExportController` | ✅ Yes | Extract helper to service OR duplicate (~10 LoC, judgment call in Phase B) |
| Skills domain definitions | `config/cv.php` `skill_domains` array | `config('cv.skill_domains')` | ❌ No | Create new config file with hand-curated 4-5 domains |
| Skills project counts | `Project::all()` filtered by relevance hint heuristic | New service method `aggregateProjectCountByDomain()` | ⚠️ Partial — Project rows exist; relevance_hint is a derived field on `CvProjectResource`, NOT stored on `projects` table | Replicate the hint heuristic from `CvProjectResource` inside the service (DRY: extract the heuristic into a helper class shared by both) |
| Projects (full inventory) | `Project::with('translations')->orderBy('sort_order')->orderBy('id')->get()` | Same query as existing CvExportController | ✅ Yes | Direct reuse |
| Project translation (EN) | `Project->translations` collection, filter `language='en'` | `$project->translations->firstWhere('language', 'en')` | ✅ Yes | Silent fallback to primary translation when EN missing |
| Awards | `Award::orderByDesc('is_featured')->orderByDesc('id')->get()` | Same as CvExportController | ✅ Yes | Direct reuse |
| Thought leadership | `Post::with(['translations','category'])->where('published',true)->whereNotNull('published_at')->orderByDesc('published_at')->limit(5)->get()` | Same as CvExportController | ✅ Yes | Direct reuse |
| Routing | Existing `cv` middleware group in `routes/api.php` lines 74-76 | N/A | ✅ Yes | Add one `Route::get(...)` line inside the group |
| Auth gate | `auth:sanctum, ability:cv:read` middleware aliases | Already registered in `bootstrap/app.php` | ✅ Yes | Inherit from route group |
| Throttle | `throttle:30,1` middleware on the route group | N/A | ✅ Yes | Inherit from route group |
| ETag | `App\Http\Middleware\ApiETag` middleware | Auto-applied via api group | ⚠️ Maybe | Verify in Phase G it fires on `text/markdown` response — fall back to manual `ETag` header if middleware bails |

**Anti-placeholder contract:** every cell above marked ✅ uses production data sources by name. The one ❌ row (`config/cv.php`) is created in Phase B as a real, populated config file with hand-written copy — NOT a stub. The ⚠️ rows have explicit fallback strategies.

### Phase Breakdown

| Phase | Code Deliverable | Est. Time | Verification |
|-------|------------------|-----------|--------------|
| A | Route + controller stub + 3 auth gate tests | 10 min | 401 / 403 / 200 + `text/markdown` content-type pass |
| B | `config/cv.php` skill_domains + `CvMasterMarkdownService` skeleton | 10 min | Service unit test renders non-empty string with header |
| C | Identity + summary section in Blade template | 15 min | Feature test asserts `# Ali Sadikin`, title, bio in body |
| D | Skills matrix section (config copy + project count aggregation) | 15 min | Feature test asserts each domain header rendered with `(~N yrs · M projects)` suffix |
| E | Projects section (loop all rows, EN translation fallback) | 20 min | Feature test asserts project count matches DB, sort_order honored, primary-language fallback works |
| F | Awards + thought leadership + footer sections | 10 min | Feature test asserts both sections present, item counts correct |
| G | `?compact=1` query param + ETag round-trip verification | 15 min | Compact body ≥40% smaller; second request with `If-None-Match` → 304 |
| H | Update root CLAUDE.md (Last Updated + API Routes section) | 5 min | CLAUDE.md mentions new endpoint under "Public Routes" / cv export note |

**Total estimated time:** ~100 minutes.

### Phase A: Scaffold route + controller action + auth gate tests

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/CvExportController.php` — add `master(Request)` action returning a stub markdown string
- Modify: `backend/routes/api.php` — add `Route::get('/cv/master.md', [CvExportController::class, 'master'])` inside existing `cv:read` group at lines 74-76
- Test: `backend/tests/Feature/CvMasterMarkdownApiTest.php` (new)

**Steps:**
1. Write failing test `returns_401_without_token` in `CvMasterMarkdownApiTest`. Expected error: `Method [master] does not exist on [CvExportController]` OR `404 (route not registered)`.
2. Run test, confirm it fails for the expected reason.
3. Add `Route::get('/cv/master.md', [CvExportController::class, 'master']);` to the existing route group.
4. Add public `master(Request $request)` method on `CvExportController` returning `response('# stub', 200)->header('Content-Type', 'text/markdown; charset=utf-8')`.
5. Run test, confirm 401 passes.
6. Add tests `returns_403_when_token_missing_cv_read_ability` and `returns_200_with_markdown_content_type_when_authorized`. Run, confirm pass.
7. Commit: `feat(cv): scaffold /api/cv/master.md route + auth gate tests`.

**Verification:**
- [ ] `php artisan test --filter=CvMasterMarkdownApiTest` passes 3 tests
- [ ] `GET /api/cv/master.md` without auth → 401
- [ ] Token without `cv:read` ability → 403
- [ ] With `cv:read` token → 200 + `Content-Type: text/markdown; charset=utf-8`
- [ ] No placeholder/TODO comments in new code
- [ ] `php -l` clean on changed files

### Phase B: Skills config + service skeleton

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/config/cv.php` — `skill_domains` array with 4-5 hand-curated domains
- Create: `backend/app/Services/CvMasterMarkdownService.php` — class with `render(bool $compact = false): string` method
- Test: `backend/tests/Unit/CvMasterMarkdownServiceTest.php` (new)

**Steps:**
1. Write failing unit test `render_returns_non_empty_string_with_h1_header` in `CvMasterMarkdownServiceTest`. Expected error: `Class CvMasterMarkdownService not found`.
2. Run test, confirm fail.
3. Create `config/cv.php` with array shape:
   ```php
   return [
       'skill_domains' => [
           [
               'key' => 'ai_automation',
               'label' => 'AI Automation',
               'years' => 7,
               'bullets' => [
                   'LLM orchestration, RAG pipelines, prompt engineering',
                   'n8n / Zapier / Make multi-step workflows',
                   'Agent platforms: Claude Agent SDK, OpenAI Assistants, LangGraph',
               ],
           ],
           [
               'key' => 'vibe_coding',
               'label' => 'Vibe Coding',
               'years' => 3,
               'bullets' => [
                   'Claude Code / Cursor / Aider — pair programming with LLMs',
                   'Spec-driven development: brief → plan → execute → verify',
                   'AI-augmented refactoring + test generation',
               ],
           ],
           [
               'key' => 'ai_agents',
               'label' => 'AI Agents',
               'years' => 2,
               'bullets' => [
                   'Multi-agent orchestration with role-based prompting',
                   'Tool-use design + safety guardrails',
                   'Custom skills + MCP server integrations',
               ],
           ],
           [
               'key' => 'manufacturing',
               'label' => 'Industrial Automation & Manufacturing',
               'years' => 12,
               'bullets' => [
                   'PLC programming, SCADA, HMI design',
                   'Computer vision QA pipelines + edge inference',
                   'Palm oil mill (PKS) automation, sorting, inspection',
               ],
           ],
           [
               'key' => 'enterprise',
               'label' => 'Enterprise Software',
               'years' => 15,
               'bullets' => [
                   'Laravel + Vue full-stack delivery',
                   'API design, queue infrastructure, deploy automation',
                   'Banking, government, logistics domain experience',
               ],
           ],
       ],
   ];
   ```
4. Create `App\Services\CvMasterMarkdownService` with `render(bool $compact = false): string` returning `"# Ali Sadikin\n"` for now.
5. Run unit test, confirm pass.
6. Update `CvExportController::master()` to call `app(CvMasterMarkdownService::class)->render($request->boolean('compact'))` and return the result wrapped in `response($body, 200)->header('Content-Type', 'text/markdown; charset=utf-8')`.
7. Run feature test, confirm still passes.
8. Commit: `feat(cv): add skill_domains config + CvMasterMarkdownService skeleton`.

**Verification:**
- [ ] `php artisan test --filter=CvMasterMarkdownServiceTest` passes
- [ ] `php artisan test --filter=CvMasterMarkdownApiTest` still passes (no regression)
- [ ] `config('cv.skill_domains')` returns array of 5 domains in tinker
- [ ] No placeholder/TODO comments in new files
- [ ] No DB queries fired by service yet (skeleton)

### Phase C: Identity + summary section

**Estimated time:** 15 minutes

**Files:**
- Create: `backend/resources/views/cv/master.blade.php` — Blade template
- Modify: `backend/app/Services/CvMasterMarkdownService.php` — `render()` calls `view('cv.master', $data)->render()`
- Modify: `backend/tests/Feature/CvMasterMarkdownApiTest.php` — add `renders_identity_and_summary` test

**Steps:**
1. Write failing test `renders_identity_and_summary_from_settings` — seeds `name`, `title`, `bio`, `social_links`, asserts response body contains `# Ali Sadikin Ma`, `**AI Generalist Expert**`, `Bio summary text`, `linkedin.com/in/x`. Expected error: assertions fail since template not rendering settings yet.
2. Run test, confirm fail.
3. Create `resources/views/cv/master.blade.php` with `{{-- whitespace-controlled --}}` Blade directives:
   ```blade
   # {{ $basics['name'] }}
   **{{ $basics['title'] }}**@if(!empty($basics['city'])) · {{ $basics['city'] }}@endif@if(!empty($basics['country'])), {{ $basics['country'] }}@endif

   @if(!empty($basics['email']))
   {{ $basics['email'] }}@endif
   @foreach($basics['profiles'] as $profile)
   {{ str_replace(['https://','http://'], '', $profile['url']) }}@if(!$loop->last) · @endif
   @endforeach

   ## Summary

   {{ $basics['summary'] }}
   ```
4. Implement `CvMasterMarkdownService::render()`:
   - `Setting::where('group','about')->pluck('value','key')` → `$about`
   - `parseSocialLinks($about->get('social_links'))` (extract from `CvExportController` to a private method on the service, OR call existing controller method via dependency — pick: duplicate inline, ~12 LoC, avoids coupling)
   - Build `$basics` array with `name`, `title`, `summary` (from `bio`), `email`, `phone`, `city`, `country`, `profiles`
   - Pass to `view('cv.master', compact('basics'))->render()`
5. Run feature test, confirm pass.
6. Add unit test `service_renders_settings_into_header_block` in `CvMasterMarkdownServiceTest` — seeds Settings, calls `app(CvMasterMarkdownService::class)->render()`, asserts string contains expected substrings. Run, confirm pass.
7. Add edge-case test `omits_optional_contact_fields_when_settings_absent` — seeds only `name` + `title` + `bio`, asserts response body does NOT contain `null`, `email:`, or empty bullet lines.
8. Run, confirm pass.
9. Commit: `feat(cv): render identity + summary section from settings`.

**Verification:**
- [ ] All tests in `CvMasterMarkdownApiTest` + `CvMasterMarkdownServiceTest` pass
- [ ] Body contains `# {name}`, `**{title}**`, `{summary}`, `{social url}` lines
- [ ] When `email`/`phone`/`city` settings missing → no `null` literal, no empty bullet
- [ ] No placeholder/TODO comments

### Phase D: Skills matrix section + project count aggregation

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/resources/views/cv/master.blade.php` — append `## Skills Matrix` block
- Modify: `backend/app/Services/CvMasterMarkdownService.php` — add `aggregateSkillDomains()` method that joins `config('cv.skill_domains')` with project counts
- Modify: `backend/app/Http/Resources/Cv/CvProjectResource.php` — extract `relevance_hint` heuristic into static helper if not already (judgment call: if it's already a private method, extract to static; if extraction is risky, replicate the heuristic in the service and flag for cleanup)
- Test: extend both test files

**Steps:**
1. Write failing feature test `renders_skills_matrix_with_domain_headers_and_project_counts` — seeds 3 projects matching `ai_automation` heuristic + 2 matching `vibe_coding`, asserts body contains `### AI Automation (~7 yrs · 3 projects)` and `### Vibe Coding (~3 yrs · 2 projects)`.
2. Run, confirm fail.
3. Read `CvProjectResource::relevance_hint` heuristic. If it lives in a closure/private method, extract to a `RelevanceHintHelper` class with public static `forProject(Project $p): array` returning the array of hint keys. Confirm existing `CvExportApiTest` still passes after the extraction.
4. Add `aggregateSkillDomains(Collection $projects): array` on the service — for each domain in `config('cv.skill_domains')`, count projects whose `RelevanceHintHelper::forProject($p)` array contains the domain `key`. Return `[['label' => ..., 'years' => ..., 'count' => ..., 'bullets' => [...]], ...]`.
5. Append Blade block:
   ```blade

   ## Skills Matrix

   @foreach($skill_domains as $domain)
   ### {{ $domain['label'] }} (~{{ $domain['years'] }} yrs · {{ $domain['count'] }} projects)
   @foreach($domain['bullets'] as $bullet)
   - {{ $bullet }}
   @endforeach

   @endforeach
   ```
6. Pass `$skill_domains` from service to view.
7. Run all tests, confirm pass.
8. Commit: `feat(cv): render skills matrix with project count aggregation`.

**Verification:**
- [ ] Skills matrix renders 5 domain headers
- [ ] Each domain shows correct project count from DB (verified via test seed)
- [ ] Bullets render under each domain
- [ ] `CvExportApiTest` still passes (no regression from RelevanceHintHelper extraction if performed)
- [ ] No N+1: aggregation runs in O(N projects × M domains) memory, single query

### Phase E: Projects section

**Estimated time:** 20 minutes

**Files:**
- Modify: `backend/resources/views/cv/master.blade.php` — append `## Selected Projects` loop
- Modify: `backend/app/Services/CvMasterMarkdownService.php` — load projects + provide formatter helpers
- Test: extend feature test

**Steps:**
1. Write failing test `renders_all_projects_in_sort_order_with_en_translation` — seeds 3 projects with sort_order [30, 10, 20] + en translations, asserts body lists project titles in order [10, 20, 30] under `## Selected Projects (3)`.
2. Run, confirm fail.
3. Update service to load projects: `$projects = Project::with('translations')->orderBy('sort_order')->orderBy('id')->get()`.
4. For each project, build a render-ready array:
   - `title` (from EN translation, fallback to primary translation, fallback to `$project->title`)
   - `role` (from project field if exists, else null)
   - `year_range` (from `period` / `year_start` / `year_end` fields — inspect Project model schema to confirm field name; fallback to `created_at` year)
   - `industry` (from project tag/category field)
   - `tech_stack` (from project tags or `tools` field — inspect schema)
   - `problem` (truncated EN translation `description` to ~80 words)
   - `outcome` (from project `outcome` / `result` field if exists)
   - `relevance` (CSV from `RelevanceHintHelper::forProject($p)`)
5. Append Blade block:
   ```blade

   ## Selected Projects ({{ count($projects) }})

   @foreach($projects as $i => $p)
   ### {{ $i + 1 }}. {{ $p['title'] }}@if(!empty($p['role'])) — {{ $p['role'] }}@endif@if(!empty($p['year_range'])) ({{ $p['year_range'] }})@endif
   @if(!empty($p['industry']) || !empty($p['tech_stack']))
   @if(!empty($p['industry']))Industry: {{ $p['industry'] }}@endif@if(!empty($p['industry']) && !empty($p['tech_stack'])) · @endif@if(!empty($p['tech_stack']))Stack: {{ $p['tech_stack'] }}@endif
   @endif
   @if(!$compact && !empty($p['problem']))
   Problem: {{ $p['problem'] }}
   @endif
   @if(!$compact && !empty($p['outcome']))
   Outcome: {{ $p['outcome'] }}
   @endif
   @if(!empty($p['relevance']))
   Relevance: {{ $p['relevance'] }}
   @endif

   @endforeach
   ```
6. Run all tests, confirm pass.
7. Add edge-case test `falls_back_to_primary_translation_when_en_missing` — seeds project with only `id` translation, asserts ID title appears in body without `[ID]` prefix.
8. Run, confirm pass.
9. Commit: `feat(cv): render full project portfolio with EN fallback`.

**Verification:**
- [ ] All projects rendered in sort_order
- [ ] EN translation used when available
- [ ] Silent fallback to primary translation when EN missing (no `[ID]` prefix in output)
- [ ] Project count in section header matches `Project::count()`
- [ ] No N+1: single eager-loaded query for projects + translations
- [ ] No `null` strings in output (e.g., `Industry: ` followed by empty)

### Phase F: Awards + thought leadership + footer

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/resources/views/cv/master.blade.php` — append both sections + footer
- Modify: `backend/app/Services/CvMasterMarkdownService.php` — load awards + posts
- Test: extend feature test

**Steps:**
1. Write failing test `renders_awards_section_ordered_by_is_featured_then_id_desc` — seeds 3 awards (mix of featured/unfeatured), asserts ordering matches `is_featured DESC, id DESC` (mirror `CvExportApiTest::awards_ordered_by_is_featured_desc_then_id_desc`).
2. Run, confirm fail.
3. Write failing test `renders_thought_leadership_with_top_5_posts_by_published_at`.
4. Update service to load `$awards = Award::orderByDesc('is_featured')->orderByDesc('id')->get()` and `$posts = Post::with(['translations','category'])->where('published',true)->whereNotNull('published_at')->orderByDesc('published_at')->limit(5)->get()`.
5. Append Blade block:
   ```blade

   ## Awards & Recognition

   @foreach($awards as $a)
   - **{{ $a['year'] }}** — {{ $a['title'] }}@if(!empty($a['organization'])): {{ $a['organization'] }}@endif@if(!empty($a['description'])) — {{ $a['description'] }}@endif
   @endforeach

   ## Thought Leadership

   @foreach($posts as $p)
   - [{{ $p['title'] }}]({{ $p['url'] }}) · {{ $p['date'] }} · {{ $p['excerpt'] }}
   @endforeach

   ---
   Generated {{ $generated_at }} · {{ $self_url }}
   ```
6. Run all tests, confirm pass.
7. Commit: `feat(cv): render awards, thought leadership, footer`.

**Verification:**
- [ ] Awards section ordered correctly (featured first)
- [ ] Thought leadership shows ≤5 posts
- [ ] Posts ordered by `published_at DESC`
- [ ] Footer line includes generated timestamp + self URL
- [ ] All earlier tests still pass (no regression)

### Phase G: Compact mode + ETag verification

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/CvExportController.php` — pass `$request->boolean('compact')` to service
- Modify: `backend/app/Services/CvMasterMarkdownService.php` — `render(bool $compact)` already wired in Phase B; verify Blade template honors `$compact` flag (Phase E template should already gate Problem/Outcome behind `!$compact`)
- Test: extend feature test

**Steps:**
1. Write failing test `compact_mode_reduces_body_size_by_at_least_40_percent` — seeds 5 projects with full Problem/Outcome strings, fetches `/api/cv/master.md` and `/api/cv/master.md?compact=1`, asserts compact body length ≤ 60% of full body length.
2. Run, confirm fail (will fail if Phase E template didn't already gate Problem/Outcome — fix the gating now).
3. Confirm Blade `@if(!$compact && !empty($p['problem']))` and matching outcome gate are correct.
4. Run, confirm pass.
5. Write `etag_round_trip_returns_304_on_repeat_request`:
   - First request: capture `ETag` header from response.
   - Second request with `If-None-Match: <etag>` → assert 304 status, empty body.
6. Run. If middleware doesn't fire on `text/markdown` (skips because of streamed/binary check or content-type filter), inspect [`ApiETag::handle()`](backend/app/Http/Middleware/ApiETag.php). Two outcomes:
   - **(a) Middleware works:** test passes, no code change.
   - **(b) Middleware bails on text/markdown:** add manual ETag computation in `CvExportController::master()` — `$etag = 'W/"' . md5($body) . '"'; if ($request->header('If-None-Match') === $etag) return response('', 304)->header('ETag', $etag); return response($body, 200)->header('ETag', $etag)->header('Content-Type', 'text/markdown; charset=utf-8');`
7. Run all tests, confirm pass.
8. Commit: `feat(cv): support ?compact=1 + verify ETag round-trip`.

**Verification:**
- [ ] `?compact=1` body ≥40% smaller than default
- [ ] `If-None-Match` round-trip → 304 + empty body
- [ ] Default `Content-Type: text/markdown; charset=utf-8` preserved on 200
- [ ] All earlier tests still pass

### Phase H: Documentation + CLAUDE.md update

**Estimated time:** 5 minutes

**Files:**
- Modify: `CLAUDE.md` (root) — append entry under "Public Routes" or extend existing Phase 10 (CV Master Export API) note. Bump "Last Updated" date.

**Steps:**
1. Open root `CLAUDE.md`, locate the existing Phase 10 CV Master Export note (search for "Phase 10 (CV Master Export API)").
2. Append a 1-paragraph addendum noting the new `GET /api/cv/master.md` endpoint, its purpose (jobhunter LLM consumption), `?compact=1` flag, and the `~10k token / ~5k compact` budget.
3. Update "Last Updated" line at bottom of root `CLAUDE.md` with today's date + 1-sentence summary mentioning markdown CV endpoint shipped.
4. Commit: `docs: document /api/cv/master.md endpoint in CLAUDE.md`.

**Verification:**
- [ ] CLAUDE.md mentions new endpoint with auth + throttle + token budget
- [ ] Last Updated reflects today's work
- [ ] No CI red after final commit

### Red Flag Self-Check

| Red Flag | Status |
|----------|--------|
| No Data Integration Map | ✅ Present, ⚠️ rows have explicit fallback strategies |
| Phase without Verification | ✅ Every phase has checklist |
| No reference to CLAUDE.md | ✅ Reuses CvExportController patterns + ApiETag middleware + Sanctum cv:read ability |
| Vague data sources | ✅ Every source named (Setting key, model query, config path) |
| No test steps | ✅ Every phase starts with "Write failing test" step |
| Phase too large | ✅ Largest phase (E) is 20 min — within bound |
| Placeholder language | ✅ Phase B explicitly populates `config/cv.php` with real curated copy |

### Execution Handoff

**Option 1: Execute in this session**
> Start Phase A. I'll use `/gaspol-execute` to implement with per-phase checkpoints + TDD hard gate.

**Option 2: Parallel execution**
> Phases B (config + service skeleton) and Phase C (identity rendering) have a dependency (B must finish first). Phase D depends on B. Phase E depends on C. Phase F is independent of D/E. Phase G depends on E+F. → Limited parallel benefit; sequential execution recommended.

**Option 3: Separate session**
> Save plan and resume later. This file at [docs/plans/2026-05-05-cv-master-markdown-api.md](docs/plans/2026-05-05-cv-master-markdown-api.md) contains the full design + plan.
