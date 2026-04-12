> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations with Content Engine (`http://127.0.0.1:8100`),
> TrendingTopicService (4 sources), and existing carousel/blog draft endpoints. During execution,
> NEVER substitute placeholders for real data sources without explicit user approval.

## Goal

Build a Content Command Center admin page where Ali manages content ideas in a spreadsheet-style UI, pulls trending topics from 5 sources, and triggers Content Engine workflows through a 2-gate pipeline (configure → research → preview → generate). This REPLACES the simpler `2026-04-12-content-engine-integration.md` plan.

## Architecture Context

**Backend (from CLAUDE.md):**
- `TrendingTopicService.php` — has `getBestTopic()` + private `fetchGoogleTrends()`, `fetchTikTokTrending()`, `fetchYouTubeTrending()`, `fetchGoogleNews()`, `filterTechTopics()`. Needs new public `getAllTrends()` method.
- `CarouselDraftController@saveDraft` — existing receiving endpoint
- `BlogPipelineController@saveDraft` — existing receiving endpoint
- Admin routes pattern: `Route::middleware(['auth:sanctum'])->prefix('admin/...')`
- API response format: `{ success: true, data: ..., message: '...' }`

**Content Engine (deployed, same VPS):**
- Base: `http://127.0.0.1:8100`, auth: `x-api-key` header
- `POST /workflows` — create workflow (carousel_rebrand, blog_article, video_social, video_promo)
- `GET /workflows/{id}` — poll status
- `GET /workflows` — list all workflows
- `GET /health` — health check
- `GET /instagram/media` — Instagram trending

**Frontend:**
- Admin layout: `AdminLayout.vue` with sidebar `router-link` items
- Composable pattern: `useXxx.js` returning `{ data, isLoading, error, methods }`
- API wrapper: `services/api.js` (Axios, auth interceptor adds Bearer token)
- Toast: `useToast()` composable

## Tech Stack

- Backend: Laravel 12 PHP 8.2, `Illuminate\Support\Facades\Http`, MySQL 8
- Frontend: Vue 3.5 `<script setup>`, Tailwind CSS 4, Axios
- Pattern: Migration → Model → Service → Controller → Route → Composable → View

---

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Ideas CRUD | `content_ideas` table (MySQL) | ContentIdeaController | **No** | Create migration + model + controller |
| Content Engine config | `config('services.content_engine')` | config/services.php | **No** | Add to services.php + .env |
| HTTP to Engine | `ContentEngineService.php` | Http facade | **No** | Create (pattern: ImageGenerationService) |
| Trending 4 sources | `TrendingTopicService::getAllTrends()` | existing service | **Partial** | Add public `getAllTrends()` method |
| Trending Instagram | Content Engine `GET /instagram/media` | ContentEngineService | **No** | Add method to ContentEngineService |
| Create workflow | Content Engine `POST /workflows` | ContentEngineService | **No** | Add method |
| Poll workflow | Content Engine `GET /workflows/{id}` | ContentEngineService | **No** | Add method |
| Health check | Content Engine `GET /health` | ContentEngineService | **No** | Add method |
| Admin API routes | `routes/api.php` | auth:sanctum | **No** | Add route group |
| Frontend composable | `useContentEngine.js` | api.js | **No** | Create |
| Admin page | `ContentEngine.vue` | Vue component | **No** | Create |
| Sidebar link | `AdminLayout.vue` | router-link | **Yes** | Add one link |
| Router entry | `router/index.js` | lazy route | **Yes** | Add one route |
| Blog draft review | `/admin/posts` | existing page | **Yes** | Link from completed ideas |
| Carousel draft review | `/admin/carousel-drafts` | existing page | **Yes** | Link from completed ideas |

---

## Phase A: Database — Migration + Model (5 min)

**Estimated time:** 5 minutes

**Files:**
- Create: `backend/database/migrations/2026_04_12_000001_create_content_ideas_table.php`
- Create: `backend/app/Models/ContentIdea.php`

**Steps:**

1. Create migration:
   ```bash
   cd D:\Projects\Portfolio_v2\backend
   D:\xampp\php\php.exe artisan make:migration create_content_ideas_table
   ```

2. Define migration schema — `content_ideas` table with all fields from design:
   - `id`, `title` (varchar 500), `description` (text nullable)
   - `source` enum (manual, google_trends, youtube, tiktok, google_news, instagram)
   - `pillar` enum (vibe_coding, ai_automation, ai_agents, ai_video_image, general)
   - `priority` enum (low, medium, high)
   - `tags` JSON nullable, `languages` JSON nullable, `output_types` JSON nullable
   - `instructions` text nullable, `niche` varchar 100 default 'AI & Tech'
   - `status` enum (draft, researching, researched, generating, completed, archived)
   - `research_data` JSON nullable, `workflows` JSON nullable, `source_data` JSON nullable
   - `timestamps()`

3. Create `ContentIdea` model with `$fillable`, `$casts` (JSON fields), enum constants, scopes:
   - `scopeDraft`, `scopeActive` (not archived), `scopeByPillar`, `scopeByStatus`

4. Run migration:
   ```bash
   D:\xampp\php\php.exe artisan migrate
   ```

**Verification:**
- [ ] `php artisan migrate` succeeds
- [ ] `content_ideas` table exists with all columns
- [ ] `ContentIdea::create(['title' => 'test', 'source' => 'manual'])` works in tinker
- [ ] JSON casts work: `tags`, `languages`, `output_types`, `research_data`, `workflows`, `source_data`
- [ ] No placeholder/TODO comments

**Commit:** `feat: add content_ideas migration and model`

---

## Phase B: Backend — ContentEngineService (5 min)

**Estimated time:** 5 minutes

**Files:**
- Modify: `backend/config/services.php` — add `content_engine` key
- Modify: `backend/.env` + `backend/.env.example` — add env vars
- Create: `backend/app/Services/ContentEngineService.php`

**Steps:**

1. Add to `.env` and `.env.example`:
   ```
   CONTENT_ENGINE_URL=http://127.0.0.1:8100
   CONTENT_ENGINE_API_KEY=prod-key-CHANGE-ME
   ```

2. Add to `config/services.php` return array:
   ```php
   'content_engine' => [
       'url' => env('CONTENT_ENGINE_URL', 'http://127.0.0.1:8100'),
       'api_key' => env('CONTENT_ENGINE_API_KEY', ''),
   ],
   ```

3. Create `ContentEngineService.php` with methods:
   - `healthCheck(): array` — `GET /health` (5s timeout, no auth needed)
   - `createWorkflow(string $type, array $inputData): array` — `POST /workflows`
   - `getWorkflowStatus(int $id): array` — `GET /workflows/{id}`
   - `listWorkflows(): array` — `GET /workflows`
   - `getInstagramTrending(): array` — `GET /instagram/media`
   - Private `client()` — returns `Http::baseUrl()->withHeaders(['x-api-key' => ...])->timeout(30)`

**Verification:**
- [ ] `config('services.content_engine.url')` returns URL
- [ ] Service uses `x-api-key` header (NOT Bearer)
- [ ] `healthCheck()` returns `['healthy' => bool, 'data' => ...]`
- [ ] `createWorkflow()` throws `RuntimeException` on failure (not silent null)
- [ ] No placeholder/TODO comments

**Commit:** `feat: add ContentEngineService with workflow + health methods`

---

## Phase C: Backend — TrendingTopicService Update (3 min)

**Estimated time:** 3 minutes

**Files:**
- Modify: `backend/app/Services/TrendingTopicService.php`

**Steps:**

1. Add a new public method `getAllTrends()` that exposes what `getBestTopic()` does internally, but returns ALL filtered trends instead of just the best one:

   ```php
   /**
    * Return all tech-filtered trending topics from all sources.
    * Used by Content Command Center to let user pick topics.
    */
   public function getAllTrends(?string $source = null): array
   {
       $allTrends = [];

       if (!$source || $source === 'google_trends') {
           $allTrends = array_merge($allTrends, $this->fetchGoogleTrends());
       }
       if (!$source || $source === 'tiktok') {
           $allTrends = array_merge($allTrends, $this->fetchTikTokTrending());
       }
       if (!$source || $source === 'youtube') {
           $allTrends = array_merge($allTrends, $this->fetchYouTubeTrending());
       }
       if (!$source || $source === 'google_news') {
           $allTrends = array_merge($allTrends, $this->fetchGoogleNews());
       }

       // Filter for tech/AI relevance
       $techTrends = $this->filterTechTopics($allTrends);

       // Sort by score descending
       usort($techTrends, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

       return $techTrends;
   }
   ```

2. Change `fetchGoogleTrends`, `fetchTikTokTrending`, `fetchYouTubeTrending`, `fetchGoogleNews`, `filterTechTopics` from `private` to `protected` (so `getAllTrends` can call them, and subclasses can extend).

   Actually — `getAllTrends` is in the same class, so `private` is fine. No change needed. Just add the public method.

**Verification:**
- [ ] `(new TrendingTopicService)->getAllTrends()` returns array of trends
- [ ] Optional source filter works: `getAllTrends('google_trends')` returns only Google Trends
- [ ] Each trend has: `title`, `source`, `score` keys
- [ ] Existing `getBestTopic()` still works unchanged

**Commit:** `feat: add getAllTrends() to TrendingTopicService`

---

## Phase D: Backend — ContentIdeaController + Routes (10 min)

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php`
- Modify: `backend/routes/api.php`

**Steps:**

1. Create controller with methods:

   - `index(Request)` — list ideas with filters (pillar, status, priority, search)
   - `store(Request)` — create new idea (validate: title required, source/pillar/priority enums)
   - `update(Request, $id)` — update idea fields (title, pillar, priority, tags, etc.)
   - `destroy($id)` — delete idea
   - `archive($id)` — set status to 'archived'
   - `restore($id)` — set status back to 'draft'
   - `pullTrending(Request)` — call `TrendingTopicService::getAllTrends($source)` + `ContentEngineService::getInstagramTrending()`, return merged list
   - `importTrending(Request)` — receive array of selected trends, create ContentIdea rows as 'draft'
   - `startResearch($id)` — set status to 'researching', call Content Engine research workflow, save config (output_types, languages, instructions)
   - `getResearch($id)` — return research_data for an idea
   - `approveGenerate($id)` — set status to 'generating', call Content Engine `createWorkflow()` for each output_type, save workflow IDs
   - `revertToDraft($id)` — set status back to 'draft' (from researched)
   - `healthCheck()` — proxy to ContentEngineService::healthCheck()
   - `listWorkflows()` — proxy to ContentEngineService::listWorkflows()
   - `getWorkflowStatus($id)` — proxy to ContentEngineService::getWorkflowStatus()

2. Add routes to `backend/routes/api.php`:
   ```php
   use App\Http\Controllers\Api\Admin\ContentIdeaController;

   Route::middleware(['auth:sanctum'])->prefix('admin/content-engine')->group(function () {
       // Health & workflows (Content Engine proxy)
       Route::get('/health', [ContentIdeaController::class, 'healthCheck']);
       Route::get('/workflows', [ContentIdeaController::class, 'listWorkflows']);
       Route::get('/workflows/{id}', [ContentIdeaController::class, 'getWorkflowStatus']);

       // Content Ideas CRUD
       Route::get('/ideas', [ContentIdeaController::class, 'index']);
       Route::post('/ideas', [ContentIdeaController::class, 'store']);
       Route::put('/ideas/{id}', [ContentIdeaController::class, 'update']);
       Route::delete('/ideas/{id}', [ContentIdeaController::class, 'destroy']);
       Route::post('/ideas/{id}/archive', [ContentIdeaController::class, 'archive']);
       Route::post('/ideas/{id}/restore', [ContentIdeaController::class, 'restore']);
       Route::post('/ideas/{id}/revert', [ContentIdeaController::class, 'revertToDraft']);

       // Trending topics
       Route::get('/trending', [ContentIdeaController::class, 'pullTrending']);
       Route::post('/trending/import', [ContentIdeaController::class, 'importTrending']);

       // Pipeline actions
       Route::post('/ideas/{id}/research', [ContentIdeaController::class, 'startResearch']);
       Route::get('/ideas/{id}/research', [ContentIdeaController::class, 'getResearch']);
       Route::post('/ideas/{id}/generate', [ContentIdeaController::class, 'approveGenerate']);
   });
   ```

3. Validation rules:
   - `store`: title required|string|max:500, source in enum, pillar in enum, priority in enum
   - `update`: title sometimes|string|max:500, etc.
   - `startResearch`: output_types required|array|min:1, languages required|array|min:1, instructions nullable|string
   - `importTrending`: topics required|array|min:1, topics.*.title required|string
   - `pullTrending`: source nullable|string (filter param)

**Verification:**
- [ ] `php artisan route:list --path=admin/content-engine` shows 15 routes
- [ ] CRUD endpoints return proper JSON (`{ success, data, message }`)
- [ ] `pullTrending` returns merged list from 5 sources
- [ ] `startResearch` saves config to idea row + calls Content Engine
- [ ] `approveGenerate` creates separate workflow per output_type
- [ ] All routes use `auth:sanctum` middleware
- [ ] No placeholder/TODO comments

**Commit:** `feat: add ContentIdeaController with full pipeline routes`

---

## Phase E: Frontend — Composable (5 min)

**Estimated time:** 5 minutes

**Files:**
- Create: `frontend/src/composables/useContentEngine.js`

**Steps:**

1. Create composable with methods mapping to all backend endpoints:
   - **Ideas CRUD:** `listIdeas(filters)`, `createIdea(data)`, `updateIdea(id, data)`, `deleteIdea(id)`, `archiveIdea(id)`, `restoreIdea(id)`
   - **Trending:** `pullTrending(source)`, `importTrending(topics)`
   - **Pipeline:** `startResearch(id, config)`, `getResearch(id)`, `approveGenerate(id)`, `revertToDraft(id)`
   - **Engine:** `checkHealth()`, `listWorkflows()`, `getWorkflowStatus(id)`
   - **State:** `isLoading`, `error` refs

2. All calls go through `api` (existing Axios instance at `@/services/api`), targeting `/admin/content-engine/...`

**Verification:**
- [ ] Composable uses `api` from `@/services/api` (NOT raw axios or $fetch)
- [ ] All methods return `{ success, data }` or `{ success: false, error }`
- [ ] `isLoading` and `error` are reactive refs
- [ ] No direct calls to `:8100` — all through Laravel proxy
- [ ] No placeholder/TODO comments

**Commit:** `feat: add useContentEngine composable`

---

## Phase F: Frontend — ContentEngine.vue (15 min)

**Estimated time:** 15 minutes

**Files:**
- Create: `frontend/src/views/admin/ContentEngine.vue`

**Steps:**

1. Create page with 4 sections:

   **Section 1: Header bar**
   - Title "Content Engine"
   - Health badge (green/red dot + "Engine Online"/"Engine Offline")
   - [+ Add Row] button
   - [Pull Trending ▾] dropdown (All, Google Trends, YouTube, TikTok, News, Instagram)

   **Section 2: Filters**
   - Pillar dropdown (All, vibe_coding, ai_automation, ai_agents, ai_video_image)
   - Status dropdown (All, draft, researching, researched, generating, completed, archived)
   - Priority dropdown (All, low, medium, high)
   - Search input

   **Section 3: Ideas Spreadsheet Table**
   - Columns: #, Topic, Pillar, Priority, Status, Source, Actions
   - Status badges with colors (draft=gray, researching=blue, researched=purple, generating=yellow, completed=green, archived=neutral)
   - Action buttons change by status (Next/Preview/Monitor/View/Restore)
   - Row click → inline edit (topic only), or Edit button → opens edit row

   **Section 4: Workflow History**
   - Table: ID, Type, Topic, Status, Step, Created
   - Auto-refresh every 10 seconds
   - Refresh button

2. Create 3 modals (within the same component or as child components):

   **Modal 1: Trending Preview** (triggered by Pull Trending)
   - Shows list of trending topics with checkboxes
   - Source badge per topic
   - Select count
   - [Cancel] [Add N to Ideas List →]

   **Modal 2: Configuration** (triggered by "Next" on draft idea)
   - Output types checkboxes: Blog Article, Carousel, Video Social, Video Promo
   - Language checkboxes: English, Indonesian
   - Instructions textarea (optional)
   - [Cancel] [Confirm & Research →]

   **Modal 3: Research Preview** (triggered by "Preview" on researched idea)
   - Trending score, hooks list, angles list
   - Editable topic field
   - Summary of what will be generated
   - [← Back to Draft] [🚀 Approve & Generate]

3. Data flow:
   - `onMounted`: fetch ideas + health + workflows
   - `pollInterval`: refresh workflows every 10s
   - All mutations → call composable method → refresh ideas list
   - Modals use `ref` for visibility + current idea

4. Use existing Tailwind classes matching admin theme (dark mode: `dark:bg-neutral-800`, etc.)

**Verification:**
- [ ] Page renders with all 4 sections
- [ ] Ideas table shows data from `/admin/content-engine/ideas`
- [ ] Health indicator refreshes on mount
- [ ] Workflow history auto-polls every 10 seconds
- [ ] Modal 1 (trending): pull + select + import works
- [ ] Modal 2 (config): saves output_types + languages + instructions, triggers research
- [ ] Modal 3 (preview): shows research_data, approve triggers generation
- [ ] Filter dropdowns filter the ideas list
- [ ] Status badges use correct colors
- [ ] Delete shows confirmation dialog
- [ ] Archive/Restore toggles status
- [ ] No placeholder/TODO comments

**Commit:** `feat: add Content Command Center admin page`

---

## Phase G: Frontend — Router + Sidebar (3 min)

**Estimated time:** 3 minutes

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/layouts/AdminLayout.vue`

**Steps:**

1. Add route in `router/index.js` — after carousel-drafts route:
   ```javascript
   {
     path: '/admin/content-engine',
     name: 'admin-content-engine',
     component: () => import('@/views/admin/ContentEngine.vue'),
     meta: { requiresAuth: true, title: 'Content Engine' }
   },
   ```

2. Add sidebar link in `AdminLayout.vue` — after "Carousels" router-link:
   ```vue
   <router-link
     to="/admin/content-engine"
     class="flex items-center px-4 py-3 rounded-lg text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
     active-class="bg-neutral-100 dark:bg-neutral-700 !text-amber-600 dark:!text-amber-400 font-medium"
   >
     <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
       <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
     </svg>
     Content Engine
   </router-link>
   ```

**Verification:**
- [ ] `http://localhost:5173/admin/content-engine` loads the page
- [ ] Sidebar shows "Content Engine" with sparkles icon
- [ ] Active state highlights when on the page
- [ ] Position: after Carousels in sidebar

**Commit:** `feat: add Content Engine to admin router + sidebar`

---

## Phase H: VPS Deploy (5 min)

**Estimated time:** 5 minutes

**Steps:**

1. SSH into VPS, pull code:
   ```bash
   ssh claudesn@31.97.188.145
   cd /var/www/Portfolio_v2
   sudo -u alisadikinma git pull origin main
   ```

2. Run migration:
   ```bash
   cd backend
   sudo -u alisadikinma php artisan migrate --force
   ```

3. Add env vars:
   ```bash
   sudo -u alisadikinma bash -c 'cat >> /var/www/Portfolio_v2/backend/.env << EOF

   # Content Engine Integration
   CONTENT_ENGINE_URL=http://127.0.0.1:8100
   CONTENT_ENGINE_API_KEY=REAL-KEY-FROM-CONTENT-ENGINE-ENV
   EOF'
   ```

4. Clear caches + build frontend:
   ```bash
   sudo -u alisadikinma php artisan config:clear
   sudo -u alisadikinma php artisan route:clear
   cd ../frontend
   sudo -u alisadikinma npm install
   sudo -u alisadikinma npm run build
   sudo nginx -s reload
   ```

5. Verify:
   ```bash
   curl -H "Authorization: Bearer TOKEN" https://alisadikinma.com/api/admin/content-engine/health
   ```

**Verification:**
- [ ] Migration runs successfully on VPS
- [ ] Health endpoint returns `{ healthy: true }`
- [ ] Admin page loads at `https://alisadikinma.com/admin/content-engine`
- [ ] Content Engine API key is the REAL key (not placeholder)

**Commit:** No git commit (env files are gitignored)

---

## Summary

| Phase | What | Files | Time |
|-------|------|-------|------|
| A | Migration + Model | 2 new | 5 min |
| B | ContentEngineService + config | 1 new, 2 modify | 5 min |
| C | TrendingTopicService update | 1 modify | 3 min |
| D | Controller + Routes | 1 new, 1 modify | 10 min |
| E | Frontend composable | 1 new | 5 min |
| F | Admin page (Vue) | 1 new | 15 min |
| G | Router + sidebar | 2 modify | 3 min |
| H | VPS deploy | 0 (server) | 5 min |
| **Total** | | **6 new, 6 modify** | **~51 min** |

---

## Execution Options

**Option 1: Sequential** — A → B → C → D → E → F → G → H

**Option 2: Parallel** — Backend (A+B+C+D) and Frontend (E+F+G) can run in parallel since they don't share files. Deploy (H) runs last.

**Option 3: Save for next session** — Plan at `docs/plans/2026-04-12-content-command-center-plan.md`.
