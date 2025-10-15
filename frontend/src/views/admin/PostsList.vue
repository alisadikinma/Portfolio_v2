<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100">
          Manage Blog Posts
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400 mt-1">
          Total: {{ pagination.total }} posts
        </p>
      </div>
      <BaseButton @click="handleNewPost">
        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Post
      </BaseButton>
    </div>

    <!-- Search and Filters -->
    <BaseCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Search -->
        <div>
          <BaseInput
            v-model="searchQuery"
            type="text"
            placeholder="Search posts..."
            @input="debouncedSearch"
          >
            <template #prefix>
              <svg class="w-5 h-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </template>
          </BaseInput>
        </div>

        <!-- Category Filter -->
        <div>
          <select
            v-model="categoryFilter"
            @change="applyFilters"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
          >
            <option value="">All Categories</option>
            <option v-for="category in categories" :key="category.id" :value="category.id">
              {{ category.name }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <select
            v-model="statusFilter"
            @change="applyFilters"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-md bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100"
          >
            <option value="">All Status</option>
            <option value="true">Published</option>
            <option value="false">Draft</option>
          </select>
        </div>
      </div>
    </BaseCard>

    <!-- Posts Table -->
    <BaseCard>
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-neutral-600 dark:text-neutral-400 mb-4">{{ error }}</p>
        <BaseButton @click="fetchPosts">Retry</BaseButton>
      </div>

      <!-- Empty State -->
      <div v-else-if="posts.length === 0" class="text-center py-12">
        <svg class="w-12 h-12 text-neutral-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          No Posts Found
        </h3>
        <p class="text-neutral-600 dark:text-neutral-400 mb-6">
          {{ searchQuery || categoryFilter || statusFilter ? 'Try adjusting your filters' : 'Get started by creating your first blog post' }}
        </p>
        <BaseButton v-if="!searchQuery && !categoryFilter && !statusFilter" @click="handleNewPost">
          Create Post
        </BaseButton>
      </div>

      <!-- Posts Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
          <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Post
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Category
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Date
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Views
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-neutral-900 divide-y divide-neutral-200 dark:divide-neutral-700">
            <tr v-for="post in posts" :key="post.id" class="hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
              <!-- Post Info -->
              <td class="px-6 py-4">
                <div class="flex items-center">
                  <div v-if="post.featured_image" class="flex-shrink-0 h-10 w-16">
                    <img :src="post.featured_image" :alt="post.title" class="h-10 w-16 rounded object-cover">
                  </div>
                  <div :class="post.featured_image ? 'ml-4' : ''">
                    <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100 line-clamp-1">
                      {{ post.title }}
                    </div>
                    <div v-if="post.excerpt" class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-1 max-w-xs">
                      {{ post.excerpt }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="post.category" class="px-2 py-1 text-xs rounded-full" :style="{ backgroundColor: post.category.color + '20', color: post.category.color }">
                  {{ post.category.name }}
                </span>
                <span v-else class="text-sm text-neutral-500 dark:text-neutral-400">-</span>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="post.published" class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                  Published
                </span>
                <span v-else class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                  Draft
                </span>
              </td>

              <!-- Date -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ formatDate(post.published_at || post.created_at) }}
                </div>
              </td>

              <!-- Views -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-neutral-900 dark:text-neutral-100">
                  {{ post.views || 0 }}
                </div>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end gap-2">
                  <!-- Edit -->
                  <button
                    @click="router.push(`/admin/posts/${post.id}/edit`)"
                    class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300"
                    title="Edit post"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>

                  <!-- Delete -->
                  <button
                    @click="confirmDelete(post)"
                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    title="Delete post"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between">
            <div class="text-sm text-neutral-600 dark:text-neutral-400">
              Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to
              {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
              {{ pagination.total }} results
            </div>

            <div class="flex items-center gap-2">
              <!-- Previous -->
              <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Previous
              </button>

              <!-- Page Numbers -->
              <button
                v-for="page in visiblePages"
                :key="page"
                @click="changePage(page)"
                :class="[
                  'px-3 py-1 border rounded-md text-sm',
                  page === pagination.current_page
                    ? 'bg-primary-600 text-white border-primary-600'
                    : 'border-neutral-300 dark:border-neutral-600 hover:bg-neutral-50 dark:hover:bg-neutral-800'
                ]"
              >
                {{ page }}
              </button>

              <!-- Next -->
              <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-1 border border-neutral-300 dark:border-neutral-600 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-neutral-50 dark:hover:bg-neutral-800"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </BaseCard>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="postToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="postToDelete = null"
      >
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Delete Post
          </h3>
          <p class="text-neutral-600 dark:text-neutral-400 mb-6">
            Are you sure you want to delete "<strong>{{ postToDelete.title }}</strong>"? This action cannot be undone.
          </p>
          <div class="flex items-center justify-end gap-3">
            <BaseButton
              variant="secondary"
              @click="postToDelete = null"
              :disabled="isDeleting"
            >
              Cancel
            </BaseButton>
            <BaseButton
              variant="danger"
              @click="handleDelete"
              :loading="isDeleting"
              :disabled="isDeleting"
            >
              Delete Post
            </BaseButton>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '@/stores/posts'
import { useCategoriesStore } from '@/stores/categories'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseInput from '@/components/base/BaseInput.vue'

const router = useRouter()
const postsStore = usePostsStore()
const categoriesStore = useCategoriesStore()
const uiStore = useUiStore()

const searchQuery = ref('')
const categoryFilter = ref('')
const statusFilter = ref('')
const postToDelete = ref(null)
const isDeleting = ref(false)

const posts = computed(() => postsStore.posts)
const pagination = computed(() => postsStore.pagination)
const loading = computed(() => postsStore.loading)
const error = computed(() => postsStore.error)
const categories = computed(() => categoriesStore.categories)

// Visible page numbers for pagination
const visiblePages = computed(() => {
  const current = pagination.value.currentPage
  const last = pagination.value.lastPage
  const pages = []

  // Show max 5 page numbers
  let start = Math.max(1, current - 2)
  let end = Math.min(last, start + 4)

  // Adjust start if we're near the end
  if (end - start < 4) {
    start = Math.max(1, end - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})

onMounted(async () => {
  console.log('PostsList mounted, fetching data...')
  await categoriesStore.fetchCategories()
  console.log('Categories loaded:', categoriesStore.categories)
  await fetchPosts()
  console.log('Posts loaded:', postsStore.posts)
  console.log('Pagination:', postsStore.pagination)
})

async function fetchPosts() {
  try {
    const filters = {}
    if (searchQuery.value) filters.search = searchQuery.value
    if (categoryFilter.value) filters.category_id = categoryFilter.value
    if (statusFilter.value !== '') filters.published = statusFilter.value

    await postsStore.fetchPosts(filters)
  } catch (err) {
    console.error('Failed to fetch posts:', err)
  }
}

// Debounced search
let searchTimeout
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchPosts()
  }, 300)
}

function applyFilters() {
  fetchPosts()
}

function handleNewPost() {
  console.log('New Post button clicked')
  router.push('/admin/posts/create')
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.lastPage) {
    postsStore.pagination.currentPage = page
    fetchPosts()
  }
}

function confirmDelete(post) {
  postToDelete.value = post
}

async function handleDelete() {
  if (!postToDelete.value) return

  isDeleting.value = true

  try {
    await postsStore.deletePost(postToDelete.value.id)

    uiStore.showSuccess(
      `"${postToDelete.value.title}" has been deleted successfully.`,
      'Post Deleted'
    )

    postToDelete.value = null

    // Reload posts if current page is now empty
    if (posts.value.length === 0 && pagination.value.currentPage > 1) {
      pagination.value.currentPage--
    }
    await fetchPosts()
  } catch (err) {
    console.error('Failed to delete post:', err)

    uiStore.showError(
      err.response?.data?.message || 'Failed to delete post. Please try again.',
      'Delete Failed',
      0 // Persistent error
    )
  } finally {
    isDeleting.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
