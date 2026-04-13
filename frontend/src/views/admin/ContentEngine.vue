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
        <select v-model="filters.pillar" @change="pagination.current_page = 1; refreshIdeas()" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">All Pillars</option>
          <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
        </select>
        <select v-model="filters.status" @change="pagination.current_page = 1; refreshIdeas()" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s" :value="s">{{ formatStatus(s) }}</option>
        </select>
        <select v-model="filters.priority" @change="pagination.current_page = 1; refreshIdeas()" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
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
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <input v-model="newIdea.title" type="text" placeholder="Topic title..." class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400 sm:col-span-2" />
        <select v-model="newIdea.pillar" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="">Select Pillar</option>
          <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
        </select>
        <select v-model="newIdea.priority" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
          <option value="medium">Medium</option>
          <option value="low">Low</option>
          <option value="high">High</option>
        </select>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-3">
        <div class="flex items-center gap-3 sm:col-span-1">
          <label class="flex items-center gap-2 cursor-pointer text-sm text-neutral-700 dark:text-neutral-300">
            <input type="checkbox" v-model="newIdea.auto_mode" class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500" />
            Auto mode
          </label>
        </div>
        <div class="sm:col-span-1">
          <input v-model="newIdea.scheduled_at" type="datetime-local" class="block w-full rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500" placeholder="Schedule (optional)" />
        </div>
        <div class="flex gap-2 sm:col-span-2 justify-end">
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
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 text-center w-16">Auto</th>
              <th class="px-4 py-3 font-medium text-neutral-500 dark:text-neutral-400 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
            <tr v-if="isLoading && !ideas.length">
              <td colspan="8" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">
                <svg class="animate-spin h-6 w-6 mx-auto mb-2 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                Loading ideas...
              </td>
            </tr>
            <tr v-else-if="!ideas.length">
              <td colspan="8" class="px-4 py-12 text-center text-neutral-500 dark:text-neutral-400">
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
              <td class="px-4 py-3 text-center">
                <button @click="toggleAutoMode(idea)" :class="['w-8 h-5 rounded-full relative transition-colors', idea.auto_mode ? 'bg-amber-500' : 'bg-neutral-300 dark:bg-neutral-600']">
                  <span :class="['absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform', idea.auto_mode ? 'translate-x-3.5' : 'translate-x-0.5']"></span>
                </button>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <!-- Status-specific action -->
                  <button v-if="idea.status === 'draft'" @click="openConfigModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50 transition-colors">
                    Next &rarr;
                  </button>
                  <button v-else-if="idea.status === 'researching'" @click="openProgressModal(idea)" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 transition-colors">
                    <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    {{ idea.progress_percentage || 0 }}% — View Progress
                  </button>
                  <button v-else-if="idea.status === 'article_ready'" @click="openResearchModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-purple-100 text-purple-700 hover:bg-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50 transition-colors">
                    Preview Article
                  </button>
                  <button v-else-if="idea.status === 'generating_images'" @click="openProgressModal(idea)" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:hover:bg-yellow-900/50 transition-colors">
                    <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                    {{ idea.progress_percentage || 0 }}% — View Progress
                  </button>
                  <button v-else-if="idea.status === 'images_ready'" @click="openResearchModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition-colors">
                    Finalize
                  </button>
                  <button v-else-if="idea.status === 'completed'" @click="openResearchModal(idea)" class="px-2.5 py-1 text-xs font-medium rounded bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50 transition-colors">
                    View
                  </button>
                  <button v-else-if="idea.status === 'archived'" @click="handleRestore(idea.id)" class="px-2.5 py-1 text-xs font-medium rounded bg-neutral-100 text-neutral-600 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600 transition-colors">
                    Restore
                  </button>

                  <!-- Common actions (hidden for generating_images) -->
                  <template v-if="idea.status !== 'generating_images'">
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

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-neutral-200 dark:border-neutral-700">
        <span class="text-xs text-neutral-500 dark:text-neutral-400">
          {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
        </span>
        <div class="flex items-center gap-1">
          <button @click="pagination.current_page = 1; refreshIdeas()" :disabled="pagination.current_page <= 1" class="px-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-30 transition-colors">
            First
          </button>
          <button @click="pagination.current_page--; refreshIdeas()" :disabled="pagination.current_page <= 1" class="px-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-30 transition-colors">
            Prev
          </button>
          <span class="px-3 py-1 text-xs font-medium text-neutral-700 dark:text-neutral-300">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button @click="pagination.current_page++; refreshIdeas()" :disabled="pagination.current_page >= pagination.last_page" class="px-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-30 transition-colors">
            Next
          </button>
          <button @click="pagination.current_page = pagination.last_page; refreshIdeas()" :disabled="pagination.current_page >= pagination.last_page" class="px-2.5 py-1 text-xs rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-700 disabled:opacity-30 transition-colors">
            Last
          </button>
        </div>
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
        <!-- Search + Select All -->
        <div class="flex items-center gap-3 mb-3">
          <input v-model="trendingSearch" type="text" placeholder="Search topics... (e.g. claude)" class="flex-1 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-sm px-3 py-2 focus:ring-amber-500 focus:border-amber-500 placeholder-neutral-400" />
          <label v-if="filteredTrending.length" class="flex items-center gap-2 cursor-pointer whitespace-nowrap text-xs text-neutral-500 dark:text-neutral-400">
            <input type="checkbox" :checked="allFilteredSelected" @change="toggleSelectAll" class="rounded border-neutral-300 text-amber-600 focus:ring-amber-500" />
            Select All
          </label>
        </div>
        <div class="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-2 min-h-0 content-start">
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
          <button @click="handleStartResearch" :disabled="!configLanguages.length || isLoading" class="px-4 py-2 text-sm font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-50">
            Confirm &amp; Research &rarr;
          </button>
        </div>
      </div>
    </div>

    <!-- Progress Modal -->
    <div v-if="showProgressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closeProgressModal">
      <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl max-w-4xl w-full mx-4 p-6 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Article Generation Progress</h3>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">{{ progressIdea?.title }}</p>
          </div>
          <button @click="closeProgressModal" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ formatStepName(progressData.current_step) }}</span>
            <span class="text-sm font-mono text-amber-600 dark:text-amber-400">{{ progressData.progress_percentage }}%</span>
          </div>
          <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-3 overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-700 ease-out"
              :class="progressData.current_step === 'failed' ? 'bg-red-500' : 'bg-gradient-to-r from-amber-500 to-amber-400'"
              :style="{ width: progressData.progress_percentage + '%' }"
            ></div>
          </div>
        </div>

        <!-- Step Indicators -->
        <div class="flex flex-wrap gap-1.5 mb-4">
          <span
            v-for="step in progressSteps"
            :key="step.name"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium transition-colors cursor-default"
            :class="stepIndicatorClass(step)"
            :title="`${step.label} — ${step.skill} (${step.model}) @ ${step.pct}%`"
          >
            <svg v-if="stepIsDone(step)" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            <svg v-else-if="stepIsActive(step)" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ step.label }}
            <span v-if="stepIsActive(step)" class="text-[8px] opacity-70">{{ step.model }}</span>
          </span>
        </div>

        <!-- Active Skill + Model Info -->
        <div class="flex items-center gap-3 mb-3 px-1">
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] font-mono text-neutral-500 uppercase tracking-wider">Skill:</span>
            <span class="text-xs font-mono font-medium" :class="activeSkillColor">{{ activeSkill }}</span>
          </div>
          <div class="w-px h-3 bg-neutral-700"></div>
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] font-mono text-neutral-500 uppercase tracking-wider">Model:</span>
            <span class="text-xs font-mono font-medium" :class="activeModelColor">{{ activeModel }}</span>
          </div>
          <div class="w-px h-3 bg-neutral-700"></div>
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] font-mono text-neutral-500 uppercase tracking-wider">Pipeline:</span>
            <span class="text-xs font-mono text-cyan-400">{{ progressIdea?.workflows?.[0]?.pipeline || 'single' }}</span>
          </div>
        </div>

        <!-- Log Viewer -->
        <div class="flex-1 min-h-0 bg-neutral-950 rounded-lg overflow-hidden flex flex-col">
          <div class="px-3 py-2 bg-neutral-900 border-b border-neutral-800 flex items-center justify-between shrink-0">
            <span class="text-xs font-mono text-neutral-400">Generation Log</span>
            <span class="text-[10px] font-mono text-neutral-500">{{ (progressData.progress_log || []).length }} entries</span>
          </div>
          <div ref="logContainer" class="overflow-y-auto flex-1 p-3 space-y-1 font-mono text-xs" style="max-height: 400px; scroll-behavior: smooth;">
            <div v-if="!(progressData.progress_log || []).length" class="text-neutral-500 py-4 text-center">Waiting for log entries...</div>
            <div
              v-for="(entry, i) in (progressData.progress_log || [])"
              :key="i"
              class="flex gap-2"
              :class="entry.step === 'failed' ? 'text-red-400' : 'text-neutral-300'"
            >
              <span class="text-neutral-600 shrink-0">{{ formatLogTime(entry.timestamp) }}</span>
              <span :class="entry.step === 'failed' ? 'text-red-400' : entry.step === 'completed' ? 'text-green-400' : 'text-amber-400'" class="shrink-0">[{{ entry.step }}]</span>
              <span class="text-neutral-300 break-words">{{ entry.message }}</span>
            </div>
          </div>
        </div>

        <!-- Status Footer -->
        <div class="flex items-center justify-between mt-4 pt-3 border-t border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full" :class="progressData.process_alive !== false ? 'bg-green-500 animate-pulse' : 'bg-red-500'"></span>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">
              {{ progressData.process_alive !== false ? 'Process running' : 'Process stopped' }}
            </span>
          </div>
          <button @click="closeProgressModal" class="px-4 py-2 text-sm font-medium rounded-lg border border-neutral-300 dark:border-neutral-600 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContentEngine } from '@/composables/useContentEngine'
import { useToast } from '@/composables/useToast'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()
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
  getProgress,
  approveArticle,
  startImageGeneration,
  approveAndPublish,
  listWorkflows,
} = useContentEngine()

const pillars = ['Vibe Coding', 'AI Automation', 'AI Agents', 'AI Video & Image', 'General']
const statuses = ['draft', 'researching', 'article_ready', 'generating_images', 'images_ready', 'completed', 'archived']
const trendingSources = [
  { label: 'All Sources', value: '' },
  { label: 'Google Trends', value: 'google_trends' },
  { label: 'YouTube', value: 'youtube' },
  { label: 'TikTok', value: 'tiktok' },
  { label: 'Google News', value: 'google_news' },
  { label: 'Instagram', value: 'instagram' },
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
const pagination = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
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
const newIdea = reactive({ title: '', pillar: '', priority: 'medium', auto_mode: false, scheduled_at: '' })

// Edit form
const editData = reactive({ id: null, title: '', pillar: '', priority: 'medium' })

// Trending modal
const trendingTopics = ref([])
const selectedTrending = ref([])
const trendingSourceFilter = ref('')
const trendingSearch = ref('')
const filteredTrending = computed(() => {
  let results = trendingTopics.value
  if (trendingSourceFilter.value) {
    results = results.filter(t => t.source === trendingSourceFilter.value)
  }
  if (trendingSearch.value.trim()) {
    const q = trendingSearch.value.trim().toLowerCase()
    results = results.filter(t => (t.title || '').toLowerCase().includes(q))
  }
  return results
})
const allFilteredSelected = computed(() => filteredTrending.value.length > 0 && filteredTrending.value.every(t => selectedTrending.value.includes(t)))
function toggleSelectAll() {
  if (allFilteredSelected.value) {
    selectedTrending.value = selectedTrending.value.filter(t => !filteredTrending.value.includes(t))
  } else {
    const toAdd = filteredTrending.value.filter(t => !selectedTrending.value.includes(t))
    selectedTrending.value = [...selectedTrending.value, ...toAdd]
  }
}

// Config modal
const configLanguages = ref([])
const configInstructions = ref('')

// Image config modal
const imageInstructions = ref('')
const imageRefFiles = ref([])
const imageRefPreviews = ref([])
const imageRefInput = ref(null)

// Images preview modal

// Progress modal
const showProgressModal = ref(false)
const progressIdea = ref(null)
const progressData = ref({
  progress_percentage: 0,
  current_step: 'initializing',
  progress_log: [],
  process_alive: true,
})
let progressPollInterval = null
const logContainer = ref(null)

const progressSteps = [
  { name: 'input_collection', label: 'Input', pct: 5, skill: 'article-prep', model: 'Sonnet' },
  { name: 'topic_research', label: 'Research', pct: 15, skill: 'article-prep', model: 'Sonnet' },
  { name: 'strategy', label: 'Strategy', pct: 25, skill: 'article-prep', model: 'Sonnet' },
  { name: 'outline', label: 'Outline', pct: 35, skill: 'article-prep', model: 'Sonnet' },
  { name: 'writing_started', label: 'Writing', pct: 50, skill: 'article-write', model: 'Opus' },
  { name: 'draft_complete', label: 'Draft', pct: 70, skill: 'article-write', model: 'Opus' },
  { name: 'style_pass', label: 'Style', pct: 78, skill: 'article-write', model: 'Opus' },
  { name: 'seo_pass', label: 'SEO', pct: 82, skill: 'article-write', model: 'Opus' },
  { name: 'images_generated', label: 'Images', pct: 85, skill: 'article-write', model: 'Opus' },
  { name: 'virality_scored', label: 'Virality', pct: 90, skill: 'article-score', model: 'Sonnet' },
  { name: 'quality_scored', label: 'Quality', pct: 94, skill: 'article-score', model: 'Sonnet' },
  { name: 'seo_scored', label: 'SEO Gate', pct: 97, skill: 'article-score', model: 'Sonnet' },
  { name: 'completed', label: 'Done', pct: 100, skill: '-', model: '-' },
]

// Active skill + model detection based on progress percentage
const activeSkill = computed(() => {
  const pct = progressData.value.progress_percentage || 0
  if (pct <= 35) return '/article-prep'
  if (pct <= 85) return '/article-write'
  if (pct < 100) return '/article-score'
  return 'completed'
})
const activeModel = computed(() => {
  const pct = progressData.value.progress_percentage || 0
  if (pct <= 35) return 'Sonnet 4.6'
  if (pct <= 85) return 'Opus 4.6'
  if (pct < 100) return 'Sonnet 4.6'
  return '-'
})
const activeSkillColor = computed(() => {
  const pct = progressData.value.progress_percentage || 0
  if (pct <= 35) return 'text-blue-400'
  if (pct <= 85) return 'text-purple-400'
  return 'text-blue-400'
})
const activeModelColor = computed(() => {
  const pct = progressData.value.progress_percentage || 0
  if (pct <= 35) return 'text-emerald-400'
  if (pct <= 85) return 'text-amber-400'
  return 'text-emerald-400'
})

// Research preview
const researchData = ref(null)
const researchPreviewTopic = ref('')

// Helpers
function statusClass(status) {
  const map = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    researching: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    article_ready: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    generating_images: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    images_ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
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
  return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function debounceSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { pagination.value.current_page = 1; refreshIdeas() }, 400)
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
  isLoading.value = true
  try {
    // Filter out empty string values to avoid backend treating them as real filters
    const params = { page: pagination.value.current_page, per_page: 15 }
    if (filters.pillar) params.pillar = filters.pillar
    if (filters.status) params.status = filters.status
    if (filters.priority) params.priority = filters.priority
    if (filters.search) params.search = filters.search
    const response = await api.get('/admin/content-engine/ideas', { params })
    if (response.data?.success) {
      ideas.value = response.data.data || []
      if (response.data.meta) {
        pagination.value = response.data.meta
      }
    }
  } catch (err) {
    console.error('Failed to load ideas:', err)
  } finally {
    isLoading.value = false
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
  const data = {
    title: newIdea.title,
    pillar: newIdea.pillar,
    priority: newIdea.priority,
    auto_mode: newIdea.auto_mode,
  }
  if (newIdea.scheduled_at) data.scheduled_at = newIdea.scheduled_at
  const result = await createIdea(data)
  if (result.success) {
    toast.success(newIdea.scheduled_at ? 'Idea scheduled successfully' : 'Idea created successfully')
    newIdea.title = ''
    newIdea.pillar = ''
    newIdea.priority = 'medium'
    newIdea.auto_mode = false
    newIdea.scheduled_at = ''
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

async function toggleAutoMode(idea) {
  const result = await updateIdea(idea.id, { auto_mode: !idea.auto_mode })
  if (result.success) {
    idea.auto_mode = !idea.auto_mode
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
  trendingSearch.value = ''
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
  configLanguages.value = ['en', 'id']
  configInstructions.value = ''
  showConfigModal.value = true
}

async function handleStartResearch() {
  if (!currentIdea.value) return
  const result = await startResearch(currentIdea.value.id, {
    languages: configLanguages.value,
    instructions: configInstructions.value || undefined,
  })
  if (result.success) {
    toast.success('Article generation started via CLI')
    showConfigModal.value = false
    await refreshIdeas()
    // Auto-open progress modal
    const updatedIdea = ideas.value.find(i => i.id === currentIdea.value.id)
    if (updatedIdea) {
      openProgressModal(updatedIdea)
    }
  } else {
    toast.error(result.error || 'Failed to start research')
  }
}

// Research preview modal / pipeline navigation
async function openResearchModal(idea) {
  // Pipeline steps → open full-page views in new tab
  if (idea.status === 'article_ready') {
    window.open(`/admin/content-engine/${idea.id}/preview`, '_blank')
    return
  }
  if (idea.status === 'generating_images' || idea.status === 'images_ready') {
    window.open(`/admin/content-engine/${idea.id}/images`, '_blank')
    return
  }
  if (idea.status === 'completed') {
    window.open(`/admin/content-engine/${idea.id}/finalize`, '_blank')
    return
  }

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

// Gate 1: Approve article text -> open image config
async function handleApproveArticle() {
  const result = await approveArticle(currentIdea.value.id)
  if (result.success) {
    toast.success('Article text approved!')
    showResearchModal.value = false
    showImageConfigModal.value = true
  } else {
    toast.error(result.error || 'Failed to approve article')
  }
}

// Image reference file handling
function handleImageRefSelect(e) {
  const files = Array.from(e.target.files)
  imageRefFiles.value = [...imageRefFiles.value, ...files]
  files.forEach(file => {
    const reader = new FileReader()
    reader.onload = (ev) => imageRefPreviews.value.push(ev.target.result)
    reader.readAsDataURL(file)
  })
}

function removeImageRef(index) {
  imageRefFiles.value.splice(index, 1)
  imageRefPreviews.value.splice(index, 1)
}

// Gate 2: Start image generation
async function handleStartImageGen() {
  const formData = new FormData()
  if (imageInstructions.value) {
    formData.append('image_instructions', imageInstructions.value)
  }
  imageRefFiles.value.forEach(file => {
    formData.append('image_references[]', file)
  })

  const result = await startImageGeneration(currentIdea.value.id, formData)
  if (result.success) {
    toast.success('Image generation started!')
    showImageConfigModal.value = false
    imageInstructions.value = ''
    imageRefFiles.value = []
    imageRefPreviews.value = []
    await refreshIdeas()
  } else {
    toast.error(result.error || 'Failed to start image generation')
  }
}


// Progress modal
function formatStepName(step) {
  if (!step) return 'Initializing...'
  const map = {
    initializing: 'Initializing...',
    input_collection: 'Collecting input...',
    topic_research: 'Researching topic...',
    research: 'Researching topic...',
    framework_selection: 'Selecting framework...',
    strategy: 'Selecting strategy...',
    emotional_arc: 'Mapping emotional arc...',
    hook_generation: 'Generating hooks...',
    outline_generation: 'Creating outline...',
    outline: 'Creating outline...',
    writing_started: 'Writing Started',
    article_writing: 'Writing article...',
    draft_complete: 'Draft Complete',
    style_pass: 'Style Pass',
    seo_pass: 'SEO Optimization',
    image_prompts: 'Generating image prompts...',
    images_generated: 'Images Generated',
    virality_score: 'Scoring virality...',
    virality_scored: 'Virality Scored',
    quality_gate: 'Quality gate check...',
    quality_scored: 'Quality Scored',
    seo_scored: 'SEO Scored',
    completed: 'Completed!',
    failed: 'Failed',
  }
  return map[step] || step.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function formatLogTime(timestamp) {
  if (!timestamp) return '--:--:--'
  return new Date(timestamp).toLocaleTimeString('en-US', { hour12: false })
}

function stepIsDone(step) {
  return progressData.value.progress_percentage > step.pct
}

function stepIsActive(step) {
  const pct = progressData.value.progress_percentage || 0
  const idx = progressSteps.findIndex(s => s.name === step.name)
  const nextStep = progressSteps[idx + 1]
  return pct >= step.pct && (!nextStep || pct < nextStep.pct)
}

function stepIndicatorClass(step) {
  if (stepIsDone(step)) return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
  if (stepIsActive(step)) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 ring-1 ring-amber-400 animate-pulse'
  return 'bg-neutral-100 text-neutral-400 dark:bg-neutral-700 dark:text-neutral-500'
}

function openProgressModal(idea) {
  progressIdea.value = idea
  progressData.value = {
    progress_percentage: idea.progress_percentage || 0,
    current_step: idea.current_step || 'initializing',
    progress_log: idea.progress_log || [],
    process_alive: true,
  }
  showProgressModal.value = true
  startProgressPolling(idea.id)
}

function closeProgressModal() {
  showProgressModal.value = false
  stopProgressPolling()
}

function startProgressPolling(ideaId) {
  stopProgressPolling()
  pollProgress(ideaId)
  progressPollInterval = setInterval(() => pollProgress(ideaId), 3000)
}

function stopProgressPolling() {
  if (progressPollInterval) {
    clearInterval(progressPollInterval)
    progressPollInterval = null
  }
}

async function pollProgress(ideaId) {
  const result = await getProgress(ideaId)
  if (result.success && result.data) {
    progressData.value = result.data
    // Sync progress back to ideas list so the table row updates in real-time
    const idx = ideas.value.findIndex(i => i.id === ideaId)
    if (idx !== -1) {
      ideas.value[idx].progress_percentage = result.data.progress_percentage
      ideas.value[idx].current_step = result.data.current_step
      ideas.value[idx].status = result.data.status || ideas.value[idx].status
    }
    // Auto-scroll log to bottom
    if (logContainer.value) {
      setTimeout(() => {
        logContainer.value.scrollTop = logContainer.value.scrollHeight
      }, 50)
    }
    // Auto-close and refresh when completed
    if (result.data.status === 'article_ready' || result.data.current_step === 'completed') {
      stopProgressPolling()
      await refreshIdeas()
    }
    // Stop polling if process died or failed
    if (result.data.process_alive === false || result.data.current_step === 'failed') {
      stopProgressPolling()
    }
  }
}

// View drafts -> navigate to posts
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
  stopProgressPolling()
})
</script>
