<template>
  <div class="automation-docs-page">
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
        API Documentation
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Reference for the two API surfaces. Switch tabs to flip between specs, then export the active surface as a markdown document you can hand to another agent.
      </p>
    </div>

    <!-- Top bar: Tabs + Export actions ----------------------------------- -->
    <div class="mb-6 flex flex-col gap-3 border-b border-gray-200 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
      <nav class="-mb-px flex gap-6" aria-label="API surface tabs">
        <button
          v-for="t in tabs"
          :key="t.slug"
          @click="setTab(t.slug)"
          :class="[
            'inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition-colors',
            activeTab === t.slug
              ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
          ]"
        >
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
            :class="t.slug === 'cv' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'"
          >
            {{ t.badge }}
          </span>
          <span>{{ t.label }}</span>
        </button>
      </nav>

      <div class="flex items-center gap-2 pb-3 sm:pb-0">
        <button
          @click="copyActiveDocs"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
          :title="'Copy ' + activeTabConfig.label + ' docs as markdown to share with another agent'"
        >
          <svg v-if="!justCopied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <svg v-else class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span :class="justCopied ? 'text-emerald-600 dark:text-emerald-400' : ''">
            {{ justCopied ? 'Copied' : 'Copy as Markdown' }}
          </span>
        </button>
        <button
          @click="downloadActiveDocs"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700"
          :title="'Download ' + activeTabConfig.label + ' docs as .md file'"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Download .md
        </button>
      </div>
    </div>

    <!-- ============================================================= -->
    <!-- CV API tab ================================================== -->
    <!-- ============================================================= -->
    <div v-if="activeTab === 'cv'">
      <!-- Quick Start -->
      <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950">
        <h2 class="text-lg font-semibold text-emerald-900 dark:text-emerald-100">CV API — Quick Start</h2>
        <ol class="mt-4 space-y-2 text-sm text-emerald-800 dark:text-emerald-200">
          <li>1. Open the <router-link to="/admin/automation/tokens" class="underline">Tokens page</router-link> and click <strong>Create Token</strong>.</li>
          <li>2. Pick category <code class="rounded bg-emerald-100 px-1.5 dark:bg-emerald-900">cv</code> — token name will auto-prefix with <code>cv-</code>.</li>
          <li>3. Tick the <code>cv:read</code> ability checkbox.</li>
          <li>4. Copy the plaintext (only shown once at creation).</li>
          <li>5. Send as <code class="rounded bg-emerald-100 px-1.5 dark:bg-emerald-900">Authorization: Bearer &lt;token&gt;</code> header.</li>
        </ol>
      </div>

      <!-- Authentication (CV-specific) -->
      <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Authentication</h2>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>Authorization: Bearer YOUR_CV_TOKEN
Accept: text/markdown, application/json</code></pre>
        <ul class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
          <li><strong>Prefix:</strong> <code>cv-</code></li>
          <li><strong>Ability:</strong> <code>cv:read</code></li>
          <li><strong>Rate limit:</strong> 30 requests / minute</li>
          <li><strong>Cross-surface usage</strong> (e.g. <code>post:write</code> on a CV token) → <code>403 Forbidden</code></li>
          <li><strong>Missing/invalid token</strong> → <code>401 UNAUTHENTICATED</code> as JSON when <code>Accept: application/json</code> is set or path matches <code>/api/*</code></li>
        </ul>
      </div>

      <!-- CV Master Markdown -->
      <div class="mb-6 rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-3">
          <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/cv/master.md</h3>
          <span class="text-xs text-gray-500 dark:text-gray-400">Default · LLM-optimized markdown</span>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Single dense markdown document for direct LLM prompt embedding. English only with silent Indonesian fallback. Sections: Identity → Summary → Skills Matrix → Selected Projects → Awards & Recognition → Thought Leadership.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
            <p class="text-[11px] font-mono uppercase tracking-wider text-gray-500 dark:text-gray-400">Default size</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">~10k tokens</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Full Problem + Outcome per project</p>
          </div>
          <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
            <p class="text-[11px] font-mono uppercase tracking-wider text-gray-500 dark:text-gray-400">Compact (?compact=1)</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">~5k tokens</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">40%+ smaller — drops Problem/Outcome</p>
          </div>
          <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
            <p class="text-[11px] font-mono uppercase tracking-wider text-gray-500 dark:text-gray-400">Content-Type</p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">text/markdown</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">UTF-8 charset</p>
          </div>
        </div>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ cvMasterExample }}</code></pre>
      </div>

      <!-- CV Master Compact -->
      <div class="mb-6 rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-3">
          <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/cv/master.md?compact=1</h3>
          <span class="text-xs text-gray-500 dark:text-gray-400">Compact variant · ~5k tokens</span>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Same shape as default but with Problem/Outcome lines removed. Use when prompt-budget-constrained (e.g. job-score scanning many roles in parallel).
        </p>
        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ cvCompactExample }}</code></pre>
      </div>

      <!-- CV JSON Resume -->
      <div class="mb-6 rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-3">
          <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/cv/export</h3>
          <span class="text-xs text-gray-500 dark:text-gray-400">Structured · JSON Resume v1.0.0</span>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          JSON Resume schema envelope. Use when the consumer parses fields programmatically (ATS, scoring engines, structured filtering). Each project carries a <code>relevance_hint[]</code> array (e.g. <code>["ai_automation", "ai_agents"]</code>) so jobhunter can filter/rank without re-parsing prose.
        </p>

        <div class="mt-4">
          <h4 class="font-medium text-gray-900 dark:text-white">Response shape</h4>
          <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li><code class="text-xs">basics</code> — name, title, summary, contact, social_links</li>
            <li><code class="text-xs">work</code> — Selected Projects (~56 entries by default) with role, year_range, industry, tech_stack, problem, outcome, relevance_hint</li>
            <li><code class="text-xs">awards</code> — featured-first ordering</li>
            <li><code class="text-xs">publications</code> — top 5 published thought-leadership posts</li>
          </ul>
        </div>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ cvExportExample }}</code></pre>
      </div>

      <!-- ETag / Caching -->
      <div class="mb-6 rounded-lg border border-cyan-200 bg-cyan-50 p-6 dark:border-cyan-900 dark:bg-cyan-950">
        <h3 class="text-base font-semibold text-cyan-900 dark:text-cyan-100">ETag revalidation (highly recommended)</h3>
        <p class="mt-2 text-sm text-cyan-800 dark:text-cyan-200">
          All CV endpoints emit a weak <code>ETag</code> on every 2xx JSON/markdown response. Send the previous value back as <code>If-None-Match</code> on the next request — if nothing changed, you'll get a <strong>304 Not Modified</strong> with empty body (~80 bytes total round-trip vs ~10k-50k for full payload).
        </p>
        <p class="mt-2 text-xs text-cyan-700 dark:text-cyan-300">
          jobhunter integrations should cache the body+ETag per token, then revalidate at the start of each agent run. Saves bandwidth and keeps the rate-limit budget for actual misses.
        </p>
        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ cvEtagExample }}</code></pre>
      </div>

      <!-- jobhunter integration recipe -->
      <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">jobhunter Agent integration</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Recommended pattern for the jobhunter platform:
        </p>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
          <li>Mint a single <code>cv-jobhunter-export</code> token in the Tokens page (category <code>cv</code>, ability <code>cv:read</code>).</li>
          <li>Store the plaintext in jobhunter's secrets manager — it's the only time it's visible.</li>
          <li>For <strong>cv-tailor</strong> / <strong>cold-email</strong> / <strong>job-score</strong> skills, fetch <code>/api/cv/master.md</code> with ETag revalidation. The markdown drops straight into the LLM prompt.</li>
          <li>For ATS-style structured matching, use <code>/api/cv/export</code> instead — <code>relevance_hint[]</code> + <code>tech_stack[]</code> let you score without re-parsing prose.</li>
          <li>If the token is ever lost, click <strong>Regenerate</strong> in the Tokens page (revokes old + mints fresh with same name + abilities).</li>
        </ol>

        <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ jobhunterRecipe }}</code></pre>
      </div>
    </div>

    <!-- ============================================================= -->
    <!-- Automation API tab ========================================== -->
    <!-- ============================================================= -->
    <div v-if="activeTab === 'automation'">
      <!-- Quick Start -->
      <div class="mb-6 rounded-lg border border-purple-200 bg-purple-50 p-6 dark:border-purple-900 dark:bg-purple-950">
        <h2 class="text-lg font-semibold text-purple-900 dark:text-purple-100">Automation API — Quick Start</h2>
        <ol class="mt-4 space-y-2 text-sm text-purple-800 dark:text-purple-200">
          <li>1. Open the <router-link to="/admin/automation/tokens" class="underline">Tokens page</router-link> and click <strong>Create Token</strong>.</li>
          <li>2. Pick category <code class="rounded bg-purple-100 px-1.5 dark:bg-purple-900">automation</code> — token name will auto-prefix with <code>api-</code>.</li>
          <li>3. Pick the abilities you need (<code>post:read</code>, <code>post:write</code>, <code>post:delete</code>, <code>category:read</code>).</li>
          <li>4. Copy the plaintext (only shown once at creation).</li>
          <li>5. Send as <code class="rounded bg-purple-100 px-1.5 dark:bg-purple-900">Authorization: Bearer &lt;token&gt;</code> header in n8n / Zapier / Make.com.</li>
        </ol>
      </div>

      <!-- Authentication (Automation-specific) -->
      <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Authentication</h2>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
Accept: application/json</code></pre>
        <ul class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
          <li><strong>Prefix:</strong> <code>api-</code></li>
          <li><strong>Abilities:</strong> <code>post:read</code>, <code>post:write</code>, <code>post:delete</code>, <code>category:read</code></li>
          <li><strong>Rate limit:</strong> 60 requests / minute</li>
          <li><strong>Per-endpoint scoping:</strong> only abilities checked at the controller level enforce — e.g. POST /posts requires <code>post:write</code></li>
        </ul>
      </div>

      <!-- Endpoints -->
      <div class="space-y-6">
        <!-- Get Posts -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get list of posts with filters</p>

          <div class="mt-4">
            <h4 class="font-medium text-gray-900 dark:text-white">Query Parameters:</h4>
            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <li><code class="text-xs">published</code> - true/false</li>
              <li><code class="text-xs">category_id</code> - integer</li>
              <li><code class="text-xs">search</code> - string</li>
              <li><code class="text-xs">per_page</code> - integer (max 100)</li>
              <li><code class="text-xs">page</code> - integer</li>
            </ul>
          </div>

          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ getPostsExample }}</code></pre>
        </div>

        <!-- Create Post -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">POST</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create a new post (simplified validation)</p>

          <div class="mt-4">
            <h4 class="font-medium text-gray-900 dark:text-white">Required Fields:</h4>
            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <li><code class="text-xs">title</code> - string (max 255)</li>
              <li><code class="text-xs">content</code> - string</li>
              <li><code class="text-xs">category_id</code> - integer</li>
            </ul>

            <h4 class="mt-4 font-medium text-gray-900 dark:text-white">Optional Fields:</h4>
            <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
              <li><code class="text-xs">slug</code> - string (auto-generated if not provided)</li>
              <li><code class="text-xs">excerpt</code> - string (auto-generated if not provided)</li>
              <li><code class="text-xs">featured_image</code> - string (URL or base64)</li>
              <li><code class="text-xs">tags</code> - array of strings</li>
              <li><code class="text-xs">published</code> - boolean (default: false)</li>
              <li><code class="text-xs">published_at</code> - datetime (auto-set if published)</li>
            </ul>
          </div>

          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ createPostExample }}</code></pre>
        </div>

        <!-- Bulk Create -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">POST</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/bulk</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create multiple posts at once (up to 50)</p>
          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ bulkCreateExample }}</code></pre>
        </div>

        <!-- Update Post -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">PUT</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/:id</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Update an existing post</p>
          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ updatePostExample }}</code></pre>
        </div>

        <!-- Delete Post -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-200">DELETE</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/posts/:id</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Delete a post</p>
          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ deletePostExample }}</code></pre>
        </div>

        <!-- Get Categories -->
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center space-x-3">
            <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">GET</span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">/api/automation/categories</h3>
          </div>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get all blog categories</p>
          <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-green-400"><code>{{ getCategoriesExample }}</code></pre>
        </div>
      </div>

      <!-- n8n Workflow Templates -->
      <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">n8n Workflow Templates</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Common workflow patterns for n8n automation platform
        </p>
        <div class="mt-6 space-y-4">
          <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
            <h3 class="font-semibold text-gray-900 dark:text-white">1. RSS Feed to Blog</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Trigger: RSS Feed Read → HTTP Request (POST /automation/posts)
            </p>
          </div>
          <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
            <h3 class="font-semibold text-gray-900 dark:text-white">2. Email to Draft Post</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Trigger: Gmail → Parse Email → HTTP Request (status: draft)
            </p>
          </div>
          <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
            <h3 class="font-semibold text-gray-900 dark:text-white">3. AI Content Generation</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Trigger: Schedule → OpenAI → HTTP Request (create post)
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useToast } from '@/stores/ui'

const toast = useToast()

// --- Tab state ---------------------------------------------------------------
// Persist active tab so the operator returning from /tokens lands on the
// surface they were last reading. Default to CV since it's the most recently
// added (and the typical share-target for a jobhunter agent integration).
const TAB_KEY = 'admin:docs:tab'
const activeTab = ref(sessionStorage.getItem(TAB_KEY) || 'cv')
watch(activeTab, (v) => sessionStorage.setItem(TAB_KEY, v))

const tabs = [
  { slug: 'cv',         label: 'CV API',         badge: 'CV' },
  { slug: 'automation', label: 'Automation API', badge: 'AUTO' },
]
const activeTabConfig = computed(() => tabs.find((t) => t.slug === activeTab.value) || tabs[0])

function setTab(slug) {
  activeTab.value = slug
  // Scroll to top so the operator sees the tab's quick-start, not a stale
  // mid-page scroll position from the previous tab.
  if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' })
}

// --- Base URL ---------------------------------------------------------------
// Resolve API base URL from the actual origin so docs render production URLs
// on alisadikinma.com and dev URLs on localhost. Vite dev runs on :5173 but
// the API itself lives on XAMPP Apache at /Portfolio_v2/ — special-case that
// pair so dev examples remain copy-pasteable.
const baseUrl = (() => {
  if (typeof window === 'undefined') return '/api'
  const origin = window.location.origin
  if (origin.includes('localhost:5173')) {
    return 'http://localhost/Portfolio_v2/backend/public/api'
  }
  return `${origin}/api`
})()

// --- CV API rendered examples (used in the visual cards) -------------------
const cvMasterExample = `# bash / curl
curl -H "Authorization: Bearer cv-jobhunter-export-PLAIN-TEXT" \\
     -H "Accept: text/markdown" \\
     ${baseUrl}/cv/master.md

# Response (HTTP 200, ~10k tokens of dense markdown)
# Identity & Contact
**Ali Sadikin** — AI Generalist Expert · Indonesia

## Summary
...

## Skills Matrix
| Domain | Projects | Highlights |
| --- | --- | --- |
| AI Automation | 12 | n8n / Zapier / Make.com pipelines |
| Vibe Coding   |  8 | Cursor + Claude Code + spec-driven |
...

## Selected Projects (sorted by sort_order)
### Project Name — 2024-2025
- **Role:** Lead AI Engineer
- **Industry:** Manufacturing
- **Tech Stack:** Python, LangChain, n8n
- **Problem:** ... (omitted in compact mode)
- **Outcome:** ... (omitted in compact mode)
- **Relevance:** ai_automation, manufacturing
...

## Awards & Recognition
...

## Thought Leadership
1. [Article title](https://alisadikinma.com/blog/slug) — published 2026-04-...
...

Generated 2026-05-06 · ${baseUrl.replace('/api','')}/api/cv/master.md`

const cvCompactExample = `# Same shape, ~5k tokens (Problem + Outcome dropped per project)
curl -H "Authorization: Bearer cv-jobhunter-export-PLAIN-TEXT" \\
     "${baseUrl}/cv/master.md?compact=1"

# Use this when prompt budget matters (e.g. job-score running across
# 50 listings in parallel). Identity, skills matrix, projects header
# row, awards, and publications all preserved.`

const cvExportExample = `curl -H "Authorization: Bearer cv-jobhunter-export-PLAIN-TEXT" \\
     -H "Accept: application/json" \\
     ${baseUrl}/cv/export

# Response (HTTP 200, schema_version "2.0.0" — NO {success, data} envelope)
{
  "schema_version": "2.0.0",
  "generated_at": "2026-05-06T07:19:01Z",
  "basics": {
    "name": "Ali Sadikin",
    "label": "AI Solopreneur Studio · Founder of INDUSIA.ai",
    "email": null,
    "phone": null,
    "url": "https://alisadikinma.com",
    "summary": "<p>17 years in enterprise...</p>",
    "summary_text": "17 years in enterprise...",       // HTML-stripped parallel
    "summary_variants": {
      "vibe_coding":   "Full-stack vibe coder shipping production AI apps...",
      "ai_automation": "AI Visual Inspection live on production lines at...",
      "ai_video":      "AI video generation pipeline operator — VEO 3, Sora..."
    },
    "location": { "city": null, "country": "Indonesia", "remote": true },
    "profiles": [{ "network": "LinkedIn", "url": "https://linkedin.com/in/..." }]
  },
  "work": [                                              // NEW in 2.0.0
    {
      "company": "INDUSIA.ai",
      "position": "Founder / Solo AI Engineer",
      "start_date": "2024-01",
      "end_date": null,
      "location": "Batam, Indonesia (remote-global)",
      "summary": "AI Visual Inspection deployments...",
      "highlights": [
        {
          "text": "Replaced $24K Keyence rigs with $19,950 INDUSIA deploys",
          "metrics": {"cost_saved_usd": 4050, "deployments": 1},
          "tags": ["industrial", "computer_vision"],
          "variant_hint": ["ai_automation"]
        }
      ],
      "tech_stack": ["Python", "PyTorch", "FastAPI"]
    }
  ],
  "education": [],                                       // NEW in 2.0.0
  "skills": {                                            // NEW in 2.0.0
    "languages":  ["Python", "TypeScript", "PHP"],
    "frameworks": ["FastAPI", "Next.js 15", "Laravel"],
    "ai_tools":   ["Claude Sonnet 4.6", "VEO 3", "Sora"],
    "cloud":      ["AWS", "Hostinger VPS"],
    "databases":  ["PostgreSQL", "MySQL"],
    "domain":     ["Computer Vision", "Industrial QC", "RAG"]
  },
  "projects": [
    {
      "name": "...",
      "description": "...",
      "url": "...",
      "industry": "AI",
      "metrics": {"total_deployments": 4, "uptime_pct": 99.7},
      "tags":       ["computer_vision", "industrial_qc"],
      "tech_stack": ["Python", "PyTorch", "ONNX"],       // NEW: separate from tags
      "highlights": [                                     // structured shape now
        {"text": "...", "metrics": null, "tags": [], "variant_hint": []}
      ],
      "variant_hint":   ["ai_automation"],               // NEW: strict 3-value enum
      "relevance_hint": ["ai_automation", "manufacturing"], // legacy, back-compat
      "start_date": "2025-03",
      "end_date": null
    }
    // ~56 entries
  ],
  "awards": [
    {
      "title":        "...",
      "summary":      "<p>...</p>",
      "summary_text": "...",                             // NEW: HTML-stripped parallel
      "is_featured":  true
    }
  ],
  "thought_leadership": [{ "title": "...", "url": "...", "published_at": "..." }]
}`

const cvEtagExample = `# First request — note the ETag header
curl -i -H "Authorization: Bearer cv-jobhunter-export-PLAIN-TEXT" \\
        ${baseUrl}/cv/master.md
HTTP/1.1 200 OK
ETag: W/"852d52b73ef15823a5e0f9822f612b0a"
Content-Type: text/markdown; charset=UTF-8
... full body ~10k tokens

# Second request — send the previous ETag back
curl -i -H "Authorization: Bearer cv-jobhunter-export-PLAIN-TEXT" \\
        -H 'If-None-Match: W/"852d52b73ef15823a5e0f9822f612b0a"' \\
        ${baseUrl}/cv/master.md
HTTP/1.1 304 Not Modified
ETag: W/"852d52b73ef15823a5e0f9822f612b0a"
(empty body — ~80 byte round-trip vs ~10k+ for the full payload)`

const jobhunterRecipe = `# Pseudo-code for the cv-tailor / job-score skills

import requests, os, pathlib, json

CV_TOKEN  = os.environ['PORTFOLIO_CV_TOKEN']     # cv-jobhunter-export plaintext
CACHE_DIR = pathlib.Path('~/.cache/jobhunter').expanduser()
CACHE_DIR.mkdir(parents=True, exist_ok=True)

def fetch_cv_markdown(compact=False):
    """ETag-cached fetch of the CV master markdown.

    Returns the raw markdown string. Hits the network only when the
    upstream content actually changed — most calls return 304 + we
    serve the cached body."""
    suffix    = '?compact=1' if compact else ''
    cache_key = f"cv-master{'-compact' if compact else ''}.md"
    body_path = CACHE_DIR / cache_key
    etag_path = CACHE_DIR / f"{cache_key}.etag"

    headers = {
        'Authorization': f'Bearer {CV_TOKEN}',
        'Accept': 'text/markdown, application/json',
    }
    if etag_path.exists() and body_path.exists():
        headers['If-None-Match'] = etag_path.read_text().strip()

    r = requests.get(
        f'${baseUrl}/cv/master.md{suffix}',
        headers=headers,
        timeout=15,
    )
    if r.status_code == 304:
        return body_path.read_text(encoding='utf-8')   # cache hit
    r.raise_for_status()                                # 401 / 403 / 5xx
    body_path.write_text(r.text, encoding='utf-8')
    if 'ETag' in r.headers:
        etag_path.write_text(r.headers['ETag'])
    return r.text

# In cv-tailor: feed straight to the LLM
cv_md = fetch_cv_markdown(compact=False)
prompt = f"""You are a resume tailor.
Master CV:
{cv_md}

Job description:
{job_description}

Output a tailored resume optimized for this role..."""`

// --- Automation API rendered examples (used in the visual cards) -----------
const getPostsExample = `GET ${baseUrl}/automation/posts?published=true&per_page=10

Response:
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50
  }
}`

const createPostExample = `POST ${baseUrl}/automation/posts
Content-Type: application/json

{
  "title": "My New Post",
  "content": "<p>Post content here...</p>",
  "category_id": 1,
  "excerpt": "Short description",
  "tags": ["tag1", "tag2"],
  "published": true
}

Response:
{
  "success": true,
  "data": { ... },
  "message": "Post created successfully"
}`

const bulkCreateExample = `POST ${baseUrl}/automation/posts/bulk
Content-Type: application/json

{
  "posts": [
    {
      "title": "Post 1",
      "content": "Content 1",
      "category_id": 1
    },
    {
      "title": "Post 2",
      "content": "Content 2",
      "category_id": 2
    }
  ]
}

Response:
{
  "success": true,
  "data": {
    "created": [...],
    "errors": [...]
  },
  "meta": {
    "total_created": 2,
    "total_failed": 0
  }
}`

const updatePostExample = `PUT ${baseUrl}/automation/posts/123
Content-Type: application/json

{
  "title": "Updated Title",
  "published": true
}

Response:
{
  "success": true,
  "data": { ... },
  "message": "Post updated successfully"
}`

const deletePostExample = `DELETE ${baseUrl}/automation/posts/123

Response:
{
  "success": true,
  "message": "Post deleted successfully"
}`

const getCategoriesExample = `GET ${baseUrl}/automation/categories

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology"
    },
    ...
  ]
}`

// --- Markdown export builders ----------------------------------------------
// Build self-contained markdown documents that can be pasted directly into
// another agent's context window. Single-quoted array-of-lines avoids the
// backtick-escaping mess that template literals would create for code spans.
function buildCvDocsMd() {
  return [
    '# Portfolio CV API — Reference',
    '',
    '> Read-only export of identity, projects, awards, and thought leadership.',
    '> Designed for direct embedding into LLM prompts (cv-tailor, job-score) or',
    '> ATS-style consumers (jobhunter platform).',
    '',
    '**Base URL:** `' + baseUrl + '`',
    '',
    '## Quick Start',
    '',
    '1. Mint a token at `' + baseUrl.replace('/api','') + '/admin/automation/tokens`',
    '   - Category: **cv**',
    '   - Ability: **cv:read**',
    '   - Plaintext is shown only once — store it in your secrets manager.',
    '2. Send it as `Authorization: Bearer <token>` on every request.',
    '',
    '## Authentication',
    '',
    '```http',
    'Authorization: Bearer YOUR_CV_TOKEN',
    'Accept: text/markdown, application/json',
    '```',
    '',
    '| Property | Value |',
    '| --- | --- |',
    '| Token prefix | `cv-` |',
    '| Required ability | `cv:read` |',
    '| Rate limit | 30 requests / minute |',
    '| Wrong ability (e.g. `post:write`) | `403 Forbidden` |',
    '| Missing / invalid token | `401 UNAUTHENTICATED` (JSON when `Accept: application/json` or path matches `/api/*`) |',
    '',
    '## Endpoints',
    '',
    '### GET /api/cv/master.md',
    '',
    'LLM-optimized markdown document for direct prompt embedding. English only with',
    'silent Indonesian fallback. Default size **~10k tokens**.',
    '',
    'Sections, in order:',
    '',
    '- Identity & Contact (name, label, summary, social links)',
    '- Summary',
    '- Skills Matrix (5 domains × project counts)',
    '- Selected Projects (~56 entries, sorted by `sort_order`)',
    '- Awards & Recognition (featured-first)',
    '- Thought Leadership (top 5 published posts)',
    '',
    '```bash',
    'curl -H "Authorization: Bearer $CV_TOKEN" \\',
    '     -H "Accept: text/markdown" \\',
    '     ' + baseUrl + '/cv/master.md',
    '```',
    '',
    '### GET /api/cv/master.md?compact=1',
    '',
    'Same shape, **~5k tokens** (Problem + Outcome lines dropped per project).',
    'Use when prompt budget is constrained — e.g. job-score scanning many listings',
    'in parallel.',
    '',
    '```bash',
    'curl -H "Authorization: Bearer $CV_TOKEN" \\',
    '     "' + baseUrl + '/cv/master.md?compact=1"',
    '```',
    '',
    '### GET /api/cv/export',
    '',
    'Structured JSON for ATS-style consumers. **Schema v2.0.0 (May 2026)** —',
    'no `{success, data}` envelope; HTTP status conveys success/failure.',
    '',
    'Top-level shape:',
    '',
    '- `schema_version` (string, currently `"2.0.0"`)',
    '- `generated_at` (ISO-8601 UTC)',
    '- `basics` — identity + 3 `summary_variants` for variant scoring',
    '- `work[]` — employment history (separate from `projects[]`)',
    '- `education[]` — degrees + courses (may be empty list)',
    '- `skills{}` — categorized skills object (`languages` / `frameworks` /',
    '  `ai_tools` / `cloud` / `databases` / `infrastructure` / `domain`)',
    '- `projects[]` — case studies with `tech_stack[]` separate from `tags[]`,',
    '  structured `highlights[]`, strict `variant_hint[]` plus legacy',
    '  `relevance_hint[]` for back-compat',
    '- `awards[]` — featured-first ordering, both `summary` (HTML) and',
    '  `summary_text` (plain) parallel fields',
    '- `thought_leadership[]` — top 5 published posts',
    '',
    '```bash',
    'curl -H "Authorization: Bearer $CV_TOKEN" \\',
    '     -H "Accept: application/json" \\',
    '     ' + baseUrl + '/cv/export',
    '```',
    '',
    'Sample response (schema 2.0.0):',
    '',
    '```json',
    '{',
    '  "schema_version": "2.0.0",',
    '  "generated_at": "2026-05-06T07:19:01Z",',
    '  "basics": {',
    '    "name": "Ali Sadikin",',
    '    "label": "AI Solopreneur Studio · Founder of INDUSIA.ai",',
    '    "url": "https://alisadikinma.com",',
    '    "summary": "<p>17 years in enterprise...</p>",',
    '    "summary_text": "17 years in enterprise...",',
    '    "summary_variants": {',
    '      "vibe_coding":   "Full-stack vibe coder shipping...",',
    '      "ai_automation": "AI Visual Inspection live on production...",',
    '      "ai_video":      "AI video generation pipeline operator..."',
    '    },',
    '    "location": { "city": null, "country": "Indonesia", "remote": true },',
    '    "profiles": [{ "network": "LinkedIn", "url": "..." }]',
    '  },',
    '  "work": [',
    '    {',
    '      "company": "INDUSIA.ai",',
    '      "position": "Founder / Solo AI Engineer",',
    '      "start_date": "2024-01",',
    '      "end_date": null,',
    '      "summary": "AI Visual Inspection deployments...",',
    '      "highlights": [',
    '        {',
    '          "text": "Replaced $24K Keyence rigs with $19,950 deploys",',
    '          "metrics": {"cost_saved_usd": 4050, "deployments": 1},',
    '          "tags": ["industrial", "computer_vision"],',
    '          "variant_hint": ["ai_automation"]',
    '        }',
    '      ],',
    '      "tech_stack": ["Python", "PyTorch", "FastAPI"]',
    '    }',
    '  ],',
    '  "education": [],',
    '  "skills": {',
    '    "languages":  ["Python", "TypeScript"],',
    '    "frameworks": ["FastAPI", "Next.js 15"],',
    '    "ai_tools":   ["Claude Sonnet 4.6", "VEO 3"]',
    '  },',
    '  "projects": [',
    '    {',
    '      "name": "...",',
    '      "tags":          ["computer_vision", "industrial_qc"],',
    '      "tech_stack":    ["Python", "PyTorch", "ONNX"],',
    '      "highlights":    [{"text": "...", "metrics": null, "tags": [], "variant_hint": []}],',
    '      "metrics":       {"total_deployments": 4},',
    '      "variant_hint":  ["ai_automation"],',
    '      "relevance_hint":["ai_automation", "manufacturing"]',
    '    }',
    '  ],',
    '  "awards": [{"title": "...", "summary": "<p>...</p>", "summary_text": "...", "is_featured": true}],',
    '  "thought_leadership": []',
    '}',
    '```',
    '',
    '**Validation note:** every field above maps directly to the consumer-side',
    '`MasterCVContent` Pydantic model. If `model_validate(response.json())`',
    'passes, the consumer imports in <1s with zero LLM cost.',
    '',
    '## ETag Revalidation (recommended)',
    '',
    'All CV endpoints emit a weak `ETag` on every 2xx response. Send the previous',
    'value back as `If-None-Match` on the next request — if nothing changed you',
    'get a `304 Not Modified` with empty body (~80 bytes round-trip vs ~10k–50k',
    'for the full payload). Saves bandwidth and preserves the rate-limit budget',
    'for actual misses.',
    '',
    '```bash',
    '# First request — capture the ETag',
    'curl -i -H "Authorization: Bearer $CV_TOKEN" \\',
    '        ' + baseUrl + '/cv/master.md',
    '# HTTP/1.1 200 OK',
    '# ETag: W/"852d52b73ef15823a5e0f9822f612b0a"',
    '# Content-Type: text/markdown; charset=UTF-8',
    '# ... full body ~10k tokens',
    '',
    '# Second request — pass it back as If-None-Match',
    'curl -i -H "Authorization: Bearer $CV_TOKEN" \\',
    '        -H \'If-None-Match: W/"852d52b73ef15823a5e0f9822f612b0a"\' \\',
    '        ' + baseUrl + '/cv/master.md',
    '# HTTP/1.1 304 Not Modified',
    '# ETag: W/"852d52b73ef15823a5e0f9822f612b0a"',
    '# (empty body)',
    '```',
    '',
    '## jobhunter Agent Integration Recipe',
    '',
    '1. Mint a single `cv-jobhunter-export` token (category `cv`, ability `cv:read`).',
    '2. Store the plaintext in jobhunter\'s secrets manager — only visible at',
    '   creation.',
    '3. For **cv-tailor / cold-email / job-score** skills: fetch',
    '   `/api/cv/master.md` with ETag revalidation. Markdown drops straight into',
    '   the LLM prompt.',
    '4. For **ATS-style structured matching**: use `/api/cv/export` —',
    '   `relevance_hint[]` + `tech_stack[]` enable scoring without re-parsing prose.',
    '5. If the token is lost, click **Regenerate** in the Tokens page (revokes the',
    '   old token and mints a fresh one with the same name + abilities).',
    '',
    '```python',
    '# Pseudo-code for the cv-tailor / job-score skills',
    '',
    'import requests, os, pathlib',
    '',
    'CV_TOKEN  = os.environ[\'PORTFOLIO_CV_TOKEN\']',
    'CACHE_DIR = pathlib.Path(\'~/.cache/jobhunter\').expanduser()',
    'CACHE_DIR.mkdir(parents=True, exist_ok=True)',
    '',
    'def fetch_cv_markdown(compact=False):',
    '    """ETag-cached fetch of the CV master markdown.',
    '',
    '    Returns the raw markdown string. Hits the network only when the',
    '    upstream content actually changed — most calls return 304 + we',
    '    serve the cached body."""',
    '    suffix    = \'?compact=1\' if compact else \'\'',
    '    cache_key = f"cv-master{\'-compact\' if compact else \'\'}.md"',
    '    body_path = CACHE_DIR / cache_key',
    '    etag_path = CACHE_DIR / f"{cache_key}.etag"',
    '',
    '    headers = {',
    '        \'Authorization\': f\'Bearer {CV_TOKEN}\',',
    '        \'Accept\': \'text/markdown, application/json\',',
    '    }',
    '    if etag_path.exists() and body_path.exists():',
    '        headers[\'If-None-Match\'] = etag_path.read_text().strip()',
    '',
    '    r = requests.get(',
    '        f\'' + baseUrl + '/cv/master.md{suffix}\',',
    '        headers=headers,',
    '        timeout=15,',
    '    )',
    '    if r.status_code == 304:',
    '        return body_path.read_text(encoding=\'utf-8\')   # cache hit',
    '    r.raise_for_status()                                # 401 / 403 / 5xx',
    '    body_path.write_text(r.text, encoding=\'utf-8\')',
    '    if \'ETag\' in r.headers:',
    '        etag_path.write_text(r.headers[\'ETag\'])',
    '    return r.text',
    '',
    '# In cv-tailor: feed straight to the LLM',
    'cv_md = fetch_cv_markdown(compact=False)',
    'prompt = f"""You are a resume tailor.',
    'Master CV:',
    '{cv_md}',
    '',
    'Job description:',
    '{job_description}',
    '',
    'Output a tailored resume optimized for this role..."""',
    '```',
    '',
    '---',
    '',
    '_Generated from ' + baseUrl.replace('/api','') + '/admin/automation/docs on ' + new Date().toISOString().slice(0,10) + '_',
    '',
  ].join('\n')
}

function buildAutomationDocsMd() {
  return [
    '# Portfolio Automation API — Reference',
    '',
    '> Write-capable webhook surface for n8n / Zapier / Make.com integrations.',
    '> Use for RSS-to-blog, AI content scheduling, email-to-draft pipelines.',
    '',
    '**Base URL:** `' + baseUrl + '`',
    '',
    '## Quick Start',
    '',
    '1. Mint a token at `' + baseUrl.replace('/api','') + '/admin/automation/tokens`',
    '   - Category: **automation**',
    '   - Abilities: pick from `post:read`, `post:write`, `post:delete`, `category:read`',
    '   - Plaintext is shown only once — store it in your platform\'s credential vault.',
    '2. Send it as `Authorization: Bearer <token>` on every request.',
    '',
    '## Authentication',
    '',
    '```http',
    'Authorization: Bearer YOUR_API_TOKEN',
    'Content-Type: application/json',
    'Accept: application/json',
    '```',
    '',
    '| Property | Value |',
    '| --- | --- |',
    '| Token prefix | `api-` |',
    '| Available abilities | `post:read`, `post:write`, `post:delete`, `category:read` |',
    '| Rate limit | 60 requests / minute |',
    '| Wrong ability | `403 Forbidden` |',
    '| Missing / invalid token | `401 UNAUTHENTICATED` |',
    '',
    '## Endpoints',
    '',
    '### GET /api/automation/posts',
    '',
    'List blog posts with filters.',
    '',
    '**Query parameters:**',
    '',
    '- `published` (boolean)',
    '- `category_id` (integer)',
    '- `search` (string)',
    '- `per_page` (integer, max 100)',
    '- `page` (integer)',
    '',
    '```bash',
    'curl -H "Authorization: Bearer $API_TOKEN" \\',
    '     "' + baseUrl + '/automation/posts?published=true&per_page=10"',
    '```',
    '',
    '```json',
    '{',
    '  "success": true,',
    '  "data": [ /* posts */ ],',
    '  "meta": { "current_page": 1, "last_page": 5, "per_page": 10, "total": 50 }',
    '}',
    '```',
    '',
    '### POST /api/automation/posts',
    '',
    'Create a single post (simplified validation).',
    '',
    '**Required fields:**',
    '',
    '- `title` (string, max 255)',
    '- `content` (string)',
    '- `category_id` (integer)',
    '',
    '**Optional fields:**',
    '',
    '- `slug` (string, auto-generated if omitted)',
    '- `excerpt` (string, auto-generated if omitted)',
    '- `featured_image` (string — URL or base64)',
    '- `tags` (array of strings)',
    '- `published` (boolean, default false)',
    '- `published_at` (datetime, auto-set when `published=true`)',
    '',
    '```bash',
    'curl -X POST -H "Authorization: Bearer $API_TOKEN" \\',
    '     -H "Content-Type: application/json" \\',
    '     -d \'{"title":"My Post","content":"<p>...</p>","category_id":1,"published":true}\' \\',
    '     ' + baseUrl + '/automation/posts',
    '```',
    '',
    '### POST /api/automation/posts/bulk',
    '',
    'Create up to 50 posts in one call. Returns per-post success/failure detail.',
    '',
    '```bash',
    'curl -X POST -H "Authorization: Bearer $API_TOKEN" \\',
    '     -H "Content-Type: application/json" \\',
    '     -d \'{"posts":[{"title":"P1","content":"...","category_id":1}]}\' \\',
    '     ' + baseUrl + '/automation/posts/bulk',
    '```',
    '',
    '```json',
    '{',
    '  "success": true,',
    '  "data": { "created": [/*...*/], "errors": [/*...*/] },',
    '  "meta": { "total_created": 2, "total_failed": 0 }',
    '}',
    '```',
    '',
    '### PUT /api/automation/posts/:id',
    '',
    'Update fields on an existing post.',
    '',
    '```bash',
    'curl -X PUT -H "Authorization: Bearer $API_TOKEN" \\',
    '     -H "Content-Type: application/json" \\',
    '     -d \'{"title":"Updated","published":true}\' \\',
    '     ' + baseUrl + '/automation/posts/123',
    '```',
    '',
    '### DELETE /api/automation/posts/:id',
    '',
    'Delete a post permanently.',
    '',
    '```bash',
    'curl -X DELETE -H "Authorization: Bearer $API_TOKEN" \\',
    '     ' + baseUrl + '/automation/posts/123',
    '```',
    '',
    '### GET /api/automation/categories',
    '',
    'List all blog categories — useful for resolving `category_id` before',
    'creating posts.',
    '',
    '```bash',
    'curl -H "Authorization: Bearer $API_TOKEN" \\',
    '     ' + baseUrl + '/automation/categories',
    '```',
    '',
    '## n8n Workflow Templates',
    '',
    '1. **RSS Feed → Blog**: RSS Feed Read → HTTP Request `POST /automation/posts`',
    '2. **Email → Draft Post**: Gmail trigger → Parse Email → HTTP Request with `published=false`',
    '3. **Scheduled AI Generation**: Schedule trigger → OpenAI → HTTP Request `POST /automation/posts`',
    '',
    '---',
    '',
    '_Generated from ' + baseUrl.replace('/api','') + '/admin/automation/docs on ' + new Date().toISOString().slice(0,10) + '_',
    '',
  ].join('\n')
}

// --- Export actions ---------------------------------------------------------
const justCopied = ref(false)

function activeDocsContent() {
  return activeTab.value === 'cv' ? buildCvDocsMd() : buildAutomationDocsMd()
}

function activeDocsFilename() {
  const slug = activeTab.value === 'cv' ? 'cv-api-docs' : 'automation-api-docs'
  return slug + '.md'
}

async function copyActiveDocs() {
  const md = activeDocsContent()
  try {
    await navigator.clipboard.writeText(md)
    justCopied.value = true
    toast.success(activeTabConfig.value.label + ' docs copied as markdown')
    setTimeout(() => { justCopied.value = false }, 2000)
  } catch (err) {
    // Fallback for browsers without async clipboard API.
    const ta = document.createElement('textarea')
    ta.value = md
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    if (ok) {
      justCopied.value = true
      toast.success('Copied (fallback)')
      setTimeout(() => { justCopied.value = false }, 2000)
    } else {
      toast.error('Failed to copy — try Download .md instead')
    }
  }
}

function downloadActiveDocs() {
  const md = activeDocsContent()
  const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = activeDocsFilename()
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
  toast.success('Downloaded ' + a.download)
}
</script>
