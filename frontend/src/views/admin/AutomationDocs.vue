<template>
  <div class="automation-docs-page">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
        API Documentation
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Reference for the two API surfaces — <strong>Automation</strong> (n8n / Zapier / Make.com webhook ingestion) and <strong>CV API</strong> (read-only export for the jobhunter platform).
      </p>
    </div>

    <!-- Quick Start -->
    <div class="mb-8 rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950">
      <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100">Quick Start</h2>
      <ol class="mt-4 space-y-2 text-sm text-blue-800 dark:text-blue-200">
        <li>1. Open the <router-link to="/admin/automation/tokens" class="underline">Tokens page</router-link> and click <strong>Create Token</strong>.</li>
        <li>2. Pick the right <strong>Category</strong>: <code class="rounded bg-blue-100 px-1.5 dark:bg-blue-900">automation</code> for blog/CMS write access, <code class="rounded bg-blue-100 px-1.5 dark:bg-blue-900">cv</code> for read-only CV export.</li>
        <li>3. Copy the plaintext token (only shown once at creation).</li>
        <li>4. Send it as <code class="rounded bg-blue-100 px-1.5 dark:bg-blue-900">Authorization: Bearer &lt;token&gt;</code> header.</li>
      </ol>
    </div>

    <!-- Authentication -->
    <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Authentication</h2>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Both surfaces use Laravel Sanctum bearer tokens. The token's category and abilities determine which endpoints will accept it.
      </p>
      <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-900 p-4 text-sm text-green-400"><code>Authorization: Bearer YOUR_API_TOKEN
Accept: application/json</code></pre>

      <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-950">
          <p class="text-sm font-semibold text-purple-900 dark:text-purple-100">Automation surface</p>
          <p class="mt-1 text-xs text-purple-800 dark:text-purple-200">
            Token prefix <code>api-</code> · abilities: <code>post:read</code>, <code>post:write</code>, <code>post:delete</code>, <code>category:read</code> · rate limit <strong>60/min</strong>.
          </p>
        </div>
        <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-950">
          <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">CV surface</p>
          <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-200">
            Token prefix <code>cv-</code> · ability: <code>cv:read</code> · rate limit <strong>30/min</strong>. Cross-surface usage (<code>post:write</code> on a <code>cv</code> token) returns <strong>403</strong>.
          </p>
        </div>
      </div>

      <div class="mt-4 rounded-lg bg-amber-50 p-4 dark:bg-amber-950">
        <p class="text-sm text-amber-900 dark:text-amber-100">
          <strong>401 vs 403:</strong> Missing or invalid token → <code>401 UNAUTHENTICATED</code>. Valid token without the required ability → <code>403 Forbidden</code>. Both responses are JSON when the request sets <code>Accept: application/json</code> or hits an <code>/api/*</code> path.
        </p>
      </div>
    </div>

    <!-- ============================================================== -->
    <!-- CV API Section (jobhunter integration) ====================== -->
    <!-- ============================================================== -->
    <div class="mb-4 flex items-center gap-3">
      <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">CV API</span>
      <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">CV Export — for jobhunter Agent</h2>
    </div>
    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
      Read-only export of identity, projects, awards, and thought leadership. Designed for direct embedding into LLM prompts (cv-tailor, job-score) or ATS-style consumers. All endpoints require a token with the <code>cv:read</code> ability.
    </p>

    <div class="mb-8 space-y-6">
      <!-- CV Master Markdown -->
      <div class="rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
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
      <div class="rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
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
      <div class="rounded-lg border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-gray-800">
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
      <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-6 dark:border-cyan-900 dark:bg-cyan-950">
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
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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

    <!-- ============================================================== -->
    <!-- Automation API Section ====================================== -->
    <!-- ============================================================== -->
    <div class="mb-4 flex items-center gap-3">
      <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-purple-800 dark:bg-purple-900 dark:text-purple-200">Automation API</span>
      <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Blog / CMS Webhooks</h2>
    </div>
    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
      Write-capable surface for n8n / Zapier / Make.com — RSS-to-blog, AI content scheduling, email-to-draft. Tokens use prefix <code>api-</code> with the <code>post:*</code> + <code>category:read</code> ability whitelist.
    </p>

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
</template>

<script setup>
// Resolve API base URL from the actual origin so the docs render production
// URLs on alisadikinma.com and dev URLs on localhost. The Vite dev server
// runs on :5173 but the API itself lives on XAMPP Apache at /Portfolio_v2/
// — special-case that pair so dev examples remain copy-pasteable.
const baseUrl = (() => {
  if (typeof window === 'undefined') return '/api'
  const origin = window.location.origin
  if (origin.includes('localhost:5173')) {
    return 'http://localhost/Portfolio_v2/backend/public/api'
  }
  return `${origin}/api`
})()

// --- CV API examples ---------------------------------------------------------
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

# Response (HTTP 200, JSON Resume v1.0.0 envelope)
{
  "$schema": "https://jsonresume.org/schema/1.0.0/resume.json",
  "basics": {
    "name": "Ali Sadikin",
    "label": "AI Generalist Expert",
    "summary": "...",
    "location": { "city": null, "region": "Indonesia" },
    "profiles": [
      { "network": "LinkedIn", "url": "https://linkedin.com/in/..." },
      ...
    ]
  },
  "work": [
    {
      "name": "Project Name",
      "position": "Lead AI Engineer",
      "startDate": "2024",
      "endDate": "2025",
      "summary": "Short description",
      "highlights": ["Outcome 1", "Outcome 2"],
      "industry": "Manufacturing",
      "tech_stack": ["Python", "LangChain", "n8n"],
      "relevance_hint": ["ai_automation", "manufacturing"]
    },
    ... // ~56 entries
  ],
  "awards":       [ ... ],   // featured-first
  "publications": [ ... ]    // top 5 by published_at DESC
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

// --- Automation API examples (existing) -------------------------------------
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
</script>
