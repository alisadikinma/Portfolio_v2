<template>
  <div class="automation-tokens-page">
    <!-- Page Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
          API Tokens
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Manage API tokens across surfaces — Automation (n8n / Zapier / Make.com) and CV API (jobhunter export).
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600"
      >
        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Create Token
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Tokens</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
              {{ automationStore.totalActiveTokens }}
            </p>
          </div>
          <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Automation Requests</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
              {{ automationStore.totalRequests }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CV calls tracked via last_used_at instead</p>
          </div>
          <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Top-level view tabs -->
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex gap-6" aria-label="Token admin sections">
        <button
          v-for="view in topLevelViews"
          :key="view.slug"
          @click="mainView = view.slug"
          :class="[
            'inline-flex items-center gap-2 border-b-2 px-1 py-3 text-sm font-medium transition-colors',
            mainView === view.slug
              ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
          ]"
        >
          <svg v-if="view.slug === 'tokens'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
          <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
          {{ view.label }}
        </button>
      </nav>
    </div>

    <!-- Tokens view -->
    <div v-if="mainView === 'tokens'">
      <!-- Category tabs (sub-nav under Tokens view) -->
      <div class="mb-4 flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800 w-max max-w-full">
        <button
          v-for="tab in tabs"
          :key="tab.slug"
          @click="activeTab = tab.slug"
          :class="[
            'inline-flex items-center gap-2 px-4 py-1.5 rounded-md text-sm font-medium transition-all',
            activeTab === tab.slug
              ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
              : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
          ]"
        >
          <span>{{ tab.label }}</span>
          <span
            class="text-xs font-mono px-1.5 py-0.5 rounded-full"
            :class="activeTab === tab.slug ? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- Tokens List -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div class="border-b border-gray-200 p-6 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ activeTabConfig.label }} Tokens</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ activeTabConfig.description }}</p>
      </div>

      <div v-if="automationStore.loading" class="flex items-center justify-center p-12">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
      </div>

      <div v-else-if="filteredTokens.length === 0" class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No tokens in this category</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Create a token to get started
        </p>
        <button
          @click="openCreateModal"
          class="mt-6 inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Create Token
        </button>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Category</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Abilities</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Requests</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Used</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="token in filteredTokens" :key="token.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ token.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="categoryBadgeClass(token.category)"
                >
                  {{ categoryLabel(token.category) }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="(ability, index) in token.abilities"
                    :key="index"
                    class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  >
                    {{ ability }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                <!-- requests_count is sourced from automation_logs (only
                     populated for automation-category tokens). CV tokens
                     always read 0 here — Sanctum's last_used_at column is
                     the authoritative usage signal for CV API. -->
                <span v-if="token.category === 'automation'">{{ token.requests_count || 0 }}</span>
                <span v-else class="text-gray-400 dark:text-gray-500" title="Use Last Used column for CV tokens — request logging not wired for this surface">—</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <span :title="token.last_used_at ? formatDate(token.last_used_at) : 'Never used'" :class="usageBadgeClass(token.last_used_at)">
                  {{ token.last_used_at ? relativeTime(token.last_used_at) : 'Never' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ formatDate(token.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="inline-flex items-center gap-3">
                  <!-- Copy: only when plaintext is cached this session.
                       Otherwise we render a non-interactive lock badge so the
                       UI doesn't promise something it can't deliver. -->
                  <button
                    v-if="hasPlaintextCached(token.id)"
                    @click="handleCopyToken(token)"
                    title="Cached this session — click to copy"
                    class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                  >
                    <svg v-if="copiedTokenId !== token.id" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg v-else class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span :class="copiedTokenId === token.id ? 'text-emerald-600 dark:text-emerald-400' : ''">
                      {{ copiedTokenId === token.id ? 'Copied' : 'Copy' }}
                    </span>
                  </button>
                  <span
                    v-else
                    title="Token plain-text only visible at creation — regenerate to get a fresh copyable value"
                    class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500 cursor-default"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.657 1.343-3 3-3s3 1.343 3 3v2M5 11h14a1 1 0 011 1v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8a1 1 0 011-1z" />
                    </svg>
                    <span>Hidden</span>
                  </span>
                  <span class="text-gray-300 dark:text-gray-600">·</span>
                  <button
                    @click="confirmRegenerate(token)"
                    title="Revoke this token and mint a fresh one with the same name + abilities"
                    class="text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                  >
                    Regenerate
                  </button>
                  <span class="text-gray-300 dark:text-gray-600">·</span>
                  <button
                    @click="confirmRevoke(token)"
                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </div>
    <!-- /tokens view -->

    <!-- API Playground (top-level tab) -->
    <div v-if="mainView === 'playground'" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div class="border-b border-gray-200 p-6 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">API Playground</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Test an endpoint with one of your tokens. The token IS the bearer sent on the wire — no admin-session fallback. Switch back to the <button @click="mainView = 'tokens'" class="text-blue-600 hover:underline dark:text-blue-400">Tokens tab</button> to mint or revoke.
        </p>
      </div>
      <div class="p-6 space-y-4">
        <!-- Form row -->
        <div class="grid gap-4 md:grid-cols-12">
          <div class="md:col-span-3">
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Token</label>
            <select
              v-model="playground.tokenId"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
              <option :value="null">— Select token —</option>
              <option v-for="t in automationStore.tokens" :key="t.id" :value="t.id">
                {{ t.name }} ({{ categoryLabel(t.category) }})
              </option>
            </select>
            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
              Tokens shown above; plain text only available right after creation.
            </p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Method</label>
            <select
              v-model="playground.method"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
              <option>GET</option>
              <option>POST</option>
              <option>PUT</option>
              <option>DELETE</option>
            </select>
          </div>
          <div class="md:col-span-7">
            <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Endpoint</label>
            <input
              v-model="playground.endpoint"
              type="text"
              placeholder="/api/cv/master.md"
              list="playground-suggestions"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
            <datalist id="playground-suggestions">
              <option v-for="s in endpointSuggestions" :key="s" :value="s" />
            </datalist>
            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
              Path only (e.g. <code>/api/cv/master.md</code>). Hosted from this server. Suggestions auto-filter by selected token's abilities.
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button
            @click="runPlayground"
            :disabled="!canRunPlayground"
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            <svg v-if="!playground.loading" class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
            <span v-if="playground.loading" class="-ml-1 mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            {{ playground.loading ? 'Testing…' : 'Send Request' }}
          </button>
          <button
            v-if="playground.response"
            @click="resetPlayground"
            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400"
          >
            Clear result
          </button>
          <span v-if="playground.warning" class="text-xs text-amber-600 dark:text-amber-400">{{ playground.warning }}</span>
        </div>

        <!-- Token has no cached plaintext — playground can't bearer-auth without
             it. Point operator at the Regenerate action instead of asking them
             to paste manually (consistent with the row-level UX). -->
        <div v-if="playground.tokenId && !playgroundTokenPlain" class="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
          <p class="text-sm text-amber-800 dark:text-amber-200">
            <span class="font-medium">Token plaintext unavailable.</span>
            It was minted in a different session and Sanctum only stores the hash.
            <button @click="mainView = 'tokens'" class="underline hover:no-underline">Switch to Tokens</button>
            and click <span class="font-medium">Regenerate</span> to get a fresh copyable value.
          </p>
        </div>

        <!-- Response panel -->
        <div v-if="playground.response" class="rounded-md border border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-2 dark:border-gray-700">
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-mono font-bold"
              :class="statusBadgeClass(playground.response.status)"
            >
              {{ playground.response.status }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              {{ playground.response.durationMs }}ms · {{ formatBytes(playground.response.size) }}
            </span>
            <button
              @click="copyResponse"
              class="ml-auto text-xs text-blue-600 hover:underline dark:text-blue-400"
            >
              {{ playground.copied ? 'Copied!' : 'Copy body' }}
            </button>
          </div>
          <div class="p-4 space-y-3">
            <div>
              <p class="text-[11px] font-mono uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Headers</p>
              <pre class="overflow-x-auto rounded bg-gray-50 p-3 text-[11px] font-mono text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ playground.response.headersText }}</pre>
            </div>
            <div>
              <div class="mb-1 flex items-center justify-between">
                <p class="text-[11px] font-mono uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Body
                  <span class="text-emerald-600 dark:text-emerald-400">
                    · {{ playground.response.size }} bytes · {{ playground.response.lineCount }} lines (full · scroll to read)
                  </span>
                </p>
                <div class="flex items-center gap-2">
                  <button
                    @click="copyPlaygroundBody"
                    class="text-[11px] font-mono uppercase tracking-wider text-blue-600 hover:text-blue-700 dark:text-blue-400"
                  >
                    {{ playground.copied ? 'Copied ✓' : 'Copy full' }}
                  </button>
                  <button
                    @click="downloadPlaygroundBody"
                    class="text-[11px] font-mono uppercase tracking-wider text-gray-600 hover:text-gray-800 dark:text-gray-400"
                  >
                    Download
                  </button>
                </div>
              </div>
              <pre class="max-h-[600px] overflow-auto rounded bg-gray-50 p-3 text-[11px] font-mono text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ playground.response.bodyFull }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Token Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showCreateModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create API Token</h3>

          <div class="mt-4 space-y-4">
            <!-- Category dropdown -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
              <select
                v-model="newToken.category"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
              >
                <option v-for="cat in selectableCategories" :key="cat.slug" :value="cat.slug">
                  {{ cat.label }}
                </option>
              </select>
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ selectedCategoryConfig?.description || '' }}
              </p>
            </div>

            <!-- Token name with category-aware prefix addon -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Token Name</label>
              <div class="mt-1 flex rounded-md shadow-sm">
                <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400">
                  {{ selectedCategoryConfig?.prefix || 'api-' }}
                </span>
                <input
                  v-model="newToken.name"
                  type="text"
                  :placeholder="namePlaceholder"
                  class="block w-full flex-1 rounded-none rounded-r-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
              </div>
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Final name: {{ (selectedCategoryConfig?.prefix || 'api-') + (newToken.name || '<name>') }}
              </p>
            </div>

            <!-- Abilities (driven by selected category) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Abilities</label>
              <div class="mt-2 space-y-2">
                <label v-for="ability in availableAbilities" :key="ability" class="flex items-center">
                  <input
                    type="checkbox"
                    :value="ability"
                    v-model="newToken.abilities"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                  />
                  <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ ability }}</span>
                </label>
                <p v-if="availableAbilities.length === 0" class="text-xs text-gray-500 dark:text-gray-400">
                  Select a category to see available abilities
                </p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex space-x-3">
            <button
              @click="createToken"
              :disabled="!newToken.name || newToken.abilities.length === 0 || !newToken.category"
              class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              Create Token
            </button>
            <button
              @click="showCreateModal = false"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Token Created Modal (shows token once) -->
    <div
      v-if="showTokenModal"
      class="fixed inset-0 z-50 overflow-y-auto"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <div class="text-center">
            <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Token Created!</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              Copy this token now. You won't be able to see it again!
            </p>

            <div class="mt-4 rounded-lg bg-gray-100 p-4 dark:bg-gray-700">
              <code class="break-all text-sm text-gray-900 dark:text-white">{{ createdToken }}</code>
            </div>

            <button
              @click="copyToken"
              class="mt-4 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              {{ copied ? 'Copied!' : 'Copy Token' }}
            </button>

            <button
              @click="closeTokenModal"
              class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Regenerate Confirmation Modal — replaces the old "paste your saved
         token" recovery flow. Revoke + create cycle invalidates any consumer
         still using the old plaintext, so we confirm before proceeding. -->
    <div
      v-if="showRegenerateModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showRegenerateModal = false"
      @keydown.esc="showRegenerateModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
              <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z" />
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Regenerate token?</h3>
              <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                This revokes <span class="font-medium text-gray-900 dark:text-white">{{ tokenToRegenerate?.name }}</span>
                and mints a fresh token with the same name and abilities. Any integration still using the old token
                will start receiving 401 errors immediately.
              </p>
              <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                You'll see the new plaintext once — copy it before closing.
              </p>
            </div>
          </div>

          <div class="mt-6 flex space-x-3">
            <button
              @click="regenerateToken"
              :disabled="regenerating"
              class="flex-1 inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
            >
              <span v-if="regenerating" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
              {{ regenerating ? 'Regenerating…' : 'Regenerate' }}
            </button>
            <button
              @click="showRegenerateModal = false"
              :disabled="regenerating"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showRevokeModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showRevokeModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Token</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to delete "{{ tokenToRevoke?.name }}"? This will permanently revoke access and cannot be undone.
          </p>

          <div class="mt-6 flex space-x-3">
            <button
              @click="revokeToken"
              class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
              Delete
            </button>
            <button
              @click="showRevokeModal = false"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAutomation } from '@/stores/automation'
import { useToast } from '@/stores/ui'

const automationStore = useAutomation()
const toast = useToast()

const showCreateModal = ref(false)
const showTokenModal = ref(false)
const showRevokeModal = ref(false)
const createdToken = ref('')
const copied = ref(false)
const tokenToRevoke = ref(null)
const activeTab = ref('all')

// Per-row Copy action state. `copiedTokenId` is the id of the row that just
// had its token copied to the clipboard — used to flip the icon + label to
// "Copied" for ~2s. Tokens minted in a previous session have no cached
// plaintext (Sanctum stores hash only) — those rows show a lock icon instead
// of Copy, and the operator regenerates if they need a fresh copyable token.
const copiedTokenId = ref(null)
const showRegenerateModal = ref(false)
const tokenToRegenerate = ref(null)
const regenerating = ref(false)

// Top-level view: 'tokens' (CRUD listing with category sub-tabs) vs
// 'playground' (in-page endpoint tester). Persisted across navigations
// via sessionStorage so operators returning from another admin page
// land back where they left off.
const MAIN_VIEW_KEY = 'admin:tokens:mainView'
const mainView = ref(sessionStorage.getItem(MAIN_VIEW_KEY) || 'tokens')
watch(mainView, (v) => sessionStorage.setItem(MAIN_VIEW_KEY, v))

const topLevelViews = [
  { slug: 'tokens', label: 'Tokens' },
  { slug: 'playground', label: 'API Playground' },
]

// Category-aware new-token form. Defaults to automation for backward compat
// with existing operator muscle memory.
const newToken = ref({
  category: 'automation',
  name: '',
  abilities: []
})

// --- Category helpers --------------------------------------------------------
// `selectableCategories` uses the live registry from the backend. Falls back
// to a static default when the categories endpoint isn't reachable, so the
// modal still works in degraded conditions.
const FALLBACK_CATEGORIES = [
  { slug: 'automation', label: 'Automation', prefix: 'api-',
    abilities: ['post:read', 'post:write', 'post:delete', 'category:read'],
    description: 'Webhook ingestion for n8n / Zapier / Make.com' },
  { slug: 'cv', label: 'CV API', prefix: 'cv-',
    abilities: ['cv:read'],
    description: 'Read-only CV export for jobhunter platform' },
]

const selectableCategories = computed(() =>
  automationStore.categories.length > 0 ? automationStore.categories : FALLBACK_CATEGORIES
)

const selectedCategoryConfig = computed(() =>
  selectableCategories.value.find((c) => c.slug === newToken.value.category)
)

const availableAbilities = computed(() =>
  selectedCategoryConfig.value?.abilities || []
)

const namePlaceholder = computed(() =>
  newToken.value.category === 'cv' ? 'jobhunter-export' : 'n8n-workflow'
)

// --- Tabs --------------------------------------------------------------------
// Tabs are derived from the registered categories + an "All" entry. Counts
// come from the store getter that buckets tokens by their backend-derived
// category field.
const tabs = computed(() => {
  const byCategory = automationStore.tokensByCategory
  const total = automationStore.tokens.length
  return [
    { slug: 'all', label: 'All', count: total, description: 'Every API token across surfaces' },
    ...selectableCategories.value.map((cat) => ({
      slug: cat.slug,
      label: cat.label,
      count: byCategory[cat.slug] || 0,
      description: cat.description,
    })),
  ]
})

const activeTabConfig = computed(() =>
  tabs.value.find((t) => t.slug === activeTab.value) || tabs.value[0]
)

const filteredTokens = computed(() => {
  if (activeTab.value === 'all') return automationStore.tokens
  return automationStore.tokens.filter((t) => t.category === activeTab.value)
})

// --- Category badge styling --------------------------------------------------
function categoryBadgeClass(slug) {
  const map = {
    automation: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    cv:         'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
  }
  return map[slug] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

function categoryLabel(slug) {
  const cat = selectableCategories.value.find((c) => c.slug === slug)
  return cat?.label || (slug ? slug.toUpperCase() : '—')
}

// --- Lifecycle ---------------------------------------------------------------
onMounted(async () => {
  try {
    // Fire both in parallel — categories are static config, tokens are live data.
    await Promise.all([
      automationStore.fetchCategories(),
      automationStore.fetchTokens(),
    ])
  } catch (error) {
    toast.error('Failed to load tokens')
  }
})

// Reset abilities when category changes (different categories have disjoint
// ability whitelists; previously-selected abilities may not apply to the
// newly-selected category).
watch(() => newToken.value.category, () => {
  newToken.value.abilities = []
})

// --- Actions -----------------------------------------------------------------
function openCreateModal() {
  // If user is on a specific category tab, pre-select that category in the
  // create modal — saves a click.
  if (activeTab.value !== 'all'
      && selectableCategories.value.some((c) => c.slug === activeTab.value)) {
    newToken.value.category = activeTab.value
  }
  showCreateModal.value = true
}

const createToken = async () => {
  try {
    const response = await automationStore.createToken({
      category: newToken.value.category,
      name: newToken.value.name,
      abilities: newToken.value.abilities
    })

    createdToken.value = response.token
    showCreateModal.value = false
    showTokenModal.value = true
    toast.success('Token created successfully')

    // Reset form (preserve category so consecutive creates stay in same surface)
    newToken.value = {
      category: newToken.value.category,
      name: '',
      abilities: []
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to create token')
  }
}

const copyToken = async () => {
  try {
    await navigator.clipboard.writeText(createdToken.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (error) {
    toast.error('Failed to copy token')
  }
}

const closeTokenModal = () => {
  showTokenModal.value = false
  createdToken.value = ''
  copied.value = false
}

// --- Per-row Copy action ----------------------------------------------------
// Look up plain-text from the session cache (populated at mint time + via the
// paste-fallback modal). Returns null when nothing's cached for this id.
function hasPlaintextCached(tokenId) {
  return Boolean(playgroundCache.value[tokenId])
}

async function writeToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // Fallback for browsers without the async clipboard API (rare in admin
    // contexts but cheap to support).
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    return ok
  }
}

async function handleCopyToken(token) {
  // Copy button only renders when plaintext is cached — this is just the
  // hot-path action. The lock-icon variant has no click handler.
  const cached = playgroundCache.value[token.id]
  if (!cached) return
  const ok = await writeToClipboard(cached)
  if (ok) {
    copiedTokenId.value = token.id
    toast.success('Token copied to clipboard')
    setTimeout(() => {
      if (copiedTokenId.value === token.id) copiedTokenId.value = null
    }, 2000)
  } else {
    toast.error('Failed to copy token')
  }
}

// --- Regenerate ----------------------------------------------------------
// Replaces the old "paste your saved token" recovery flow. When operator
// loses the plaintext (token from a previous session), they regenerate:
// revoke the old token then mint a fresh one with the same name + abilities.
// The new plaintext is cached + shown in the "Token Created" modal so the
// operator can copy it once before it's gone.
function confirmRegenerate(token) {
  tokenToRegenerate.value = token
  showRegenerateModal.value = true
}

async function regenerateToken() {
  const old = tokenToRegenerate.value
  if (!old || regenerating.value) return
  regenerating.value = true
  try {
    // Strip the category prefix from the stored name so createToken can
    // re-prefix it server-side (consistent with how the create modal works).
    const cat = selectableCategories.value.find((c) => c.slug === old.category)
    const prefix = cat?.prefix || 'api-'
    const bareName = old.name.startsWith(prefix) ? old.name.slice(prefix.length) : old.name

    await automationStore.revokeToken(old.id)

    const response = await automationStore.createToken({
      category: old.category,
      name: bareName,
      abilities: old.abilities || [],
    })

    // Drop the cached plaintext for the now-revoked id (keeps cache tidy).
    delete playgroundCache.value[old.id]

    createdToken.value = response.token
    showRegenerateModal.value = false
    tokenToRegenerate.value = null
    showTokenModal.value = true
    toast.success('Token regenerated')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to regenerate token')
  } finally {
    regenerating.value = false
  }
}

const confirmRevoke = (token) => {
  tokenToRevoke.value = token
  showRevokeModal.value = true
}

const revokeToken = async () => {
  try {
    await automationStore.revokeToken(tokenToRevoke.value.id)
    showRevokeModal.value = false
    tokenToRevoke.value = null
    toast.success('Token deleted successfully')
  } catch (error) {
    toast.error('Failed to delete token')
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Relative time for token usage indicator. Shorter than full datetime —
// scannable at a glance for "is this token active?".
const relativeTime = (dateString) => {
  if (!dateString) return 'Never'
  const diff = Date.now() - new Date(dateString).getTime()
  if (diff < 0) return 'just now'
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  const months = Math.floor(days / 30)
  if (months < 12) return `${months}mo ago`
  return `${Math.floor(months / 12)}y ago`
}

// Color usage indicator by activity tier:
//   <24h   → emerald (active)
//   <7d    → blue    (recent)
//   <30d   → amber   (stale)
//   ≥30d   → red     (dormant)
//   never  → gray
function usageBadgeClass(dateString) {
  const base = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '
  if (!dateString) return base + 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
  const days = (Date.now() - new Date(dateString).getTime()) / 86400000
  if (days < 1)  return base + 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
  if (days < 7)  return base + 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  if (days < 30) return base + 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
  return base + 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
}

// --- API Playground ---------------------------------------------------------
// In-page tool to hit any backend endpoint with one of the existing tokens.
// Direct fetch (NOT through axios `api` instance) so the bearer being tested
// is what actually goes on the wire — no admin-session interceptor fallback.
//
// Plain-text caching strategy:
//   - When a token is minted via the modal, we cache its plainText keyed by
//     id in this component's lifetime. Survives component re-mount within a
//     session via sessionStorage (cleared on tab close — never persisted).
//   - For tokens minted in a previous session OR via tinker, the operator
//     pastes the plain-text value into the manual-token field below.
//   - Sanctum does NOT store plain text server-side — by design.
const PLAINTEXT_CACHE_KEY = 'admin:tokens:plaintext-cache'

const playgroundCache = ref(loadPlaintextCache())
function loadPlaintextCache() {
  try {
    const raw = sessionStorage.getItem(PLAINTEXT_CACHE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch { return {} }
}
function savePlaintextCache() {
  try {
    sessionStorage.setItem(PLAINTEXT_CACHE_KEY, JSON.stringify(playgroundCache.value))
  } catch { /* quota / disabled — degrade silently */ }
}

const playground = ref({
  tokenId: null,
  method: 'GET',
  endpoint: '/api/cv/master.md',
  loading: false,
  response: null,
  warning: '',
  copied: false,
})

const playgroundTokenPlain = computed(() => {
  if (!playground.value.tokenId) return null
  return playgroundCache.value[playground.value.tokenId] || null
})

// Suggested endpoints filtered by selected token's abilities. Falls back to
// full list when no token selected so the operator can browse what's tested.
const ENDPOINT_CATALOG = [
  { path: '/api/health', abilities: [], desc: 'Liveness' },
  { path: '/api/cv/master.md', abilities: ['cv:read'], desc: 'CV master markdown' },
  { path: '/api/cv/master.md?compact=1', abilities: ['cv:read'], desc: 'CV compact' },
  { path: '/api/cv/export', abilities: ['cv:read'], desc: 'JSON Resume' },
  { path: '/api/automation/posts', abilities: ['post:read'], desc: 'List posts (automation)' },
  { path: '/api/categories', abilities: [], desc: 'Public categories' },
]

const endpointSuggestions = computed(() => {
  const sel = automationStore.tokens.find((t) => t.id === playground.value.tokenId)
  if (!sel) return ENDPOINT_CATALOG.map((e) => e.path)
  return ENDPOINT_CATALOG
    .filter((e) => e.abilities.length === 0 || e.abilities.some((a) => sel.abilities?.includes(a)))
    .map((e) => e.path)
})

const canRunPlayground = computed(() =>
  playground.value.tokenId
  && playground.value.endpoint.startsWith('/')
  && !!playgroundTokenPlain.value
  && !playground.value.loading
)

function statusBadgeClass(status) {
  if (status >= 200 && status < 300) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
  if (status >= 300 && status < 400) return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  if (status >= 400 && status < 500) return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
  return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
}

function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes}B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)}KB`
  return `${(bytes / 1024 / 1024).toFixed(2)}MB`
}

async function runPlayground() {
  const bearer = playgroundTokenPlain.value
  if (!bearer) {
    playground.value.warning = 'Plaintext not cached — regenerate this token from the Tokens tab'
    return
  }
  playground.value.warning = ''
  playground.value.loading = true
  playground.value.response = null
  const start = performance.now()

  try {
    // Use raw fetch — bypasses the axios interceptor that auto-attaches the
    // admin's session bearer. We want THIS specific bearer on the wire.
    // baseURL inferred from window.location origin so dev (localhost) and
    // prod (alisadikinma.com) both work.
    const resp = await fetch(playground.value.endpoint, {
      method: playground.value.method,
      headers: {
        Authorization: `Bearer ${bearer}`,
        Accept: 'application/json, text/markdown, */*',
      },
    })
    const text = await resp.text()
    const durationMs = Math.round(performance.now() - start)

    // Format headers as displayable text
    const headerLines = []
    resp.headers.forEach((value, key) => headerLines.push(`${key}: ${value}`))

    // Show full body in scrollable container — no client-side truncation.
    // Operator can scroll / Ctrl+F / Copy full.
    const lineCount = text.split('\n').length

    playground.value.response = {
      status: resp.status,
      headersText: headerLines.sort().join('\n'),
      bodyText: text,
      bodyFull: text,
      lineCount,
      size: new Blob([text]).size,
      durationMs,
    }
  } catch (err) {
    playground.value.response = {
      status: 0,
      headersText: '(no response)',
      bodyText: `Network error: ${err.message}`,
      bodyFull: '',
      bodyTruncated: false,
      size: 0,
      durationMs: Math.round(performance.now() - start),
    }
  } finally {
    playground.value.loading = false
  }
}

function downloadPlaygroundBody() {
  if (!playground.value.response?.bodyFull) return
  const ext = playground.value.endpoint.match(/\.([a-z0-9]+)(\?|$)/i)?.[1] ?? 'txt'
  const filename = `playground-response-${Date.now()}.${ext}`
  const blob = new Blob([playground.value.response.bodyFull], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

async function copyPlaygroundBody() {
  if (!playground.value.response?.bodyFull) return
  try {
    await navigator.clipboard.writeText(playground.value.response.bodyFull)
    playground.value.copied = true
    setTimeout(() => { playground.value.copied = false }, 2000)
  } catch (err) {
    // Fallback: select-and-copy via temporary textarea (rare browsers without clipboard API).
    const ta = document.createElement('textarea')
    ta.value = playground.value.response.bodyFull
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    playground.value.copied = true
    setTimeout(() => { playground.value.copied = false }, 2000)
  }
}

function resetPlayground() {
  playground.value.response = null
  playground.value.copied = false
}

async function copyResponse() {
  if (!playground.value.response?.bodyFull) return
  try {
    await navigator.clipboard.writeText(playground.value.response.bodyFull)
    playground.value.copied = true
    setTimeout(() => { playground.value.copied = false }, 2000)
  } catch {
    toast.error('Failed to copy')
  }
}

// Hook into successful mints: cache the plain-text token by id so the
// playground can use it without operator paste. Watch the createdToken ref —
// it gets set when a token is freshly minted.
watch(createdToken, (val) => {
  if (!val) return
  // The newest token is at the head of the list (store.unshift on success).
  const newest = automationStore.tokens[0]
  if (newest) {
    playgroundCache.value[newest.id] = val
    savePlaintextCache()
  }
})
</script>
