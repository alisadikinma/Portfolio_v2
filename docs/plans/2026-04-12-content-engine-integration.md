> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Enable Portfolio_v2 admin dashboard to TRIGGER content generation workflows on the Content Engine microservice (FastAPI, `http://127.0.0.1:8100`). This adds a new admin page where Ali can start carousel/blog/video generation, monitor workflow progress, and see health status — all from the existing admin panel.

## Architecture Context

**Existing (from CLAUDE.md):**
- Backend: Laravel 12, `backend/routes/api.php` — admin routes use `auth:sanctum` middleware with `prefix('admin/...')`
- Frontend: Vue 3 `<script setup>`, composables pattern (`useXxx.js`), Pinia stores, Tailwind CSS 4
- Admin layout: `frontend/src/layouts/AdminLayout.vue` — sidebar has `router-link` items
- Admin routes: `frontend/src/router/index.js` — lazy-loaded with `meta: { requiresAuth: true }`
- API wrapper: `frontend/src/services/api.js` — Axios with auth interceptor
- Existing service pattern: `ImageGenerationService.php` uses `Http::withHeaders()` + config
- Existing receiving endpoints already done:
  - `CarouselDraftController@saveDraft` → `POST /api/automation/carousel/save-draft`
  - `BlogPipelineController@saveDraft` → `POST /api/automation/blog/save-draft`

**Content Engine (external, already deployed):**
- Base URL: `http://127.0.0.1:8100` (same VPS)
- Auth: `x-api-key` header (NOT Bearer token)
- Key endpoints: `POST /workflows`, `GET /workflows/{id}`, `GET /health`

## Tech Stack

- Backend: Laravel 12 PHP, `Illuminate\Support\Facades\Http`
- Frontend: Vue 3.5 Composition API, Axios, Tailwind CSS 4
- Pattern: Service → Controller → Route → Composable → View

---

## Data Integration Map

| Feature | Data Source | Exists? | Action |
|---------|-----------|---------|--------|
| Content Engine config | `config('services.content_engine')` | **No** | Add to `services.php` + `.env` |
| HTTP client to Engine | `ContentEngineService.php` | **No** | Create (pattern: `ImageGenerationService`) |
| Admin API proxy | `ContentEngineController.php` | **No** | Create (pattern: `DashboardController`) |
| Admin routes | `routes/api.php` | **No** | Add under `auth:sanctum` |
| Frontend composable | `useContentEngine.js` | **No** | Create (pattern: `useAutomation.js`) |
| Admin page | `ContentEngine.vue` | **No** | Create |
| Sidebar nav link | `AdminLayout.vue` | **Yes** | Add one `router-link` |
| Router entry | `router/index.js` | **Yes** | Add one route |
| Auth interceptor | `services/api.js` | **Yes** | Use existing (adds Bearer token) |
| Carousel save-draft | `CarouselDraftController@saveDraft` | **Yes** | Already exists, no changes |
| Blog save-draft | `BlogPipelineController@saveDraft` | **Yes** | Already exists, no changes |

---

## Phase A: Backend Config + Service (5 min)

**Estimated time:** 5 minutes

**Files:**
- Modify: `backend/config/services.php`
- Modify: `backend/.env`
- Modify: `backend/.env.example`
- Create: `backend/app/Services/ContentEngineService.php`

**Steps:**

1. Add to `backend/.env` and `backend/.env.example`:
   ```
   CONTENT_ENGINE_URL=http://127.0.0.1:8100
   CONTENT_ENGINE_API_KEY=prod-key-CHANGE-ME
   ```

2. Add `content_engine` key to `backend/config/services.php` return array:
   ```php
   'content_engine' => [
       'url' => env('CONTENT_ENGINE_URL', 'http://127.0.0.1:8100'),
       'api_key' => env('CONTENT_ENGINE_API_KEY', ''),
   ],
   ```

3. Create `backend/app/Services/ContentEngineService.php`:
   ```php
   <?php

   namespace App\Services;

   use Illuminate\Support\Facades\Http;
   use Illuminate\Support\Facades\Log;

   class ContentEngineService
   {
       private string $baseUrl;
       private string $apiKey;

       public function __construct()
       {
           $this->baseUrl = rtrim(config('services.content_engine.url'), '/');
           $this->apiKey = config('services.content_engine.api_key', '');
       }

       private function client()
       {
           return Http::baseUrl($this->baseUrl)
               ->withHeaders(['x-api-key' => $this->apiKey])
               ->timeout(30)
               ->acceptJson();
       }

       public function healthCheck(): array
       {
           try {
               $response = Http::baseUrl($this->baseUrl)
                   ->timeout(5)
                   ->get('/health');
               return [
                   'healthy' => $response->successful(),
                   'data' => $response->json(),
               ];
           } catch (\Exception $e) {
               Log::warning('[ContentEngine] Health check failed: ' . $e->getMessage());
               return ['healthy' => false, 'data' => null, 'error' => $e->getMessage()];
           }
       }

       public function createWorkflow(string $workflowType, array $inputData = []): array
       {
           $response = $this->client()->post('/workflows', [
               'workflow_type' => $workflowType,
               'input_data' => $inputData,
           ]);

           if (!$response->successful()) {
               Log::error('[ContentEngine] createWorkflow failed', [
                   'status' => $response->status(),
                   'body' => $response->body(),
               ]);
               throw new \RuntimeException(
                   'Content Engine error: ' . ($response->json('detail') ?? $response->body())
               );
           }

           return $response->json();
       }

       public function getWorkflowStatus(int $id): array
       {
           $response = $this->client()->get("/workflows/{$id}");

           if (!$response->successful()) {
               throw new \RuntimeException('Workflow not found or engine error');
           }

           return $response->json();
       }

       public function listWorkflows(): array
       {
           $response = $this->client()->get('/workflows');

           return $response->successful() ? $response->json() : [];
       }
   }
   ```

**Verification:**
- [ ] `backend/.env` has `CONTENT_ENGINE_URL` and `CONTENT_ENGINE_API_KEY`
- [ ] `config('services.content_engine.url')` returns the URL
- [ ] `ContentEngineService.php` exists and uses `x-api-key` header (NOT Bearer)
- [ ] Service follows same pattern as `ImageGenerationService.php`
- [ ] No placeholder/TODO comments

**Commit:** `feat: add ContentEngineService for workflow triggering`

---

## Phase B: Backend Controller + Routes (5 min)

**Estimated time:** 5 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/ContentEngineController.php`
- Modify: `backend/routes/api.php`

**Steps:**

1. Create `backend/app/Http/Controllers/Api/Admin/ContentEngineController.php`:
   ```php
   <?php

   namespace App\Http\Controllers\Api\Admin;

   use App\Http\Controllers\Controller;
   use App\Services\ContentEngineService;
   use Illuminate\Http\JsonResponse;
   use Illuminate\Http\Request;

   class ContentEngineController extends Controller
   {
       private ContentEngineService $engine;

       public function __construct(ContentEngineService $engine)
       {
           $this->engine = $engine;
       }

       public function healthCheck(): JsonResponse
       {
           $result = $this->engine->healthCheck();

           return response()->json([
               'success' => true,
               'data' => $result,
           ]);
       }

       public function createWorkflow(Request $request): JsonResponse
       {
           $request->validate([
               'workflow_type' => 'required|string|in:carousel_rebrand,blog_article,video_social,video_promo',
               'input_data' => 'required|array',
               'input_data.topic' => 'required|string|max:500',
           ]);

           try {
               $result = $this->engine->createWorkflow(
                   $request->input('workflow_type'),
                   $request->input('input_data')
               );

               return response()->json([
                   'success' => true,
                   'data' => $result,
                   'message' => 'Workflow created successfully',
               ], 201);
           } catch (\RuntimeException $e) {
               return response()->json([
                   'success' => false,
                   'message' => $e->getMessage(),
               ], 502);
           }
       }

       public function getWorkflowStatus(int $id): JsonResponse
       {
           try {
               $result = $this->engine->getWorkflowStatus($id);

               return response()->json([
                   'success' => true,
                   'data' => $result,
               ]);
           } catch (\RuntimeException $e) {
               return response()->json([
                   'success' => false,
                   'message' => $e->getMessage(),
               ], 404);
           }
       }

       public function listWorkflows(): JsonResponse
       {
           $result = $this->engine->listWorkflows();

           return response()->json([
               'success' => true,
               'data' => $result,
           ]);
       }
   }
   ```

2. Add routes to `backend/routes/api.php` — add import at top and route group after existing admin groups:
   ```php
   // Import at top
   use App\Http\Controllers\Api\Admin\ContentEngineController;

   // Add after the admin/automation group:
   Route::middleware(['auth:sanctum'])->prefix('admin/content-engine')->group(function () {
       Route::get('/health', [ContentEngineController::class, 'healthCheck']);
       Route::get('/workflows', [ContentEngineController::class, 'listWorkflows']);
       Route::post('/workflows', [ContentEngineController::class, 'createWorkflow']);
       Route::get('/workflows/{id}', [ContentEngineController::class, 'getWorkflowStatus']);
   });
   ```

**Verification:**
- [ ] `php artisan route:list --path=admin/content-engine` shows 4 routes
- [ ] Controller validates `workflow_type` against allowed values
- [ ] Routes use `auth:sanctum` middleware
- [ ] HTTP 502 returned when Content Engine is unreachable (not 500)
- [ ] No placeholder/TODO comments

**Commit:** `feat: add ContentEngineController with admin routes`

---

## Phase C: Frontend Composable (5 min)

**Estimated time:** 5 minutes

**Files:**
- Create: `frontend/src/composables/useContentEngine.js`

**Steps:**

1. Create `frontend/src/composables/useContentEngine.js`:
   ```javascript
   import { ref } from 'vue'
   import api from '@/services/api'

   export function useContentEngine() {
     const isLoading = ref(false)
     const error = ref(null)

     const checkHealth = async () => {
       try {
         const response = await api.get('/admin/content-engine/health')
         return response.data.data
       } catch (err) {
         return { healthy: false, error: err.message }
       }
     }

     const createWorkflow = async (workflowType, inputData) => {
       isLoading.value = true
       error.value = null

       try {
         const response = await api.post('/admin/content-engine/workflows', {
           workflow_type: workflowType,
           input_data: inputData,
         })
         return { success: true, data: response.data.data }
       } catch (err) {
         error.value = err.response?.data?.message || 'Failed to create workflow'
         return { success: false, error: error.value }
       } finally {
         isLoading.value = false
       }
     }

     const getWorkflowStatus = async (id) => {
       try {
         const response = await api.get(`/admin/content-engine/workflows/${id}`)
         return response.data.data
       } catch (err) {
         return null
       }
     }

     const listWorkflows = async () => {
       try {
         const response = await api.get('/admin/content-engine/workflows')
         return response.data.data || []
       } catch (err) {
         return []
       }
     }

     return {
       isLoading,
       error,
       checkHealth,
       createWorkflow,
       getWorkflowStatus,
       listWorkflows,
     }
   }
   ```

**Verification:**
- [ ] Composable uses `api` (existing Axios instance with auth interceptor)
- [ ] Calls go to `/admin/content-engine/...` (Laravel proxy, NOT directly to `:8100`)
- [ ] Returns `{ success, data }` pattern matching existing composables
- [ ] No placeholder/TODO comments

**Commit:** `feat: add useContentEngine composable`

---

## Phase D: Admin Page — ContentEngine.vue (10 min)

**Estimated time:** 10 minutes

**Files:**
- Create: `frontend/src/views/admin/ContentEngine.vue`

**Steps:**

1. Create `frontend/src/views/admin/ContentEngine.vue` with three sections:
   - **Health status** — green/red indicator from `GET /health`
   - **Generate forms** — 4 workflow types with topic/language/niche inputs
   - **Active workflows table** — list with status polling every 10 seconds

   ```vue
   <script setup>
   import { ref, onMounted, onUnmounted } from 'vue'
   import { useContentEngine } from '@/composables/useContentEngine'
   import { useToast } from '@/composables/useToast'

   const { isLoading, error, checkHealth, createWorkflow, listWorkflows, getWorkflowStatus } = useContentEngine()
   const { showToast } = useToast()

   // Health
   const health = ref(null)

   // Form state
   const selectedType = ref('carousel_rebrand')
   const topic = ref('')
   const niche = ref('AI & Tech')
   const language = ref('en')
   const targetPlatform = ref('instagram')

   // Workflows list
   const workflows = ref([])
   let pollInterval = null

   const workflowTypes = [
     { value: 'carousel_rebrand', label: 'Instagram Carousel', icon: '🎠' },
     { value: 'blog_article', label: 'Blog Article', icon: '📝' },
     { value: 'video_social', label: 'Social Video (9:16)', icon: '🎬' },
     { value: 'video_promo', label: 'Promo Video (16:9)', icon: '🎥' },
   ]

   const statusColors = {
     pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
     processing: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
     completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
     failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
   }

   async function refreshHealth() {
     health.value = await checkHealth()
   }

   async function refreshWorkflows() {
     workflows.value = await listWorkflows()
   }

   async function handleGenerate() {
     if (!topic.value.trim()) return

     const inputData = {
       topic: topic.value.trim(),
       niche: niche.value,
       language: language.value,
     }

     if (['carousel_rebrand', 'video_social'].includes(selectedType.value)) {
       inputData.target_platform = targetPlatform.value
     }

     const result = await createWorkflow(selectedType.value, inputData)

     if (result.success) {
       showToast('Workflow started!', 'success')
       topic.value = ''
       await refreshWorkflows()
     } else {
       showToast(result.error || 'Failed to start workflow', 'error')
     }
   }

   onMounted(async () => {
     await Promise.all([refreshHealth(), refreshWorkflows()])
     pollInterval = setInterval(refreshWorkflows, 10000)
   })

   onUnmounted(() => {
     if (pollInterval) clearInterval(pollInterval)
   })
   </script>

   <template>
     <div class="max-w-6xl mx-auto space-y-8">
       <!-- Header -->
       <div class="flex items-center justify-between">
         <div>
           <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Content Engine</h1>
           <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">Generate carousels, blog articles, and videos with AI</p>
         </div>
         <!-- Health Badge -->
         <div class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm"
              :class="health?.healthy ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'">
           <span class="w-2 h-2 rounded-full" :class="health?.healthy ? 'bg-green-500' : 'bg-red-500'"></span>
           {{ health?.healthy ? 'Engine Online' : 'Engine Offline' }}
         </div>
       </div>

       <!-- Generate Form -->
       <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 p-6">
         <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Generate Content</h2>

         <!-- Workflow Type Selector -->
         <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
           <button
             v-for="wt in workflowTypes"
             :key="wt.value"
             @click="selectedType = wt.value"
             class="p-3 rounded-lg border-2 text-center transition-all text-sm"
             :class="selectedType === wt.value
               ? 'border-amber-500 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400'
               : 'border-neutral-200 dark:border-neutral-600 hover:border-neutral-300 dark:hover:border-neutral-500 text-neutral-600 dark:text-neutral-400'"
           >
             <span class="text-xl block mb-1">{{ wt.icon }}</span>
             {{ wt.label }}
           </button>
         </div>

         <!-- Input Fields -->
         <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
           <div class="md:col-span-2">
             <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Topic *</label>
             <input
               v-model="topic"
               type="text"
               placeholder="e.g. AI Automation for Startups"
               class="w-full px-4 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
             />
           </div>
           <div>
             <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Niche</label>
             <select v-model="niche" class="w-full px-4 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white">
               <option>AI & Tech</option>
               <option>Vibe Coding</option>
               <option>AI Automation</option>
               <option>AI Agents</option>
               <option>AI Video & Image</option>
               <option>Startup & Business</option>
             </select>
           </div>
           <div>
             <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Language</label>
             <select v-model="language" class="w-full px-4 py-2.5 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white">
               <option value="en">English</option>
               <option value="id">Indonesian</option>
             </select>
           </div>
         </div>

         <!-- Generate Button -->
         <button
           @click="handleGenerate"
           :disabled="isLoading || !topic.trim()"
           class="px-6 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 disabled:bg-neutral-300 dark:disabled:bg-neutral-600 text-white font-medium transition-colors"
         >
           <span v-if="isLoading">Starting...</span>
           <span v-else>Generate {{ workflowTypes.find(w => w.value === selectedType)?.label }}</span>
         </button>

         <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
       </div>

       <!-- Active Workflows -->
       <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 p-6">
         <div class="flex items-center justify-between mb-4">
           <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Workflows</h2>
           <button @click="refreshWorkflows" class="text-sm text-amber-600 hover:text-amber-700">Refresh</button>
         </div>

         <div v-if="!workflows.length" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
           No workflows yet. Generate your first content above.
         </div>

         <div v-else class="overflow-x-auto">
           <table class="w-full text-sm">
             <thead>
               <tr class="text-left text-neutral-500 dark:text-neutral-400 border-b border-neutral-200 dark:border-neutral-700">
                 <th class="pb-3 font-medium">ID</th>
                 <th class="pb-3 font-medium">Type</th>
                 <th class="pb-3 font-medium">Status</th>
                 <th class="pb-3 font-medium">Step</th>
                 <th class="pb-3 font-medium">Created</th>
               </tr>
             </thead>
             <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
               <tr v-for="wf in workflows" :key="wf.id" class="text-neutral-700 dark:text-neutral-300">
                 <td class="py-3 font-mono text-xs">#{{ wf.id }}</td>
                 <td class="py-3">{{ wf.workflow_type }}</td>
                 <td class="py-3">
                   <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColors[wf.status] || statusColors.pending">
                     {{ wf.status }}
                   </span>
                 </td>
                 <td class="py-3">{{ wf.current_step || 0 }}</td>
                 <td class="py-3 text-xs text-neutral-500">{{ wf.created_at }}</td>
               </tr>
             </tbody>
           </table>
         </div>
       </div>
     </div>
   </template>
   ```

**Verification:**
- [ ] Page renders with health indicator, form, and workflows table
- [ ] Workflow type selector highlights active selection
- [ ] Generate button calls `POST /admin/content-engine/workflows`
- [ ] Workflows table auto-refreshes every 10 seconds
- [ ] Status badges use correct colors (pending/processing/completed/failed)
- [ ] Uses Tailwind classes matching admin dark/light theme
- [ ] No placeholder/TODO comments

**Commit:** `feat: add Content Engine admin page`

---

## Phase E: Router + Sidebar Nav (3 min)

**Estimated time:** 3 minutes

**Files:**
- Modify: `frontend/src/router/index.js`
- Modify: `frontend/src/layouts/AdminLayout.vue`

**Steps:**

1. Add route to `frontend/src/router/index.js` — in the admin routes section, after carousel-drafts:
   ```javascript
   {
     path: '/admin/content-engine',
     name: 'admin-content-engine',
     component: () => import('@/views/admin/ContentEngine.vue'),
     meta: { requiresAuth: true, title: 'Content Engine' }
   },
   ```

2. Add sidebar link to `frontend/src/layouts/AdminLayout.vue` — after the "Carousels" `router-link`, add:
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
   (Heroicons "sparkles" icon — matches admin aesthetic)

**Verification:**
- [ ] `http://localhost:5173/admin/content-engine` loads the page
- [ ] Sidebar shows "Content Engine" link with sparkles icon
- [ ] Active state highlights correctly when on the page
- [ ] Nav item positioned after "Carousels" in sidebar

**Commit:** `feat: add Content Engine to admin nav + router`

---

## Phase F: VPS .env Setup (2 min)

**Estimated time:** 2 minutes

**Steps:**

1. SSH into VPS and add env vars to Portfolio_v2 backend:
   ```bash
   ssh claudesn@31.97.188.145
   sudo -u alisadikinma bash -c 'echo "" >> /var/www/Portfolio_v2/backend/.env'
   sudo -u alisadikinma bash -c 'echo "# Content Engine Integration" >> /var/www/Portfolio_v2/backend/.env'
   sudo -u alisadikinma bash -c 'echo "CONTENT_ENGINE_URL=http://127.0.0.1:8100" >> /var/www/Portfolio_v2/backend/.env'
   sudo -u alisadikinma bash -c 'echo "CONTENT_ENGINE_API_KEY=ACTUAL-KEY-HERE" >> /var/www/Portfolio_v2/backend/.env'
   ```

2. Clear config cache:
   ```bash
   cd /var/www/Portfolio_v2/backend && sudo -u alisadikinma php artisan config:clear
   ```

**Verification:**
- [ ] `php artisan tinker` → `config('services.content_engine.url')` returns `http://127.0.0.1:8100`
- [ ] API key is the REAL key (not placeholder)

**Commit:** No git commit (env files are gitignored)

---

## Summary

| Phase | What | Files | Time |
|-------|------|-------|------|
| A | Backend config + service | 3 files (1 new, 2 modify) | 5 min |
| B | Backend controller + routes | 2 files (1 new, 1 modify) | 5 min |
| C | Frontend composable | 1 file (new) | 5 min |
| D | Admin page (Vue) | 1 file (new) | 10 min |
| E | Router + sidebar nav | 2 files (modify) | 3 min |
| F | VPS .env setup | 0 files (server config) | 2 min |
| **Total** | | **9 files** | **~30 min** |

---

## Execution Options

**Option 1: Execute all phases sequentially now**
> Phases A→B→C→D→E are independent enough to implement straight through, then deploy (F).

**Option 2: Parallel execution**
> Backend (A+B) and Frontend (C+D+E) can run in parallel since they don't share files.

**Option 3: Save for next session**
> Plan saved at `docs/plans/2026-04-12-content-engine-integration.md`.
