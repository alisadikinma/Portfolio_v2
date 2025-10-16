<template>
  <div class="automation-tokens-page">
    <!-- Page Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
          API Tokens
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Manage API tokens for automation platforms (n8n, Zapier, Make.com)
        </p>
      </div>
      <button
        @click="showCreateModal = true"
        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600"
      >
        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Create Token
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
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
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Revoked Tokens</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
              {{ automationStore.revokedTokens.length }}
            </p>
          </div>
          <div class="rounded-full bg-red-100 p-3 dark:bg-red-900">
            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Requests</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
              {{ totalRequests }}
            </p>
          </div>
          <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Tokens List -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
      <div class="border-b border-gray-200 p-6 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">API Tokens</h2>
      </div>

      <div v-if="automationStore.loading" class="flex items-center justify-center p-12">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
      </div>

      <div v-else-if="automationStore.tokens.length === 0" class="p-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No tokens yet</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Create your first API token to get started with automation
        </p>
        <button
          @click="showCreateModal = true"
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
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Abilities</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Used</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="token in automationStore.tokens" :key="token.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ token.name }}</div>
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
                {{ token.last_used_at ? formatDate(token.last_used_at) : 'Never' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                {{ formatDate(token.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  v-if="!token.revoked_at"
                  class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold leading-5 text-green-800 dark:bg-green-900 dark:text-green-200"
                >
                  Active
                </span>
                <span
                  v-else
                  class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold leading-5 text-red-800 dark:bg-red-900 dark:text-red-200"
                >
                  Revoked
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  v-if="!token.revoked_at"
                  @click="confirmRevoke(token)"
                  class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                >
                  Revoke
                </button>
              </td>
            </tr>
          </tbody>
        </table>
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
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Token Name</label>
              <input
                v-model="newToken.name"
                type="text"
                placeholder="e.g., n8n Workflow"
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
              />
            </div>

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
              </div>
            </div>
          </div>

          <div class="mt-6 flex space-x-3">
            <button
              @click="createToken"
              :disabled="!newToken.name || newToken.abilities.length === 0"
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

    <!-- Revoke Confirmation Modal -->
    <div
      v-if="showRevokeModal"
      class="fixed inset-0 z-50 overflow-y-auto"
      @click.self="showRevokeModal = false"
    >
      <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Revoke Token</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to revoke "{{ tokenToRevoke?.name }}"? This action cannot be undone.
          </p>

          <div class="mt-6 flex space-x-3">
            <button
              @click="revokeToken"
              class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
              Revoke
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
import { ref, computed, onMounted } from 'vue'
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

const newToken = ref({
  name: '',
  abilities: []
})

const availableAbilities = ['post:read', 'post:write', 'post:delete', 'category:read']

const totalRequests = computed(() => {
  return automationStore.tokens.reduce((total, token) => total + (token.requests_count || 0), 0)
})

onMounted(async () => {
  try {
    await automationStore.fetchTokens()
  } catch (error) {
    toast.error('Failed to load tokens')
  }
})

const createToken = async () => {
  try {
    const response = await automationStore.createToken(newToken.value)
    createdToken.value = response.token
    showCreateModal.value = false
    showTokenModal.value = true
    toast.success('Token created successfully')

    // Reset form
    newToken.value = { name: '', abilities: [] }
  } catch (error) {
    toast.error('Failed to create token')
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

const confirmRevoke = (token) => {
  tokenToRevoke.value = token
  showRevokeModal.value = true
}

const revokeToken = async () => {
  try {
    await automationStore.revokeToken(tokenToRevoke.value.id)
    showRevokeModal.value = false
    tokenToRevoke.value = null
    toast.success('Token revoked successfully')
  } catch (error) {
    toast.error('Failed to revoke token')
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
</script>
