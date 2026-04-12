<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Content Engine</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">Manage content ideas, research, and generation pipeline</p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Health Badge -->
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
          :class="healthOnline
            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'"
        >
          <span class="w-2 h-2 rounded-full" :class="healthOnline ? 'bg-green-500' : 'bg-red-500'"></span>
          {{ healthOnline ? 'Engine Online' : 'Engine Offline' }}
        </span>

        <!-- Pull Trending Dropdown -->
        <div class="relative">
          <button
            @click="trendingDropdownOpen = !trendingDropdownOpen"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 bg-white dark:bg-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
          >
            Pull Trending
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
          </button>
          <div v-if="trendingDropdownOpen" class="absolute right-0 mt-1 w-48 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg shadow-lg z-20">
            <button v-for="src in trendingSources" :key="src.value" @click="openTrendingModal(src.value)" class="block w-full text-left px-4 py-2 text-sm text-neutral-700 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 first:rounded-t-lg last:rounded-b-lg">
              {{ src.label }}
            </button>
          </div>
        </div>

        <!-- Add Idea Button -->
        <button @click="showAddForm = !showAddForm" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          Add Idea
        </button>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <select v-model="filters.pillar" @change="refreshIdeas" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">All Pillars</option>
          <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
        </select>
        <select v-model="filters.status" @change="refreshIdeas" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ formatStatus(s) }}</option>
        </select>
        <select v-model="filters.priority" @change="refreshIdeas" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">All Priorities</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <input v-model="filters.search" @input="debounceSearch" type="text" placeholder="Search ideas..." class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400" />
      </div>
    </div>

    <!-- Add Idea Inline Form -->
    <div v-if="showAddForm" class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-amber-200 dark:border-amber-700 p-4">
      <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">New Idea</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <input v-model="newIdea.title" type="text" placeholder="Topic title..." class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400 sm:col-span-1" />
        <select v-model="newIdea.pillar" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">Select Pillar</option>
          <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
        </select>
        <div class="flex gap-2">
          <select v-model="newIdea.priority" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
            <option value="medium">Medium</option>
            <option value="low">Low</option>
            <option value="high">High</option>
          </select>
          <button @click="handleCreateIdea" :disabled="!newIdea.title || isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50 whitespace-nowrap">
            Save
          </button>
          <button @click="showAddForm = false" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors whitespace-nowrap">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Ideas Table -->
    <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-neutral-50 dark:bg-neutral-700/50 text-left">
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 w-10">#</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Topic</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Pillar</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Priority</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Status</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Source</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
            <tr v-if="isLoading && !ideas.length">
              <td colspan="7" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">
                <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                Loading ideas...
              </td>
            </tr>
            <tr v-else-if="!ideas.length">
              <td colspan="7" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">
                No ideas found. Click "Add Idea" or "Pull Trending" to get started.
              </td>
            </tr>
            <tr v-for="(idea, idx) in ideas" :key="idea.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700/30 transition-colors">
              <td class="px-4 py-3 text-neutral-400 dark:text-neutral-500">{{ idx + 1 }}</td>
              <td class="px-4 py-3 text-neutral-900 dark:text-neutral-100 font-medium max-w-xs truncate">{{ idea.title }}</td>
              <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300 text-xs">{{ idea.pillar || '-' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="priorityClass(idea.priority)">{{ idea.priority || '-' }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="statusClass(idea.status)">{{ formatStatus(idea.status) }}</span>
              </td>
              <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 text-xs">{{ idea.source || 'manual' }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <!-- Status-specific action -->
                  <button v-if="idea.status === 'draft'" @click="openConfigModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50 transition-colors">
                    Next &rarr;
                  </button>
                  <span v-else-if="idea.status === 'researching'" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs text-blue-600 dark:text-blue-400">
                    <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    Researching...
                  </span>
                  <button v-else-if="idea.status === 'researched'" @click="openResearchModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-purple-100 text-purple-700 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50 transition-colors">
                    Preview
                  </button>
                  <span v-else-if="idea.status === 'generating'" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs text-yellow-600 dark:text-yellow-400">
                    <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    Generating...
                  </span>
                  <button v-else-if="idea.status === 'completed'" @click="viewDrafts(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50 transition-colors">
                    View Drafts
                  </button>
                  <button v-else-if="idea.status === 'archived'" @click="handleRestore(idea.id)" class="px-2.5 py-1 text-xs font-medium rounded bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600 transition-colors">
                    Restore
                  </button>

                  <!-- Common actions (hidden for generating) -->
                  <template v-if="idea.status !== 'generating'">
                    <button @click="openEditModal(idea)" class="p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors" title="Edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                    </button>
                    <button v-if="idea.status !== 'archived'" @click="handleArchive(idea.id)" class="p-1.5 rounded text-neutral-400 hover:text-amber-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors" title="Archive">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                    </button>
                    <button @click="handleDelete(idea.id)" class="p-1.5 rounded text-neutral-400 hover:text-red-600 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors" title="Delete">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Workflow History -->
    <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 p-6">
      <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">Workflow History</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-neutral-50 dark:bg-neutral-700/50 text-left">
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">ID</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Type</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Topic</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Status</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Step</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400">Created</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
            <tr v-if="!workflows.length">
              <td colspan="6" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">No workflows yet.</td>
            </tr>
            <tr v-for="wf in workflows" :key="wf.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-700/30 transition-colors">
              <td class="px-4 py-3 text-neutral-400 dark:text-neutral-500 font-mono text-xs">{{ wf.id }}</td>
              <td class="px-4 py-3 text-neutral-900 dark:text-neutral-100">{{ wf.type || '-' }}</td>
              <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300 max-w-xs truncate">{{ wf.topic || wf.title || '-' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="statusClass(wf.status)">{{ formatStatus(wf.status) }}</span>
              </td>
              <td class="px-4 py-3 text-neutral-600 dark:text-neutral-300 text-xs">{{ wf.current_step || wf.step || '-' }}</td>
              <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 text-xs">{{ formatDate(wf.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Idea Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showEditModal = false">
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">Edit Idea</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Topic</label>
            <input v-model="editData.title" type="text" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Pillar</label>
            <select v-model="editData.pillar" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
              <option value="">None</option>
              <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Priority</label>
            <select v-model="editData.priority" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="showEditModal = false" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">Cancel</button>
          <button @click="handleUpdateIdea" :disabled="isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">Save Changes</button>
        </div>
      </div>
    </div>

    <!-- Trending Preview Modal -->
    <div v-if="showTrendingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showTrendingModal = false">
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Trending Topics</h3>
          <select v-model="trendingSourceFilter" @change="filterTrendingTopics" class="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-xs px-2 py-1 focus:ring-amber-500 focus:border-amber-500">
            <option value="">All Sources</option>
            <option v-for="src in trendingSources" :key="src.value" :value="src.value">{{ src.label }}</option>
          </select>
        </div>
        <div class="flex-1 overflow-y-auto space-y-2 min-h-0">
          <div v-if="trendingLoading" class="py-8 text-center text-neutral-500 dark:text-neutral-400">
            <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            Loading trending topics...
          </div>
          <div v-else-if="!filteredTrending.length" class="py-8 text-center text-neutral-500 dark:text-neutral-400">No trending topics found.</div>
          <label v-for="topic in filteredTrending" :key="topic.title || topic.topic" class="flex items-start gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700/30 cursor-pointer transition-colors">
            <input type="checkbox" :value="topic" v-model="selectedTrending" class="mt-0.5 rounded border-neutral-300 text-amber-600 focus:ring-amber-500" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ topic.title || topic.topic }}</p>
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400 mt-1">{{ topic.source || 'unknown' }}</span>
            </div>
          </label>
        </div>
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
          <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ selectedTrending.length }} selected</span>
          <div class="flex gap-2">
            <button @click="showTrendingModal = false" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">Cancel</button>
            <button @click="handleImportTrending" :disabled="!selectedTrending.length || isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
              Add {{ selectedTrending.length }} to Ideas List &rarr;
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Configuration Modal -->
    <div v-if="showConfigModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showConfigModal = false">
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">Configure Research</h3>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-5">{{ currentIdea?.title }}</p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Output Types</label>
            <div class="space-y-2">
              <label v-for="ot in outputTypeOptions" :key="ot.value" class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" :value="ot.value" v-model="configOutputTypes" class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ ot.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Languages</label>
            <div class="flex gap-4">
              <label v-for="lang in languageOptions" :key="lang.value" class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" :value="lang.value" v-model="configLanguages" class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ lang.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Instructions (optional)</label>
            <textarea v-model="configInstructions" rows="3" placeholder="Any specific instructions for research..." class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button @click="showConfigModal = false" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">Cancel</button>
          <button @click="handleStartResearch" :disabled="!configOutputTypes.length || !configLanguages.length || isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
            Confirm &amp; Research &rarr;
          </button>
        </div>
      </div>
    </div>

    <!-- Research Preview Modal -->
    <div v-if="showResearchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showResearchModal = false">
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-lg w-full mx-4 p-6 max-h-[80vh] flex flex-col">
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-4">Research Preview</h3>
        <div class="flex-1 overflow-y-auto space-y-4 min-h-0">
          <!-- Editable topic -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Topic</label>
            <input v-model="researchPreviewTopic" type="text" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500" />
          </div>

          <!-- Research data -->
          <div v-if="researchData">
            <div v-if="researchData.trending_score !== undefined" class="mb-3">
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Trending Score</span>
              <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ researchData.trending_score }}</p>
            </div>
            <div v-if="researchData.hooks?.length" class="mb-3">
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Hooks</span>
              <ul class="mt-1 space-y-1">
                <li v-for="(hook, i) in researchData.hooks" :key="i" class="text-sm text-neutral-700 dark:text-neutral-300 flex items-start gap-1.5">
                  <span class="text-amber-500 mt-0.5 flex-shrink-0">&bull;</span>
                  {{ hook }}
                </li>
              </ul>
            </div>
            <div v-if="researchData.angles?.length" class="mb-3">
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Angles</span>
              <ul class="mt-1 space-y-1">
                <li v-for="(angle, i) in researchData.angles" :key="i" class="text-sm text-neutral-700 dark:text-neutral-300 flex items-start gap-1.5">
                  <span class="text-amber-500 mt-0.5 flex-shrink-0">&bull;</span>
                  {{ angle }}
                </li>
              </ul>
            </div>
          </div>

          <!-- Generation summary -->
          <div v-if="currentIdea">
            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Will Generate</span>
            <div class="mt-1 flex flex-wrap gap-1.5">
              <span v-for="ot in (currentIdea.output_types || [])" :key="ot" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">{{ ot }}</span>
              <span v-for="lang in (currentIdea.languages || [])" :key="lang" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ lang }}</span>
            </div>
          </div>
        </div>
        <div class="flex justify-between mt-6 pt-4 border-t border-neutral-200 dark:border-neutral-700">
          <button @click="handleRevertToDraft" :disabled="isLoading" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors disabled:opacity-50">
            &larr; Back to Draft
          </button>
          <button @click="handleApproveGenerate" :disabled="isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
            Approve &amp; Generate
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const toast = useToast()
const {
  isLoading,
  checkHealth,
  listIdeas,
  createIdea,
  updateIdea,
  deleteIdea,
  archiveIdea,
  restoreIdea,
  revertToDraft,
  pullTrending,
  importTrending,
  startResearch,
  getResearch,
  approveGenerate,
  listWorkflows,
} = useContentEngine()

const pillars = ['Vibe Coding', 'AI Automation', 'AI Agents', 'AI Video & Image', 'General']
const statuses = ['draft', 'researching', 'researched', 'generating', 'completed', 'archived']
const trendingSources = [
  { label: 'All Sources', value: '' },
  { label: 'Google Trends', value: 'google_trends' },
  { label: 'YouTube', value: 'youtube' },
  { label: 'TikTok', value: 'tiktok' },
  { label: 'Google News', value: 'google_news' },
  { label: 'Instagram', value: 'instagram' },
]
const outputTypeOptions = [
  { label: 'Blog Article', value: 'blog_article' },
  { label: 'Instagram Carousel', value: 'carousel_rebrand' },
  { label: 'Social Video', value: 'video_social' },
  { label: 'Promo Video', value: 'video_promo' },
]
const languageOptions = [
  { label: 'English', value: 'en' },
  { label: 'Indonesian', value: 'id' },
]

// State
const health = ref(null)
const healthOnline = ref(false)
const ideas = ref([])
const workflows = ref([])
const filters = reactive({ pillar: '', status: '', priority: '', search: '' })
let pollInterval = null
let searchTimeout = null

// UI state
const showAddForm = ref(false)
const showEditModal = ref(false)
const showTrendingModal = ref(false)
const showConfigModal = ref(false)
const showResearchModal = ref(false)
const trendingDropdownOpen = ref(false)
const currentIdea = ref(null)
const trendingLoading = ref(false)

// Add form
const newIdea = reactive({ title: '', pillar: '', priority: 'medium' })

// Edit form
const editData = reactive({ id: null, title: '', pillar: '', priority: 'medium' })

// Trending modal
const trendingTopics = ref([])
const selectedTrending = ref([])
const trendingSourceFilter = ref('')
const filteredTrending = computed(() => {
  if (!trendingSourceFilter.value) return trendingTopics.value
  return trendingTopics.value.filter(t => t.source === trendingSourceFilter.value)
})

// Config modal
const configOutputTypes = ref([])
const configLanguages = ref([])
const configInstructions = ref('')

// Research preview
const researchData = ref(null)
const researchPreviewTopic = ref('')

// Helpers
function statusClass(status) {
  const map = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    researching: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    researched: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    generating: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    archived: 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400',
  }
  return map[status] || map.draft
}

function priorityClass(priority) {
  const map = {
    high: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    medium: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    low: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  }
  return map[priority] || 'bg-neutral-100 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400'
}

function formatStatus(s) {
  if (!s) return '-'
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function debounceSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => refreshIdeas(), 400)
}

// Data fetching
async function refreshHealth() {
  const result = await checkHealth()
  if (result.success) {
    health.value = result.data
    healthOnline.value = result.data?.healthy ?? false
  } else {
    healthOnline.value = false
  }
}

async function refreshIdeas() {
  const result = await listIdeas(filters)
  if (result.success) {
    ideas.value = result.data || []
  }
}

async function refreshWorkflows() {
  const result = await listWorkflows()
  if (result.success) {
    workflows.value = result.data || []
  }
}

// Idea CRUD
async function handleCreateIdea() {
  if (!newIdea.title) return
  const result = await createIdea({ title: newIdea.title, pillar: newIdea.pillar, priority: newIdea.priority })
  if (result.success) {
    toast.success('Idea created successfully')
    newIdea.title = ''
    newIdea.pillar = ''
    newIdea.priority = 'medium'
    showAddForm.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to create idea')
  }
}

function openEditModal(idea) {
  editData.id = idea.id
  editData.title = idea.title
  editData.pillar = idea.pillar || ''
  editData.priority = idea.priority || 'medium'
  showEditModal.value = true
}

async function handleUpdateIdea() {
  const result = await updateIdea(editData.id, { title: editData.title, pillar: editData.pillar, priority: editData.priority })
  if (result.success) {
    toast.success('Idea updated successfully')
    showEditModal.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to update idea')
  }
}

async function handleDelete(id) {
  if (!confirm('Are you sure you want to delete this idea?')) return
  const result = await deleteIdea(id)
  if (result.success) {
    toast.success('Idea deleted successfully')
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to delete idea')
  }
}

async function handleArchive(id) {
  const result = await archiveIdea(id)
  if (result.success) {
    toast.success('Idea archived')
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to archive idea')
  }
}

async function handleRestore(id) {
  const result = await restoreIdea(id)
  if (result.success) {
    toast.success('Idea restored')
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to restore idea')
  }
}

// Trending
async function openTrendingModal(source) {
  trendingDropdownOpen.value = false
  trendingSourceFilter.value = source || ''
  selectedTrending.value = []
  showTrendingModal.value = true
  trendingLoading.value = true
  const result = await pullTrending(source)
  trendingLoading.value = false
  if (result.success) {
    trendingTopics.value = result.data || []
  } else {
    toast.error(result.error || 'Failed to load trending topics')
    trendingTopics.value = []
  }
}

function filterTrendingTopics() {
  selectedTrending.value = []
}

async function handleImportTrending() {
  const topics = selectedTrending.value.map(t => ({
    title: t.title || t.topic,
    source: t.source,
    pillar: t.pillar || '',
    priority: t.priority || 'medium',
  }))
  const result = await importTrending(topics)
  if (result.success) {
    toast.success(`Imported ${result.data?.imported || topics.length} topics`)
    showTrendingModal.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to import topics')
  }
}

// Config modal (research)
function openConfigModal(idea) {
  currentIdea.value = idea
  configOutputTypes.value = []
  configLanguages.value = []
  configInstructions.value = ''
  showConfigModal.value = true
}

async function handleStartResearch() {
  if (!currentIdea.value) return
  const result = await startResearch(currentIdea.value.id, {
    output_types: configOutputTypes.value,
    languages: configLanguages.value,
    instructions: configInstructions.value || undefined,
  })
  if (result.success) {
    toast.success('Research started')
    showConfigModal.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to start research')
  }
}

// Research preview modal
async function openResearchModal(idea) {
  currentIdea.value = idea
  researchPreviewTopic.value = idea.title
  researchData.value = null
  showResearchModal.value = true
  const result = await getResearch(idea.id)
  if (result.success) {
    const data = result.data
    researchData.value = data?.research_data || data?.research || data || null
    if (data?.title) researchPreviewTopic.value = data.title
    if (data?.output_types) currentIdea.value = { ...currentIdea.value, output_types: data.output_types }
    if (data?.languages) currentIdea.value = { ...currentIdea.value, languages: data.languages }
  } else {
    toast.error(result.error || 'Failed to load research data')
  }
}

async function handleRevertToDraft() {
  if (!currentIdea.value) return
  const result = await revertToDraft(currentIdea.value.id)
  if (result.success) {
    toast.success('Reverted to draft')
    showResearchModal.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to revert')
  }
}

async function handleApproveGenerate() {
  if (!currentIdea.value) return
  if (researchPreviewTopic.value !== currentIdea.value.title) {
    await updateIdea(currentIdea.value.id, { title: researchPreviewTopic.value })
  }
  const result = await approveGenerate(currentIdea.value.id)
  if (result.success) {
    toast.success('Generation started')
    showResearchModal.value = false
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to start generation')
  }
}

// View drafts → navigate to posts
function viewDrafts(idea) {
  router.push({ path: '/admin/posts', query: { search: idea.title } })
}

// Lifecycle
onMounted(async () => {
  await Promise.all([refreshHealth(), refreshIdeas(), refreshWorkflows()])
  pollInterval = setInterval(refreshWorkflows, 10000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (searchTimeout) clearTimeout(searchTimeout)
})
</script>
