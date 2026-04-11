# Nuxt 3 SSR Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.  
> **CRITICAL:** This plan specifies real integrations with existing Laravel backend, Pinia stores, and 20 composables. NEVER substitute placeholders. If data source doesn't exist, STOP and ask user.

## Goal

Transform Vue 3 SPA to Nuxt 3 with Server-Side Rendering (SSR) to unlock LLM discoverability. All page content must render on the server as static HTML so ChatGPT/Gemini/Claude crawlers can read without JavaScript. Preserve all functionality (20 composables, 14 Pinia stores, admin features). Timeline: 5-6 weeks.

## Architecture Context (from CLAUDE.md)

**Current Setup:**
- Frontend: Vue 3.5 + Vite 7 + Pinia 3 + Vue Router 4.5 + Tailwind CSS 4
- Backend: Laravel 12 + MySQL 8 + Sanctum 4 + Filament 4.1
- Auth: JWT tokens in localStorage (must migrate to cookies for SSR)
- API Base: `http://localhost/Portfolio_v2/backend/public/api`
- Project Path: `D:\Projects\Portfolio_v2\`

**Existing Patterns to Reuse:**
- Pinia stores (auth.js, posts.js, projects.js, ui.js, theme.js, etc.) → Works with Nuxt
- 20 composables (usePosts, useProjects, useAuth, etc.) → Migrate to .ts, adapt to server
- Tailwind CSS → @nuxtjs/tailwindcss module
- API response format: `{ data: {...}, meta: {...} }` → Handle in server routes
- Route structure: `/posts/{slug}` → `pages/blog/[slug].vue`

## Tech Stack (Existing Choices + New)

```
Framework:    Nuxt 3.11+ (with Vue 3.5)
Rendering:    SSR + Static generation + ISR (Incremental Static Regeneration)
State:        Pinia 3 (with useAsyncData for SSR sync)
Auth:         HttpOnly cookies (migrate from localStorage)
Styling:      Tailwind CSS 4 (@nuxtjs/tailwindcss)
HTTP Client:  $fetch (built-in, replaces axios)
Database:     MySQL 8 (backend only, unchanged)
Deploy:       Self-hosted XAMPP Apache
```

---

## Data Integration Map

| Component/Route | Data Source | Type | Existing? | Action |
|---|---|---|---|---|
| Home page (`/`) | Backend `/api/settings/site` | Server fetch | Yes (SettingsController) | Create `server/api/settings.ts` |
| Blog list (`/blog`) | Backend `/api/posts?page=1` | Server fetch | Yes (PostController) | Create `server/api/posts.ts` |
| Blog detail (`/blog/[slug]`) | Backend `/api/posts/{slug}` | Server fetch SSR | Yes (PostController) | Use server route above |
| Projects list (`/projects`) | Backend `/api/projects` | Server fetch | Yes (ProjectController) | Create `server/api/projects.ts` |
| Project detail (`/projects/[slug]`) | Backend `/api/projects/{slug}` | Server fetch SSR | Yes (ProjectController) | Use server route above |
| Awards page (`/awards`) | Backend `/api/awards` | Server fetch | Yes (AwardController) | Create `server/api/awards.ts` |
| Gallery page (`/gallery`) | Backend `/api/galleries` | Server fetch | Yes (GalleryController) | Create `server/api/galleries.ts` |
| Categories (`/blog?category=slug`) | Backend `/api/categories` | Server fetch | Yes (CategoryController) | Create `server/api/categories.ts` |
| Contact form (`/contact`) | Backend `/api/contact` (POST) | Client form | Yes (ContactController) | Client-side composable |
| Admin Dashboard | Backend `/api/admin/dashboard/stats` | Server + auth | Yes (DashboardController) | Server route with auth check |
| Blog filters (client-side) | Pinia store | Client state | Yes (posts.js store) | Migrate store, use useState() |
| Favorites/History | localStorage + Pinia | Client hydration | Yes (ui.js store) | useAsyncData() + useState() |
| Auth state | Backend `/api/auth/me` | Server + SSR | Yes (AuthController) | Server middleware, cookie-based |
| Theme toggle | Pinia store | Client state | Yes (theme.js store) | useAsyncData() for hydration |

**Key Rules:**
- ✅ Server-render all static content (Home, Projects, Blog index, Awards, Gallery, About)
- ✅ SSR dynamic routes (Blog detail, Project detail) on every request (with ISR caching)
- ✅ Hydrate client-side filters, favorites, theme toggle after load
- ✅ Server middleware validates auth, proxies backend API
- ✅ NO placeholder API calls — all use real Laravel backend routes

---

## Phase A: Project Setup & Dependencies (1 day)

**Estimated time:** 4 hours  
**Goal:** Initialize Nuxt 3 project, install dependencies, set up nuxt.config.ts

### A1: Create Nuxt 3 Project

**Files:**
- Create: `nuxt.config.ts`
- Create: `app.vue`
- Create: `tsconfig.json`
- Modify: `package.json`
- Delete: `vite.config.ts`, `src/main.js`, `index.html`

**Steps:**

1. Backup current package.json and vite.config.ts
   ```bash
   cp package.json package.json.backup
   cp vite.config.ts vite.config.ts.backup
   ```

2. Initialize Nuxt 3 scaffolding (simulate via manual setup):
   ```bash
   npm install -D nuxt @nuxt/devtools
   npm install pinia @pinia/nuxt
   npm install -D @nuxtjs/tailwindcss
   npm install -D @nuxtjs/google-fonts
   npm install -D typescript ts-node
   npm install h3 devalue
   ```

3. Update package.json scripts:
   ```json
   {
     "scripts": {
       "dev": "nuxt dev",
       "build": "nuxt build",
       "preview": "nuxt preview",
       "generate": "nuxt generate"
     }
   }
   ```

4. Create `nuxt.config.ts`:
   ```typescript
   export default defineNuxtConfig({
     ssr: true,  // Enable SSR
     devtools: { enabled: true },
     modules: [
       '@nuxtjs/tailwindcss',
       '@pinia/nuxt',
     ],
     
     // Prerender static routes at build time
     prerender: {
       routes: [
         '/',
         '/about',
         '/awards',
         '/gallery',
         '/contact',
         '/projects',
         '/blog',
         '/not-found'
       ],
       crawlLinks: true,
       ignore: ['/admin']  // Don't prerender admin routes
     },

     // Build configuration
     build: {
       transpile: ['axios']
     },

     // Environment variables
     runtimeConfig: {
       apiBase: 'http://localhost/Portfolio_v2/backend/public/api'
     },

     // Tailwind CSS
     tailwindcss: {
       configPath: './tailwind.config.js',
       exposeConfig: false
     },

     // Pinia state persistence
     pinia: {
       storeDir: './stores'
     }
   })
   ```

5. Create `app.vue`:
   ```vue
   <template>
     <div>
       <NuxtLayout>
         <NuxtPage />
       </NuxtLayout>
     </div>
   </template>

   <script setup>
   import { useThemeStore } from '@/stores/theme'

   const themeStore = useThemeStore()
   
   // Apply stored theme on app load
   onMounted(() => {
     if (themeStore.isDark) {
       document.documentElement.classList.add('dark')
     }
   })
   </script>
   ```

6. Create `tsconfig.json`:
   ```json
   {
     "compilerOptions": {
       "target": "ES2020",
       "module": "ESNext",
       "lib": ["ES2020", "DOM", "DOM.Iterable"],
       "jsx": "preserve",
       "strict": true,
       "esModuleInterop": true,
       "skipLibCheck": true,
       "forceConsistentCasingInFileNames": true,
       "moduleResolution": "bundler",
       "baseUrl": ".",
       "paths": {
         "@/*": ["./*"]
       }
     },
     "include": [".nuxt/dist/app/**/*.ts"]
   }
   ```

**Verification:**
- [ ] `npm install` completes without errors
- [ ] `npm run dev` starts Nuxt dev server on port 3000
- [ ] `http://localhost:3000` loads (shows blank page, that's ok)
- [ ] `tsc --noEmit` passes
- [ ] `nuxt.config.ts` is syntactically valid

**Commit:**
```bash
git add nuxt.config.ts app.vue tsconfig.json package.json package-lock.json
git commit -m "setup: initialize Nuxt 3 project with SSR config"
```

---

## Phase B: Migrate Directory Structure (1 day)

**Estimated time:** 3 hours  
**Goal:** Convert src/views → pages/, create layouts/, set up file-based routing

### B1: Create Pages Directory

**Files:**
- Create: `pages/index.vue` (home)
- Create: `pages/about.vue`
- Create: `pages/projects/index.vue`
- Create: `pages/projects/[slug].vue`
- Create: `pages/blog/index.vue`
- Create: `pages/blog/[slug].vue`
- Create: `pages/awards.vue`
- Create: `pages/gallery.vue`
- Create: `pages/contact.vue`
- Create: `pages/admin/index.vue`

**Steps:**

1. Create pages directory structure:
   ```bash
   mkdir -p pages/projects pages/blog pages/admin
   ```

2. Copy Home.vue to pages/index.vue (minimal migration):
   ```bash
   cp src/views/Home.vue pages/index.vue
   ```

3. Edit `pages/index.vue` - remove router imports (Vue Router no longer needed):
   ```vue
   <!-- Delete -->
   import { useRouter } from 'vue-router'
   const router = useRouter()
   
   <!-- Replace with -->
   const router = useRouter()  // Nuxt auto-provides this
   ```

4. Create `pages/about.vue`:
   ```bash
   cp src/views/About.vue pages/about.vue
   ```

5. Create `pages/projects/index.vue`:
   ```bash
   cp src/views/Projects.vue pages/projects/index.vue
   ```

6. Create `pages/projects/[slug].vue` (dynamic route):
   ```bash
   cp src/views/ProjectDetail.vue pages/projects/[slug].vue
   ```

   Edit to use route params:
   ```vue
   <script setup>
   const route = useRoute()
   const slug = route.params.slug  // Auto-extracted from [slug]
   
   const { project } = useProjects()
   onMounted(() => fetchProject(slug))
   </script>
   ```

7. Create `pages/blog/index.vue`:
   ```bash
   cp src/views/Blog.vue pages/blog/index.vue
   ```

8. Create `pages/blog/[slug].vue`:
   ```bash
   cp src/views/BlogDetail.vue pages/blog/[slug].vue
   ```

   Edit for dynamic route:
   ```vue
   <script setup>
   const route = useRoute()
   const slug = route.params.slug
   
   const { post } = usePosts()
   onMounted(() => fetchPost(slug))
   </script>
   ```

9. Create other pages:
   ```bash
   cp src/views/Awards.vue pages/awards.vue
   cp src/views/Gallery.vue pages/gallery.vue
   cp src/views/Contact.vue pages/contact.vue
   cp src/views/admin/Dashboard.vue pages/admin/index.vue
   ```

10. Create placeholder pages for admin routes:
    ```bash
    mkdir -p pages/admin/posts pages/admin/projects
    
    # pages/admin/posts/index.vue
    # pages/admin/posts/[id].vue
    # etc.
    ```

**Verification:**
- [ ] `pages/` directory contains all view files
- [ ] File-based routing works: `http://localhost:3000/about` loads About page
- [ ] Dynamic routes work: `http://localhost:3000/blog/test-slug` renders
- [ ] Admin routes protected (will verify in Phase C)
- [ ] No broken imports (TypeScript check passes)

**Commit:**
```bash
git add pages/
git commit -m "migration: convert src/views to pages/ with file-based routing"
```

### B2: Create Layouts

**Files:**
- Create: `layouts/default.vue` (public layout)
- Create: `layouts/admin.vue` (admin layout)

**Steps:**

1. Create `layouts/default.vue` (public layout with navbar, footer):
   ```vue
   <template>
     <div class="min-h-screen flex flex-col">
       <TheNavigation />
       <main class="flex-1">
         <slot />
       </main>
       <TheFooter />
     </div>
   </template>

   <script setup>
   import TheNavigation from '@/components/TheNavigation.vue'
   import TheFooter from '@/components/TheFooter.vue'
   </script>
   ```

2. Create `layouts/admin.vue` (admin layout with sidebar):
   ```vue
   <template>
     <div class="flex min-h-screen">
       <!-- Sidebar -->
       <aside class="w-64 bg-gray-900 text-white" v-if="uiStore.isSidebarOpen">
         <AdminSidebar />
       </aside>
       
       <!-- Main content -->
       <main class="flex-1 p-8">
         <slot />
       </main>
     </div>
   </template>

   <script setup>
   import { useUiStore } from '@/stores/ui'
   import AdminSidebar from '@/components/admin/AdminSidebar.vue'

   const uiStore = useUiStore()

   // Protect admin routes
   definePageMeta({
     middleware: 'auth'  // Will create in Phase C
   })
   </script>
   ```

3. Update pages to use specific layouts:
   ```vue
   <!-- pages/admin/index.vue -->
   <script setup>
   definePageMeta({
     layout: 'admin'
   })
   </script>
   ```

   ```vue
   <!-- pages/index.vue (and public pages) -->
   <script setup>
   definePageMeta({
     layout: 'default'
   })
   </script>
   ```

**Verification:**
- [ ] Public pages use `default` layout (navbar + footer visible)
- [ ] Admin pages use `admin` layout (sidebar visible)
- [ ] Layout switching works without page reload
- [ ] No style conflicts between layouts

**Commit:**
```bash
git add layouts/
git commit -m "migration: create page layouts (default, admin)"
```

---

## Phase C: Server Data Layer & Auth Middleware (2 days)

**Estimated time:** 6 hours  
**Goal:** Create server/api/ routes, implement auth middleware, proxy all backend calls

### C1: Create Server Utilities

**Files:**
- Create: `server/utils/api-client.ts`
- Create: `server/middleware/auth.ts`

**Steps:**

1. Create `server/utils/api-client.ts` - shared backend fetch logic:
   ```typescript
   // server/utils/api-client.ts
   export const createBackendFetch = (event: H3Event) => {
     const config = useRuntimeConfig()
     
     return async (endpoint: string, options: any = {}) => {
       // Get auth token from cookies
       const token = getCookie(event, 'auth_token')
       
       const headers = {
         'Content-Type': 'application/json',
         'Accept': 'application/json',
         ...options.headers
       }
       
       // Add auth token if exists
       if (token) {
         headers.Authorization = `Bearer ${token}`
       }
       
       try {
         return await $fetch(endpoint, {
           baseURL: config.apiBase,
           headers,
           ...options
         })
       } catch (error) {
         console.error(`[API Error] ${endpoint}:`, error)
         throw error
       }
     }
   }
   ```

2. Create `server/middleware/auth.ts` - auth check middleware:
   ```typescript
   // server/middleware/auth.ts
   export default defineEventHandler(async (event) => {
     const route = event.node.req.url

     // Skip auth for public routes
     const publicRoutes = ['/', '/about', '/projects', '/blog', '/awards', '/gallery', '/contact', '/api']
     const isPublic = publicRoutes.some(r => route?.startsWith(r))
     
     if (isPublic) return

     // Check if admin route
     const isAdminRoute = route?.startsWith('/admin')
     if (!isAdminRoute) return

     // Get token from cookies
     const token = getCookie(event, 'auth_token')
     
     if (!token) {
       // Redirect to login
       await sendRedirect(event, '/login', 302)
       return
     }

     // Verify token validity with backend
     try {
       const apiBase = useRuntimeConfig().apiBase
       await $fetch('/auth/me', {
         baseURL: apiBase,
         headers: {
           Authorization: `Bearer ${token}`
         }
       })
     } catch (error) {
       // Token invalid, clear and redirect
       deleteCookie(event, 'auth_token')
       await sendRedirect(event, '/login', 302)
     }
   })
   ```

**Verification:**
- [ ] `server/utils/api-client.ts` compiles without errors
- [ ] `server/middleware/auth.ts` compiles without errors
- [ ] Types are correctly inferred (tsc --noEmit passes)

**Commit:**
```bash
git add server/utils/ server/middleware/
git commit -m "feat: add server utilities (api-client, auth middleware)"
```

### C2: Create Server API Routes (Posts)

**Files:**
- Create: `server/api/posts.ts` (list posts)
- Create: `server/api/posts/[slug].ts` (single post)

**Steps:**

1. Create `server/api/posts.ts` - list endpoint:
   ```typescript
   // server/api/posts.ts
   export default defineEventHandler(async (event) => {
     const query = getQuery(event)
     const apiFetch = createBackendFetch(event)

     try {
       // Get language from header or query
       const lang = query.lang || event.node.req.headers['accept-language']?.slice(0, 2) || 'en'

       // Fetch from backend
       const response = await apiFetch('/posts', {
         query: {
           lang,
           page: query.page || 1,
           per_page: query.per_page || 15,
           search: query.search,
           category: query.category
         }
       })

       return response
     } catch (error) {
       console.error('Posts fetch error:', error)
       throw createError({
         statusCode: 500,
         statusMessage: 'Failed to fetch posts'
       })
     }
   })
   ```

2. Create `server/api/posts/[slug].ts` - single post endpoint:
   ```typescript
   // server/api/posts/[slug].ts
   export default defineEventHandler(async (event) => {
     const slug = getRouterParam(event, 'slug')
     const query = getQuery(event)
     const apiFetch = createBackendFetch(event)

     try {
       const lang = query.lang || 'en'

       // Fetch from backend
       const response = await apiFetch(`/posts/${slug}`, {
         query: { lang }
       })

       return response
     } catch (error) {
       if (error.statusCode === 404) {
         throw createError({
           statusCode: 404,
           statusMessage: 'Post not found'
         })
       }
       throw error
     }
   })
   ```

3. Test the routes:
   ```bash
   # Should return posts list
   curl http://localhost:3000/api/posts
   
   # Should return single post
   curl http://localhost:3000/api/posts/test-slug
   ```

**Verification:**
- [ ] `curl http://localhost:3000/api/posts` returns JSON from backend
- [ ] `curl http://localhost:3000/api/posts/test-slug` returns single post or 404
- [ ] Language param works: `?lang=id` returns Indonesian translation
- [ ] TypeScript compiles without errors

**Commit:**
```bash
git add server/api/posts.ts server/api/posts/
git commit -m "feat: add server routes for posts (list & detail)"
```

### C3: Create Server API Routes (Other Endpoints)

**Files:**
- Create: `server/api/projects.ts`, `server/api/projects/[slug].ts`
- Create: `server/api/categories.ts`
- Create: `server/api/awards.ts`
- Create: `server/api/galleries.ts`
- Create: `server/api/settings.ts`
- Create: `server/api/contact.post.ts`

**Steps:**

1. Create `server/api/projects.ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)
     const query = getQuery(event)

     const response = await apiFetch('/projects', {
       query: {
         page: query.page || 1,
         per_page: query.per_page || 100,
         search: query.search
       }
     })

     return response
   })
   ```

2. Create `server/api/projects/[slug].ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const slug = getRouterParam(event, 'slug')
     const apiFetch = createBackendFetch(event)

     const response = await apiFetch(`/projects/${slug}`)

     return response
   })
   ```

3. Create `server/api/categories.ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)

     return await apiFetch('/categories')
   })
   ```

4. Create `server/api/awards.ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)

     return await apiFetch('/awards')
   })
   ```

5. Create `server/api/galleries.ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)
     const query = getQuery(event)

     return await apiFetch('/galleries', { query })
   })
   ```

6. Create `server/api/settings.ts`:
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)
     const query = getQuery(event)
     const group = query.group || 'site'

     return await apiFetch(`/settings/${group}`)
   })
   ```

7. Create `server/api/contact.post.ts` (POST endpoint):
   ```typescript
   export default defineEventHandler(async (event) => {
     const apiFetch = createBackendFetch(event)
     const body = await readBody(event)

     try {
       const response = await apiFetch('/contact', {
         method: 'POST',
         body
       })

       return response
     } catch (error) {
       throw createError({
         statusCode: 422,
         statusMessage: 'Contact form submission failed'
       })
     }
   })
   ```

**Verification:**
- [ ] All server routes compile without errors
- [ ] Each route returns data from backend successfully
- [ ] Error handling works (404, 422, 500)
- [ ] POST endpoint accepts form data

**Commit:**
```bash
git add server/api/
git commit -m "feat: add all server API routes (projects, categories, awards, etc)"
```

---

## Phase D: Migrate Composables & Stores to TypeScript (2 days)

**Estimated time:** 6 hours  
**Goal:** Convert composables from .js to .ts, update Pinia stores, adapt for SSR

### D1: Migrate Pinia Stores to TypeScript

**Files:**
- Modify: `stores/auth.ts` (from auth.js)
- Modify: `stores/posts.ts` (from posts.js)
- Modify: `stores/ui.ts` (from ui.js)
- Modify: `stores/theme.ts` (from theme.js)
- etc. (12 more stores)

**Steps:**

1. Rename and update `stores/auth.ts`:
   ```typescript
   // stores/auth.ts
   import { defineStore } from 'pinia'
   import { ref, computed } from 'vue'

   export const useAuthStore = defineStore('auth', () => {
     const user = ref(null)
     const isLoading = ref(false)
     const error = ref(null)

     const isAuthenticated = computed(() => !!user.value)

     // Server-side login (called from server middleware)
     async function login(credentials: { email: string; password: string }) {
       isLoading.value = true
       error.value = null

       try {
         const response = await $fetch('/api/auth/login', {
           method: 'POST',
           body: credentials
         })

         user.value = response.data.user
         // Token is in httpOnly cookie (handled by server)

         return { success: true }
       } catch (err) {
         error.value = err.message
         return { success: false, error: error.value }
       } finally {
         isLoading.value = false
       }
     }

     async function logout() {
       try {
         await $fetch('/api/auth/logout', { method: 'POST' })
       } finally {
         user.value = null
       }
     }

     return {
       user,
       isLoading,
       error,
       isAuthenticated,
       login,
       logout
     }
   })
   ```

2. Update `stores/posts.ts`:
   ```typescript
   // stores/posts.ts
   import { defineStore } from 'pinia'
   import { ref, computed } from 'vue'

   export const usePostsStore = defineStore('posts', () => {
     const posts = ref([])
     const post = ref(null)
     const isLoading = ref(false)
     const error = ref(null)
     const pagination = ref({ currentPage: 1, total: 0, lastPage: 1 })

     // Fetch via server route (not direct API)
     const fetchPosts = async (params = {}) => {
       isLoading.value = true
       error.value = null

       try {
         const response = await $fetch('/api/posts', {
           query: params
         })

         posts.value = response.data
         pagination.value = response.meta

         return { success: true }
       } catch (err) {
         error.value = err.message
         return { success: false, error: error.value }
       } finally {
         isLoading.value = false
       }
     }

     const fetchPost = async (slug: string) => {
       isLoading.value = true

       try {
         const response = await $fetch(`/api/posts/${slug}`)
         post.value = response.data

         return { success: true }
       } catch (err) {
         error.value = err.message
         return { success: false }
       } finally {
         isLoading.value = false
       }
     }

     return {
       posts,
       post,
       isLoading,
       error,
       pagination,
       fetchPosts,
       fetchPost
     }
   })
   ```

3. Repeat for remaining stores (ui.ts, theme.ts, projects.ts, categories.ts, etc.)
   - Keep the same logic
   - Update API calls to use `/api/posts` instead of direct backend URLs
   - Add type annotations

**Verification:**
- [ ] All stores compile without TypeScript errors
- [ ] Store functions return correct types
- [ ] Pinia store integration test passes (can create/destroy store)

**Commit:**
```bash
git add stores/
git commit -m "migration: convert stores from .js to .ts with proper types"
```

### D2: Migrate Composables to TypeScript

**Files:**
- Modify: `composables/usePosts.ts` (from usePosts.js)
- Modify: `composables/useProjects.ts` (from useProjects.js)
- Modify: `composables/useApi.ts` (from useApi.js)
- etc. (17 more composables)

**Steps:**

1. Migrate `composables/usePosts.ts`:
   ```typescript
   // composables/usePosts.ts
   import { ref } from 'vue'
   import { usePostsStore } from '@/stores/posts'

   export function usePosts() {
     const store = usePostsStore()

     const posts = computed(() => store.posts)
     const post = computed(() => store.post)
     const isLoading = computed(() => store.isLoading)

     const fetchPosts = (params = {}) => store.fetchPosts(params)
     const fetchPost = (slug: string) => store.fetchPost(slug)

     return {
       posts,
       post,
       isLoading,
       fetchPosts,
       fetchPost
     }
   }
   ```

2. Migrate `composables/useApi.ts` - **CRITICAL CHANGE**:
   ```typescript
   // composables/useApi.ts
   // NEW: This now calls Nuxt server routes, NOT backend directly

   export function useApi() {
     // useAsyncData ensures server/client hydration sync
     const { data, pending, error } = await useAsyncData(
       'api-' + Math.random(),
       () => $fetch('/api/posts')  // Calls server route
     )

     return { data, pending, error }
   }
   ```

3. Add SSR-safe composables using `useAsyncData()`:
   ```typescript
   // composables/useBlogData.ts (NEW)
   export function useBlogData(slug?: string) {
     return useAsyncData(
       `blog-${slug}`,
       () => slug 
         ? $fetch(`/api/posts/${slug}`)
         : $fetch('/api/posts')
     )
   }
   ```

4. Migrate remaining composables (useProjects, useAwards, useGallery, etc.)
   - Replace direct API calls with `$fetch('/api/...')`
   - Use `useAsyncData()` for server-sync data
   - Add type annotations

**Verification:**
- [ ] All composables compile without TypeScript errors
- [ ] useAsyncData properly hydrates server→client
- [ ] Composables work in both server and client context

**Commit:**
```bash
git add composables/
git commit -m "migration: convert composables to TypeScript with SSR support"
```

---

## Phase E: Implement Page Components with SSR (2 days)

**Estimated time:** 6 hours  
**Goal:** Update page components to fetch data server-side, render full HTML

### E1: Update Blog List Page

**File:** `pages/blog/index.vue`

**Steps:**

1. Update blog list to use SSR data fetch:
   ```vue
   <template>
     <div>
       <h1>Blog Posts</h1>
       <div v-if="pending" class="text-center py-12">Loading...</div>
       <div v-else-if="error" class="text-red-600">Error: {{ error.message }}</div>
       <div v-else class="grid gap-6">
         <BlogCard 
           v-for="post in data?.data" 
           :key="post.id" 
           :post="post"
         />
       </div>
       <!-- Pagination -->
       <div class="mt-8">
         <button 
           v-if="data?.meta.current_page > 1"
           @click="currentPage--"
         >
           Previous
         </button>
         <button 
           v-if="data?.meta.current_page < data?.meta.last_page"
           @click="currentPage++"
         >
           Next
         </button>
       </div>
     </div>
   </template>

   <script setup lang="ts">
   const currentPage = ref(1)

   // SSR: Fetch on server
   const { data, pending, error } = await useAsyncData(
     'blog-list',
     () => $fetch('/api/posts', {
       query: { page: currentPage.value }
     }),
     { watch: [currentPage] }
   )

   // Set page meta for SEO
   useHead({
     title: 'Blog - Ali Sadikin Ma',
     meta: [
       { name: 'description', content: 'Latest blog posts on AI, automation, and web development' }
     ]
   })
   </script>
   ```

**Verification:**
- [ ] Blog list page renders on server with all posts in HTML
- [ ] Pagination works (query param changes trigger refetch)
- [ ] No 404 errors in browser console
- [ ] Page HTML includes full post content (inspect source)

**Commit:**
```bash
git add pages/blog/index.vue
git commit -m "feat: update blog list with SSR data fetching"
```

### E2: Update Blog Detail Page (Dynamic Route)

**File:** `pages/blog/[slug].vue`

**Steps:**

1. Update blog detail for SSR + ISR:
   ```vue
   <template>
     <article v-if="data" class="max-w-4xl mx-auto">
       <h1>{{ data.data.title }}</h1>
       <time :datetime="data.data.published_at">
         {{ formatDate(data.data.published_at) }}
       </time>
       <div class="prose" v-html="data.data.content"></div>
       
       <!-- Related posts -->
       <div v-if="relatedPosts" class="mt-12">
         <h2>Related Posts</h2>
         <div class="grid gap-4">
           <BlogCard 
             v-for="post in relatedPosts" 
             :key="post.id" 
             :post="post"
           />
         </div>
       </div>
     </article>
     <div v-else class="text-red-600">Post not found</div>
   </template>

   <script setup lang="ts">
   import { useRoute } from 'vue-router'

   const route = useRoute()
   const slug = route.params.slug

   // SSR: Fetch on server, then cache with ISR
   const { data, error } = await useAsyncData(
     `blog-${slug}`,
     () => $fetch(`/api/posts/${slug}`)
   )

   // Client-side: fetch related posts
   const relatedPosts = ref([])
   onMounted(async () => {
     if (data.value?.data.related_post_ids) {
       relatedPosts.value = await $fetch('/api/posts', {
         query: { ids: data.value.data.related_post_ids.join(',') }
       })
     }
   })

   // JSON-LD for blog post
   useHead({
     script: [{
       type: 'application/ld+json',
       innerHTML: JSON.stringify({
         "@context": "https://schema.org",
         "@type": "BlogPosting",
         "headline": data.value?.data.title,
         "content": data.value?.data.content,
         "author": { "@type": "Person", "name": "Ali Sadikin" },
         "datePublished": data.value?.data.published_at
       })
     }],
     title: data.value?.data.meta_title || data.value?.data.title,
     meta: [
       { name: 'description', content: data.value?.data.meta_description },
       { property: 'og:title', content: data.value?.data.og_title },
       { property: 'og:image', content: data.value?.data.og_image }
     ]
   })
   </script>
   ```

**Verification:**
- [ ] Blog detail page renders on server with full content
- [ ] Dynamic route works: `/blog/test-slug` loads correct post
- [ ] 404 handling works for non-existent posts
- [ ] JSON-LD schema injected in page head
- [ ] Meta tags (og:title, og:image) present in HTML

**Commit:**
```bash
git add pages/blog/[slug].vue
git commit -m "feat: update blog detail page with SSR + ISR caching"
```

### E3: Update Project Pages (Same Pattern)

**Files:** `pages/projects/index.vue`, `pages/projects/[slug].vue`

**Steps:**
1. Apply same SSR pattern to projects index
2. Apply same SSR pattern to project detail
3. Include JSON-LD for CreativeWork schema

**Verification:**
- [ ] Projects list renders on server
- [ ] Project detail SSR works with full content
- [ ] CreativeWork JSON-LD schema present

**Commit:**
```bash
git add pages/projects/
git commit -m "feat: update project pages with SSR rendering"
```

### E4: Update Static Pages (Home, About, Awards, Gallery)

**Files:** `pages/index.vue`, `pages/about.vue`, `pages/awards.vue`, `pages/gallery.vue`

**Steps:**
1. Add SSR data fetching for each:
   ```vue
   <script setup>
   // Home page: fetch site settings + featured content
   const { data: settings } = await useAsyncData('site-settings', () => $fetch('/api/settings'))
   const { data: featured } = await useAsyncData('featured-projects', () => $fetch('/api/projects?featured=true'))
   </script>
   ```

2. Add useHead() for SEO meta tags

**Verification:**
- [ ] All static pages render full HTML on server
- [ ] No missing data (settings, featured content)
- [ ] Meta tags present in page source

**Commit:**
```bash
git add pages/index.vue pages/about.vue pages/awards.vue pages/gallery.vue
git commit -m "feat: add SSR rendering to static pages"
```

---

## Phase F: Client-Side Hydration & Interactivity (1 day)

**Estimated time:** 4 hours  
**Goal:** Hydrate client state (filters, favorites, theme), ensure smooth interactivity

### F1: Implement Client-Side State Hydration

**File:** `composables/useClientState.ts`

**Steps:**

1. Create hydration composable:
   ```typescript
   // composables/useClientState.ts
   export function useClientState() {
     // This runs ONLY on client, after SSR hydration
     return {
       hydrate: () => {
         // 1. Restore favorites from localStorage
         const favorites = useState('favorites', 
           () => JSON.parse(localStorage.getItem('favorites') || '[]')
         )

         // 2. Restore theme from localStorage
         const isDark = useState('isDark',
           () => localStorage.getItem('theme') === 'dark'
         )

         // 3. Restore language
         const language = useState('language',
           () => localStorage.getItem('language') || 'en'
         )

         // Watch changes and persist
         watch([favorites, isDark, language], ([fav, dark, lang]) => {
           localStorage.setItem('favorites', JSON.stringify(fav))
           localStorage.setItem('theme', dark ? 'dark' : 'light')
           localStorage.setItem('language', lang)
         })

         return { favorites, isDark, language }
       }
     }
   }
   ```

2. Call hydration in `app.vue`:
   ```vue
   <script setup>
   const { hydrate } = useClientState()

   onMounted(() => {
     hydrate()
   })
   </script>
   ```

**Verification:**
- [ ] Client state persists after page reload
- [ ] Favorites load from localStorage after SSR
- [ ] Theme preference preserved

**Commit:**
```bash
git add composables/useClientState.ts
git commit -m "feat: implement client-side state hydration"
```

### F2: Add Client-Side Filters & Search

**File:** `pages/blog/index.vue` (updated)

**Steps:**

1. Add client-side search filtering:
   ```vue
   <template>
     <div>
       <!-- Search input (client-only) -->
       <input 
         v-model="searchQuery"
         type="search"
         placeholder="Search posts..."
         class="w-full px-4 py-2 mb-6"
       />

       <!-- Filtered posts -->
       <div class="grid gap-6">
         <BlogCard 
           v-for="post in filteredPosts" 
           :key="post.id" 
           :post="post"
         />
       </div>
     </div>
   </template>

   <script setup>
   const searchQuery = ref('')

   const { data } = await useAsyncData('blog-list', () => $fetch('/api/posts'))

   // Client-side filter (no server call)
   const filteredPosts = computed(() => {
     return data.value?.data.filter(post =>
       post.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
       post.excerpt.toLowerCase().includes(searchQuery.value.toLowerCase())
     ) || []
   })
   </script>
   ```

**Verification:**
- [ ] Search input filters posts client-side
- [ ] No extra server calls during search
- [ ] Filtering works after hydration

**Commit:**
```bash
git add pages/blog/index.vue
git commit -m "feat: add client-side search filtering"
```

---

## Phase G: LLM Integration & Optimization (1 day)

**Estimated time:** 4 hours  
**Goal:** Generate ChatGPT plugin JSON, test LLM crawling, ensure full indexability

### G1: Generate ChatGPT Plugin & OpenAPI Spec

**Files:**
- Create: `.well-known/ai-plugin.json`
- Create: `.well-known/openapi.json`

**Steps:**

1. Create `public/.well-known/ai-plugin.json`:
   ```json
   {
     "schema_version": "v1",
     "name_for_human": "Ali Sadikin's Portfolio",
     "name_for_model": "ali_sadikin_portfolio",
     "description_for_human": "Search and explore Ali Sadikin's portfolio, projects, and blog posts. Get insights into AI automation, generative AI systems, and full-stack development.",
     "description_for_model": "Allows users to search through Ali Sadikin Ma's portfolio including blog posts, projects, and expertise areas.",
     "auth": {
       "type": "none"
     },
     "api": {
       "type": "openapi",
       "url": "https://alisadikinma.com/.well-known/openapi.json"
     },
     "logo_url": "https://alisadikinma.com/logo.png",
     "contact_email": "ali.sadikincom85@gmail.com",
     "legal_info_url": "https://alisadikinma.com"
   }
   ```

2. Create `public/.well-known/openapi.json`:
   ```json
   {
     "openapi": "3.0.0",
     "info": {
       "title": "Ali Sadikin's Portfolio API",
       "description": "API for searching portfolio, projects, and blog content",
       "version": "1.0.0"
     },
     "servers": [
       {
         "url": "https://alisadikinma.com/api"
       }
     ],
     "paths": {
       "/posts": {
         "get": {
           "summary": "Search blog posts",
           "parameters": [
             {
               "name": "search",
               "in": "query",
               "description": "Search term",
               "schema": { "type": "string" }
             },
             {
               "name": "page",
               "in": "query",
               "schema": { "type": "integer" }
             }
           ],
           "responses": {
             "200": {
               "description": "List of blog posts"
             }
           }
         }
       },
       "/projects": {
         "get": {
           "summary": "Search projects",
           "parameters": [
             {
               "name": "search",
               "in": "query",
               "schema": { "type": "string" }
             }
           ],
           "responses": {
             "200": {
               "description": "List of projects"
             }
           }
         }
       }
     }
   }
   ```

**Verification:**
- [ ] Files created in `public/.well-known/`
- [ ] JSON is valid (parse check)
- [ ] URLs are production URLs (https://alisadikinma.com)

**Commit:**
```bash
git add public/.well-known/
git commit -m "feat: add ChatGPT plugin & OpenAPI spec"
```

### G2: Verify LLM Crawlability

**Steps:**

1. Build project:
   ```bash
   npm run build
   ```

2. Test SSR output with curl:
   ```bash
   # Test home page (should include full HTML)
   curl http://localhost:3000/ | grep -i "<article>"

   # Test blog post (should include full content)
   curl http://localhost:3000/blog/test-slug | head -200

   # Test that robots.txt allows crawling
   curl http://localhost:3000/robots.txt
   ```

3. Verify JSON-LD schemas:
   ```bash
   # Should include BlogPosting schema
   curl http://localhost:3000/blog/test-slug | grep -A 10 "application/ld+json"
   ```

4. Test OpenAPI spec:
   ```bash
   curl http://localhost:3000/.well-known/openapi.json | jq .
   ```

**Verification:**
- [ ] Full HTML content in curl output (no `<div id="app"></div>`)
- [ ] JSON-LD schemas present and valid
- [ ] No JavaScript required to read content
- [ ] OpenAPI spec is valid JSON

**Commit:**
```bash
git add .
git commit -m "test: verify LLM crawlability and SSR output"
```

---

## Phase H: Testing & Quality Assurance (1 day)

**Estimated time:** 4 hours  
**Goal:** Run tests, verify no regressions, ensure all routes work

### H1: Run Test Suite

**Steps:**

1. Create test for SSR rendering:
   ```typescript
   // tests/ssr.test.ts
   import { describe, it, expect } from 'vitest'

   describe('SSR Rendering', () => {
     it('renders home page on server', async () => {
       const html = await $fetch('/')
       expect(html).toContain('<h1')
       expect(html).not.toContain('<div id="app">')
     })

     it('renders blog post with content', async () => {
       const html = await $fetch('/blog/test-slug')
       expect(html).toContain('<article>')
       expect(html).toContain('content')
     })

     it('includes JSON-LD schema', async () => {
       const html = await $fetch('/blog/test-slug')
       expect(html).toContain('application/ld+json')
       expect(html).toContain('BlogPosting')
     })
   })
   ```

2. Run tests:
   ```bash
   npm run test
   ```

3. Check TypeScript:
   ```bash
   tsc --noEmit
   ```

**Verification:**
- [ ] All tests pass
- [ ] No TypeScript errors
- [ ] No console errors in dev server

**Commit:**
```bash
git add tests/
git commit -m "test: add SSR rendering tests"
```

### H2: Performance Audit

**Steps:**

1. Measure First Byte Time (TTFB):
   ```bash
   time curl -w "%{time_starttransfer}s" http://localhost:3000/blog/test-slug
   # Should be < 500ms
   ```

2. Check build output size:
   ```bash
   npm run build
   # Output should show .dist/ size
   ```

3. Verify no N+1 queries:
   - Check server logs for duplicate backend API calls
   - Each route should call backend once per parameter

**Verification:**
- [ ] TTFB < 500ms on local machine
- [ ] Build completes without errors
- [ ] No duplicate API calls in logs

**Commit:**
```bash
git add .
git commit -m "perf: measure and document performance metrics"
```

---

## Phase I: Production Deployment (1 day)

**Estimated time:** 3 hours  
**Goal:** Deploy to production, verify LLM discoverability

### I1: Deploy to Production

**Steps:**

1. Update environment variables for production:
   ```bash
   # .env.production
   NUXT_PUBLIC_API_BASE=https://alisadikinma.com/api
   ```

2. Build for production:
   ```bash
   npm run build
   ```

3. Copy build output to XAMPP (or your host):
   ```bash
   cp -r .output/public/* D:\xampp\htdocs\Portfolio_v2\frontend\
   ```

4. Verify production URLs:
   ```bash
   curl https://alisadikinma.com/
   curl https://alisadikinma.com/api/posts
   curl https://alisadikinma.com/.well-known/ai-plugin.json
   ```

**Verification:**
- [ ] Production URLs load without errors
- [ ] HTTPS certificates valid
- [ ] API calls return real data
- [ ] Plugin JSON accessible

**Commit:**
```bash
git add .env.production
git commit -m "deploy: prepare production build & environment"
```

### I2: Test LLM Discoverability

**Steps:**

1. Test with Claude API:
   ```
   "Search for information about Ali Sadikin Ma's portfolio at alisadikinma.com"
   ```

2. Check indexing in ChatGPT:
   - Go to https://chat.openai.com
   - Install plugin from .well-known/ai-plugin.json
   - Test: "Show me Ali Sadikin's projects"

3. Verify crawl metrics:
   ```bash
   # Check server logs for crawler requests from GPTBot, ClaudeBot
   grep -i "gptbot\|claudebot\|perplexity" /var/log/apache2/access.log
   ```

**Verification:**
- [ ] ChatGPT can search portfolio
- [ ] Gemini can access blog posts
- [ ] Claude API retrieves content
- [ ] Crawler logs show bot visits

**Commit:**
```bash
git add .
git commit -m "deploy: verify LLM plugin integration"
```

---

## Summary & Rollback Plan

### What Gets Changed

| Item | Before | After |
|------|--------|-------|
| Frontend Framework | Vue 3 SPA | Nuxt 3 SSR |
| Routing | Vue Router | Nuxt file-based |
| Auth | localStorage tokens | HttpOnly cookies |
| API Calls | Client-side axios | Server middleware $fetch |
| Build Tool | Vite | Nuxt (Rolldown) |
| Content Rendering | JS hydration required | SSR HTML (no JS needed) |
| LLM Discoverability | Score 7.5/10 | Score 9.5/10 |

### What Stays the Same

- ✅ Laravel backend (unchanged)
- ✅ Database schema (unchanged)
- ✅ Pinia stores (95% compatible)
- ✅ Composables (adapted, logic preserved)
- ✅ Components (Vue remains Vue)
- ✅ Tailwind CSS
- ✅ Admin functionality

### Rollback Procedure

If something goes wrong:

```bash
# Restore from git
git reset --hard origin/main

# Or restore backup
cp package.json.backup package.json
cp vite.config.ts.backup vite.config.ts

# Clear node_modules
rm -rf node_modules package-lock.json

# Reinstall Vue 3 dependencies
npm install

# Restart dev server
npm run dev
```

---

## Execution Instructions

**Ready to implement?**

1. **Option A: Sequential execution**  
   - Use `gaspol-execute` to run phases A-I in sequence
   - ~6 hours per week, 5-6 weeks total

2. **Option B: Parallel execution**  
   - Phases A-C can run in parallel with D-E
   - Reduces timeline to 3-4 weeks

3. **Option C: Save for next session**  
   - Keep this plan file
   - Next session: load `docs/plans/2026-04-11-nuxt3-ssr-implementation-plan.md`
   - Resume from Phase A

---

**Plan created by:** Claude Code + gaspol-plan  
**Plan saved:** `docs/plans/2026-04-11-nuxt3-ssr-implementation-plan.md`  
**Last updated:** April 11, 2026  
**Phases:** 9 (A-I)  
**Estimated tasks:** 45+  
**Timeline:** 5-6 weeks
